<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
/** Publicación atómica de una consolidación previamente revisada.
 *  Funciona en MariaDB 10.11; no emplea ST_IsValid, no toca SURC ni RRHH.
 */
final class PublicarConsolidacion {
 private PDO $db;
 public function __construct() { $this->db=Conexion::getConexion('BARRIOS'); $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
 private function one(string $sql,array $params=[]): ?array {
  $s=$this->db->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;
 }
 private function all(string $sql,array $params=[]):array {
  $s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);
 }
 private function countSql(string $sql,array $params=[]):int { $r=$this->one($sql,$params);return (int)array_values($r??[0])[0]; }
 private function check(bool $ok,string $msg,int $code=409):void {
  if(!$ok)throw new RuntimeException($msg,$code);
 }
 /** Comprobación conservadora del GeoJSON, sin permitir topología compleja no verificada. */
 private function validarGeometria(array $geo):array {
  $this->check(($geo['type']??null)==='MultiPolygon','La geometría municipal no es MULTIPOLYGON.');
  $pol=$geo['coordinates']??null;
  $this->check(is_array($pol)&&count($pol)===1,'La geometría contiene múltiples polígonos: requiere revisión topológica GIS.');
  $this->check(count($pol[0])===1,'La geometría tiene huecos: requiere revisión topológica GIS.');
  $ring=$pol[0][0];
  $this->check(is_array($ring)&&count($ring)>=4&&count($ring)<=5000,'Anillo exterior inválido o excesivamente grande.');
  $n=count($ring)-1;
  $this->check($ring[0]===$ring[$n],'El anillo no está cerrado.');
  $coords=[];
  foreach($ring as $p){
   $this->check(is_array($p)&&count($p)===2&&is_numeric($p[0])&&is_numeric($p[1]),'Coordenada no válida.');
   $x=(float)$p[0];$y=(float)$p[1];
   $this->check(is_finite($x)&&is_finite($y)&&abs($x)<=180&&abs($y)<=90,'Coordenada fuera de rango.');
   $coords[]=[$x,$y];
  }
  $area=0.;for($i=0;$i<$n;$i++)$area+=$coords[$i][0]*$coords[$i+1][1]-$coords[$i+1][0]*$coords[$i][1];
  $this->check(abs($area)>1.0e-12,'Anillo con área nula.');
  // Chequeo básico de autointersección de aristas no adyacentes.
  $cross=static fn($a,$b,$c)=>($b[0]-$a[0])*($c[1]-$a[1])-($b[1]-$a[1])*($c[0]-$a[0]);
  for($i=0;$i<$n;$i++)for($j=$i+2;$j<$n;$j++){
   if($i===0&&$j===$n-1)continue;
   $a=$coords[$i];$b=$coords[$i+1];$c=$coords[$j];$d=$coords[$j+1];
   $s1=$cross($a,$b,$c);$s2=$cross($a,$b,$d);$s3=$cross($c,$d,$a);$s4=$cross($c,$d,$b);
   $eps=1e-13;
   if((($s1>$eps&&$s2<-$eps)||($s1<-$eps&&$s2>$eps)) && (($s3>$eps&&$s4<-$eps)||($s3<-$eps&&$s4>$eps))) throw new RuntimeException('Polígono autointersectado; corregir en el editor.',409);
  }
  return ['vertices'=>$n,'poligonos'=>1,'huecos'=>0];
 }
 private function datos(int $id,bool $bloquear):array {
  $sql='SELECT * FROM db_barrios.bar_consolidaciones WHERE id_consolidacion=:id'.($bloquear?' FOR UPDATE':'');
  $c=$this->one($sql,[':id'=>$id]);$this->check((bool)$c,'Consolidación no encontrada.',404);
  $this->check($c['estado']==='PENDIENTE','La consolidación ya no está pendiente.');
  // Toda jurisdicción debe estar resuelta antes de publicar el gobernante.
  // La cobertura POR_VERIFICAR puede conservarse, pero NO será jurisdicción actual.
  $jurisdicciones=$this->all('SELECT gen_organismos_id,cobertura,estado,observaciones FROM db_barrios.bar_consolidaciones_jurisdicciones WHERE id_consolidacion=:id ORDER BY gen_organismos_id'.($bloquear?' FOR UPDATE':''),[':id'=>$id]);
  foreach($jurisdicciones as $j){
   $this->check(in_array($j['estado'],['VALIDADA','DESCARTADA'],true),
     'Hay jurisdicciones aún POR_VERIFICAR: resolver cada relación antes de aprobar.');
   $this->check(in_array($j['cobertura'],['POR_VERIFICAR','PARCIAL','TOTAL'],true),
     'Tipo de cobertura jurisdiccional no reconocido.');
   if($j['estado']==='VALIDADA'){
    $rrhh=$this->one('SELECT GEN_Organismos_Activo AS activo,GEN_Organismos_categ_Id AS categoria FROM rrhh.gen_organismos WHERE GEN_ORGANISMOS_Id=:id', [':id'=>$j['gen_organismos_id']]);
    $this->check((bool)$rrhh && (int)$rrhh['activo']===1 && in_array((int)$rrhh['categoria'],[52,53,54],true),
      'Una dependencia validada ya no está activa o cambió de categoría RRHH: revisar la consolidación.');
   }
  }
  $hs=$this->all('SELECT o.id_barrio_origen,b.nombre,b.id_localidad,b.estado,b.activo FROM db_barrios.bar_consolidaciones_origen o JOIN db_barrios.bar_barrios b ON b.id_barrio=o.id_barrio_origen WHERE o.id_consolidacion=:id ORDER BY o.id_barrio_origen'.($bloquear?' FOR UPDATE':''),[':id'=>$id]);
  $this->check(count($hs)>=2,'Se requieren dos o más barrios históricos.');
  $ids=[];
  foreach($hs as $h){
   $this->check((string)$h['id_localidad']===(string)$c['id_localidad'],'Histórico de otra localidad.');
   $this->check($h['estado']==='IMPORTADO' && (int)$h['activo']===0,'Histórico ya activo o con estado inesperado.');
   $ids[]=(int)$h['id_barrio_origen'];
   $conf=$this->countSql("SELECT COUNT(*) n FROM db_barrios.bar_relaciones_barrios WHERE id_barrio_origen=:id AND estado='APROBADA'",[':id'=>$h['id_barrio_origen']]);
   $this->check($conf===0,'Un histórico ya tiene un gobernante aprobado.');
  }
  $nombre=trim((string)$c['nombre_gobernante']);
  $this->check($nombre!=='' && mb_strlen($nombre)<=200,'Nombre gobernante inválido.');
  $this->check($c['id_barrio_gobernante']===null,'Esta versión permite gobernante nuevo solamente.');
  $conf=$this->countSql('SELECT COUNT(*) n FROM db_barrios.bar_barrios WHERE id_localidad=:loc AND UPPER(TRIM(nombre))=UPPER(:nombre) AND (activo=1 OR estado=\'APROBADO\')', [':loc'=>$c['id_localidad'],':nombre'=>$nombre]);
  $this->check($conf===0,'Ya existe un gobernante activo con ese nombre y localidad.');
  $surc=null;
  if($c['barrio_codigo_surc']!==null){
   $surc=$this->one('SELECT Barrio_Codigo,Barrio_Descrip,Localidad_Codigo FROM dbseg.ref_barrio WHERE Barrio_Codigo=:cod',[':cod'=>$c['barrio_codigo_surc']]);
   $this->check((bool)$surc,'No existe el código SURC seleccionado.');
   // Mapeo comprobado para localidad Salta capital; otras localidades requieren catálogo de equivalencias.
   $this->check((string)$c['id_localidad']==='3266028028050' && (int)$surc['Localidad_Codigo']===8,
      'Equivalencia de localidad SURC no verificada: bloquear aprobación.');
   $conf=$this->countSql('SELECT COUNT(*) n FROM db_barrios.bar_equivalencias_surc WHERE barrio_codigo_surc=:cod',[':cod'=>$c['barrio_codigo_surc']]);
   $this->check($conf===0,'El código SURC ya tiene equivalencia registrada; revisión manual requerida.');
  }
  $municipal=null;
  if($c['id_importacion_municipal']!==null){
   $municipal=$this->one('SELECT id_importacion,id_externo,nombre_original,estado,ST_SRID(geometria) srid,ST_IsEmpty(geometria) vacia,ST_AsGeoJSON(geometria) geojson FROM db_barrios.bar_importacion_geometrias WHERE id_importacion=:id'.($bloquear?' FOR UPDATE':''),[':id'=>$c['id_importacion_municipal']]);
   $this->check((bool)$municipal && (int)$municipal['srid']===4326 && (int)$municipal['vacia']===0,'Geometría municipal inexistente, vacía o con SRID incorrecto.');
   $topo=$this->validarGeometria(json_decode($municipal['geojson'],true,512,JSON_THROW_ON_ERROR));
   $municipal['topologia_basica']=$topo;
  }
  $this->check((bool)$municipal,'Una consolidación oficial necesita polígono municipal revisado.');
  return ['c'=>$c,'hs'=>$hs,'surc'=>$surc,'municipal'=>$municipal,'jurisdicciones'=>$jurisdicciones];
 }
 public function simular(int $id,int $usuario):array {
  $this->check($id>0&&$usuario>0,'Identificadores inválidos.',400);
  $datos=$this->datos($id,false);
  return ['ok'=>true,'id_consolidacion'=>$id,'gobernante'=>$datos['c']['nombre_gobernante'],
    'historicos'=>array_column($datos['hs'],'id_barrio_origen'),'surc'=>$datos['surc']['Barrio_Codigo']??null,
    'municipal'=>$datos['municipal']['id_importacion']??null,
    'jurisdicciones'=>$datos['jurisdicciones'],
    'topologia_basica'=>$datos['municipal']['topologia_basica']??null,
    'advertencia'=>'La comprobación básica NO reemplaza la revisión topológica GIS.'];
 }
 public function aprobar(int $id,int $usuario,string $fundamento):array {
  $fundamento=trim($fundamento);
  $this->check($id>0&&$usuario>0&&mb_strlen($fundamento)>=15&&mb_strlen($fundamento)<=3000,
    'La aprobación exige un fundamento de 15 a 3000 caracteres.',400);
  $lock='territorio_consolidacion_'. $id;
  $l=$this->one('SELECT GET_LOCK(:l,10) AS obtenido',[':l'=>$lock]);
  $this->check((int)($l['obtenido']??0)===1,'La consolidación está siendo procesada por otro administrador.',409);
  try {
   // La conexión BARRIOS y geoterr deben estar en la MISMA instancia InnoDB.
   $this->db->beginTransaction();
   $x=$this->datos($id,true);$c=$x['c'];
   $nombre=trim((string)$c['nombre_gobernante']);
   // Segundo candado por localidad + nombre para bloquear publicaciones simultáneas de otro expediente.
   $nombreLock='terr_gob_'.substr(hash('sha256',(string)$c['id_localidad'].'|'.mb_strtoupper($nombre)),0,40);
   $ll=$this->one('SELECT GET_LOCK(:l,10) AS obtenido',[':l'=>$nombreLock]);
   $this->check((int)($ll['obtenido']??0)===1,'Otro administrador está publicando este barrio.',409);
   try {
    $this->check(count($this->all("SELECT id_barrio FROM db_barrios.bar_barrios WHERE id_localidad=:l AND UPPER(TRIM(nombre))=UPPER(:n) AND (activo=1 OR estado='APROBADO') FOR UPDATE",[':l'=>$c['id_localidad'],':n'=>$nombre]))===0,'Gobernante ya existente.');
    $s=$this->db->prepare("INSERT INTO db_barrios.bar_barrios (id_localidad,nombre,nombre_normalizado,clasificacion,descripcion,estado,activo) VALUES (:loc,:n,:normal,'BARRIO',:d,'APROBADO',1)");
    $s->execute([':loc'=>$c['id_localidad'],':n'=>$nombre,':normal'=>mb_strtoupper($nombre),':d'=>'Consolidación aprobada N.º '.$id]);
    $gob=(int)$this->db->lastInsertId();
    $s=$this->db->prepare("INSERT INTO db_barrios.bar_relaciones_barrios (id_barrio_origen,id_barrio_destino,tipo_relacion,estado,observaciones,usuario_solicitante,usuario_aprobador,fecha_aprobacion) VALUES (:origen,:destino,:tipo,'APROBADA',:obs,:sol,:apr,NOW())");
    foreach($x['hs'] as $h) $s->execute([':origen'=>$h['id_barrio_origen'],':destino'=>$gob,':tipo'=>$c['tipo_relacion'],':obs'=>'Consolidación N.º '.$id,':sol'=>$c['usuario_solicitante'],':apr'=>$usuario]);
    if($x['surc']){
     $s=$this->db->prepare("INSERT INTO db_barrios.bar_equivalencias_surc (id_barrio,barrio_codigo_surc,localidad_codigo_surc,estado,observaciones,usuario_validacion,fecha_validacion) VALUES (:id,:codigo,:local,'VALIDADA',:obs,:u,NOW())");
     $s->execute([':id'=>$gob,':codigo'=>$x['surc']['Barrio_Codigo'],':local'=>$x['surc']['Localidad_Codigo'],':obs'=>'Validado en consolidación N.º '.$id,':u'=>$usuario]);
    }
    $s=$this->db->prepare("INSERT INTO db_barrios.bar_geometrias (id_barrio,version,geometria,origen,estado,observaciones,usuario_creacion,usuario_aprobacion,fecha_aprobacion) SELECT :b,1,geometria,'MUNICIPALIDAD','APROBADO',:obs,:sol,:apr,NOW() FROM db_barrios.bar_importacion_geometrias WHERE id_importacion=:mid");
    $s->execute([':b'=>$gob,':obs'=>'Fuente municipal importación '.$c['id_importacion_municipal'].' · consolidación N.º '.$id,':sol'=>$c['usuario_solicitante'],':apr'=>$usuario,':mid'=>$c['id_importacion_municipal']]);
    $this->check($s->rowCount()===1,'No se pudo publicar la geometría.');
    // Importamos SOLO las jurisdicciones cuya relación fue validada.
    // Una cobertura POR_VERIFICAR no es un mapa de jurisdicción confirmada:
    // es_actual=0 y no se inventa fecha de vigencia. Incluso con PARCIAL/TOTAL,
    // falta definir la capa jurisdiccional para publicar vigencia geográfica.
    $jurPublicadas=[];
    $insertJur=$this->db->prepare("INSERT INTO db_barrios.bar_jurisdicciones
       (id_barrio,gen_organismos_id,origen,fecha_desde,fecha_hasta,es_actual,observaciones,usuario_creacion)
       VALUES (:b,:dep,'MANUAL',NULL,NULL,0,:obs,:usuario)");
    foreach($x['jurisdicciones'] as $j){
     if($j['estado']!=='VALIDADA')continue;
     $obs='Relación validada en consolidación N.º '.$id.'; cobertura: '.$j['cobertura'].
          '; VIGENCIA TERRITORIAL PENDIENTE DE VERIFICAR';
     if(trim((string)($j['observaciones']??''))!=='')$obs.='; nota: '.$j['observaciones'];
     $insertJur->execute([':b'=>$gob,':dep'=>$j['gen_organismos_id'],':obs'=>$obs,':usuario'=>$usuario]);
     $this->check($insertJur->rowCount()===1,'No se pudo registrar una relación jurisdiccional.');
     $jurPublicadas[]=['id_dependencia'=>(int)$j['gen_organismos_id'],
       'cobertura'=>$j['cobertura'],'es_actual'=>0,'id_jurisdiccion'=>(int)$this->db->lastInsertId()];
    }
    $s=$this->db->prepare("UPDATE db_barrios.bar_consolidaciones SET estado='APROBADA',id_barrio_gobernante=:b,usuario_aprobador=:u,fecha_resolucion=NOW(),observaciones_resolucion=:obs WHERE id_consolidacion=:id AND estado='PENDIENTE'");
    $s->execute([':b'=>$gob,':u'=>$usuario,':obs'=>$fundamento,':id'=>$id]);
    $this->check($s->rowCount()===1,'La consolidación cambió de estado durante la publicación.');
    $s=$this->db->prepare("INSERT INTO db_barrios.bar_consolidaciones_eventos (id_consolidacion,accion,usuario_id,detalle) VALUES (:id,'APROBACION',:u,:d)");
    $s->execute([':id'=>$id,':u'=>$usuario,':d'=>$fundamento]);
    // La auditoría ES parte de esta transacción. Falla cerrada si no existe la tabla/campos.
    $antes=json_encode(['consolidacion'=>$id,'estado'=>'PENDIENTE','historicos'=>array_column($x['hs'],'id_barrio_origen')],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $despues=json_encode(['gobernante'=>$gob,'estado'=>'APROBADA','codigo_surc'=>$x['surc']['Barrio_Codigo']??null,'municipal'=>$c['id_importacion_municipal'],'jurisdicciones_validadas'=>$jurPublicadas,'jurisdicciones_descartadas'=>array_values(array_map(static fn($j)=>(int)$j['gen_organismos_id'],array_filter($x['jurisdicciones'],static fn($j)=>$j['estado']==='DESCARTADA'))),'solicitante'=>$c['usuario_solicitante'],'aprobador'=>$usuario,'autoaprobacion'=>(int)$c['usuario_solicitante']===$usuario],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $s=$this->db->prepare('INSERT INTO geoterr.geo_auditoria (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent) VALUES (:mod,:ent,:id,:accion,:usuario,:descr,:antes,:despues,:ip,:agente)');
    $s->execute([':mod'=>'BARRIOS',':ent'=>'CONSOLIDACION',':id'=>$id,':accion'=>'APROBACION_CONSOLIDACION',':usuario'=>$usuario,':descr'=>$fundamento,':antes'=>$antes,':despues'=>$despues,':ip'=>$_SERVER['REMOTE_ADDR']??null,':agente'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    $this->db->commit();
    return ['ok'=>true,'id_consolidacion'=>$id,'id_barrio_gobernante'=>$gob,'estado'=>'APROBADA','jurisdicciones_registradas'=>count($jurPublicadas),'jurisdicciones_actuales_confirmadas'=>0];
   }
  catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
  finally {
   if(isset($nombreLock)&&isset($ll)&&(int)($ll['obtenido']??0)===1) $this->one('SELECT RELEASE_LOCK(:l) AS liberado',[':l'=>$nombreLock]);
   $this->one('SELECT RELEASE_LOCK(:l) AS liberado',[':l'=>$lock]);
  }
 }
}
