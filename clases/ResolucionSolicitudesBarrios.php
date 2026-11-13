<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/AuditoriaTerritorio.php';
require_once __DIR__.'/ValidadorGeometriaTerritorio.php';
/** Revisión transaccional. No publica una topología modificada sin validación GIS fiable. */
final class ResolucionSolicitudesBarrios {
    private PDO $db;
    public function __construct() {
        $this->db=Conexion::getConexion('BARRIOS');
        $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    private function uno(string $sql,array $args=[]):?array {
        $s=$this->db->prepare($sql);$s->execute($args);$v=$s->fetch(PDO::FETCH_ASSOC);
        return $v?:null;
    }
    public function resolver(int $id,string $decision,string $motivo,int $usuario,bool $confirmacionGeometrica=false):array {
        $motivo=trim($motivo);
        if($id<=0||$usuario<=0||!in_array($decision,['APROBADA','RECHAZADA'],true))throw new InvalidArgumentException('Resolución inválida.',400);
        if(mb_strlen($motivo)<10||mb_strlen($motivo)>2000)throw new InvalidArgumentException('Ingresá un fundamento entre 10 y 2000 caracteres.',400);
        $this->db->beginTransaction();
        try {
            $s=$this->uno('SELECT * FROM db_barrios.bar_solicitudes WHERE id_solicitud=:id FOR UPDATE',[':id'=>$id]);
            if(!$s)throw new RuntimeException('Solicitud inexistente.',404);
            if($s['estado']!=='PENDIENTE')throw new RuntimeException('La solicitud ya no está pendiente.',409);
            // Evita resoluciones paralelas. Debe existir el esquema SQL de esta entrega.
            if($this->uno('SELECT id_resolucion FROM db_barrios.bar_solicitudes_resoluciones WHERE id_solicitud=:id',[':id'=>$id]))
                throw new RuntimeException('La solicitud ya fue resuelta.',409);
            $correlacion=AuditoriaTerritorio::correlacion();
            $aud=new AuditoriaTerritorio($this->db);
            $barrioId=(int)($s['id_barrio']??0);
            $barrio=null;$geo=null;$propuesta=null;$geomCambiada=false;$nuevaVersion=null;$idGeomNueva=null;$reporteGis=null;
            if($barrioId>0) {
                $barrio=$this->uno('SELECT * FROM db_barrios.bar_barrios WHERE id_barrio=:id FOR UPDATE',[':id'=>$barrioId]);
                if(!$barrio)throw new RuntimeException('El barrio asociado ya no existe.',409);
                if($this->uno("SELECT id_relacion FROM db_barrios.bar_relaciones_barrios WHERE id_barrio_origen=:id AND estado='APROBADA' LIMIT 1",[':id'=>$barrioId]))
                    throw new RuntimeException('La solicitud corresponde a un histórico subordinado.',409);
                $geo=$this->uno("SELECT id_geometria,version,ST_SRID(geometria) AS srid,SHA2(ST_AsBinary(geometria),256) AS huella FROM db_barrios.bar_geometrias WHERE id_barrio=:id AND estado='APROBADO' ORDER BY version DESC,id_geometria DESC LIMIT 1 FOR UPDATE",[':id'=>$barrioId]);
            }
            $propuesta=$this->uno('SELECT id_solicitud_geometria,origen,ST_SRID(geometria) AS srid,SHA2(ST_AsBinary(geometria),256) AS huella,ST_AsGeoJSON(geometria) AS geojson FROM db_barrios.bar_solicitudes_geometrias WHERE id_solicitud=:id LIMIT 1 FOR UPDATE',[':id'=>$id]);
            // Comparamos huellas WKB exactas: no asumimos igualdad topológica de anillos reordenados.
            $geomCambiada=$propuesta!==null && ($geo===null || !hash_equals((string)$geo['huella'],(string)$propuesta['huella']));
            $versionActual=$geo?(int)$geo['version']:0;
            if($decision==='APROBADA'&&$geo){
                $c=$this->uno("SELECT COUNT(*) AS cantidad FROM db_barrios.bar_geometrias WHERE id_barrio=:id AND estado='APROBADO'",[':id'=>$barrioId]);
                if((int)$c['cantidad']!==1)throw new RuntimeException('Se requiere exactamente una geometría oficial vigente.',409);
                if((int)$geo['srid']!==4326)throw new RuntimeException('La geometría oficial no está en SRID 4326.',409);
            }
            if($decision==='APROBADA') {
                if(!in_array($s['tipo_solicitud'],['MODIFICACION','CAMBIO_GEOMETRIA'],true))
                    throw new RuntimeException('Esta entrega solo publica MODIFICACION/CAMBIO_GEOMETRIA sobre barrios existentes. Alta/baja/jurisdicción requieren procedimiento específico.',409);
                if(!$barrio||$barrio['estado']!=='APROBADO'||(int)$barrio['activo']!==1)
                    throw new RuntimeException('El barrio oficial no está aprobado y activo.',409);
                if((int)$s['version_base']!==$versionActual)
                    throw new RuntimeException('Cambió la versión oficial; revisá nuevamente la solicitud.',409);
                if($propuesta&&(int)$propuesta['srid']!==4326)
                    throw new RuntimeException('La geometría propuesta no tiene SRID 4326.',409);
                if($geomCambiada){
                    if(!$confirmacionGeometrica)throw new RuntimeException('La propuesta modifica los límites. Confirmá expresamente la revisión cartográfica y el fundamento para publicar una nueva versión.',409);
                    if(!$geo||!$propuesta)throw new RuntimeException('Se necesita geometría oficial y propuesta para cambiar límites.',409);
                    // Se revalida SIEMPRE dentro de la transacción, nunca confiar en el navegador.
                    $reporteGis=ValidadorGeometriaTerritorio::validar((string)$propuesta['geojson']);
                    if(!$this->uno("SELECT id_geometria FROM db_barrios.bar_geometrias WHERE id_barrio=:b AND estado='APROBADO' AND id_geometria=:g",
                        [':b'=>$barrioId,':g'=>$geo['id_geometria']]))throw new RuntimeException('Cambió la geometría oficial durante la revisión.',409);
                    // Control espacial conservador: se bloquea ante cualquier superficie de
                    // intersección positiva con otro barrio oficialmente publicado.
                    // ST_Intersection opera en coordenadas del SRID 4326: el AREA se usa
                    // SOLO para detectar >0, NO para reportar metros cuadrados.
                    $q=$this->db->prepare("SELECT og.id_barrio,og.id_geometria,
                        ST_Area(ST_Intersection(og.geometria,pg.geometria)) AS area_solapada
                        FROM db_barrios.bar_geometrias og
                        INNER JOIN db_barrios.bar_barrios ob ON ob.id_barrio=og.id_barrio
                        CROSS JOIN db_barrios.bar_solicitudes_geometrias pg
                        WHERE pg.id_solicitud=:sol AND og.estado='APROBADO'
                          AND og.id_barrio<>:barrio AND ob.estado='APROBADO' AND ob.activo=1
                          AND MBRIntersects(og.geometria,pg.geometria)");
                    $q->execute([':sol'=>$id,':barrio'=>$barrioId]);
                    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $vecino){
                        if($vecino['area_solapada']===null)throw new RuntimeException('No pudo calcularse intersección con otro barrio aprobado.',409);
                        if((float)$vecino['area_solapada']>0.0)
                            throw new RuntimeException('La propuesta se superpone con el barrio oficial #'.$vecino['id_barrio'].'. Revisar límites antes de publicar.',409);
                    }
                    $vmax=$this->uno('SELECT MAX(version) AS maxima FROM db_barrios.bar_geometrias WHERE id_barrio=:b',[':b'=>$barrioId]);
                    $nuevaVersion=max($versionActual,(int)($vmax['maxima']??0))+1;
                    // La transacción y el bloqueo del barrio impiden la doble publicación
                    // concurrente. Conservar versión previa: nunca borrar ni sobrescribir WKB.
                    $u=$this->db->prepare("UPDATE db_barrios.bar_geometrias SET estado='HISTORICO'
                        WHERE id_geometria=:g AND id_barrio=:b AND estado='APROBADO'");
                    $u->execute([':g'=>$geo['id_geometria'],':b'=>$barrioId]);
                    if($u->rowCount()!==1)throw new RuntimeException('No se pudo historizar la geometría vigente.',409);
                    $ins=$this->db->prepare("INSERT INTO db_barrios.bar_geometrias
                       (id_barrio,version,geometria,origen,estado,observaciones,usuario_creacion,usuario_aprobacion,fecha_aprobacion)
                       SELECT :b,:v,geometria,origen,'APROBADO',:obs,:usu_crea,:usu_aprueba,NOW()
                       FROM db_barrios.bar_solicitudes_geometrias WHERE id_solicitud=:sol");
                    $ins->execute([':b'=>$barrioId,':v'=>$nuevaVersion,':obs'=>'Publicada desde solicitud #'.$id.' — '.$motivo,
                        ':usu_crea'=>$usuario,':usu_aprueba'=>$usuario,':sol'=>$id]);
                    if($ins->rowCount()!==1)throw new RuntimeException('No se pudo publicar la nueva geometría.',409);
                    $idGeomNueva=(int)$this->db->lastInsertId();
                    $aud->registrarABM([
                        'usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::GEOMETRIA,
                        'tabla'=>'db_barrios.bar_geometrias','registro'=>(string)$idGeomNueva,
                        'correlacion'=>$correlacion,
                        'antes'=>['id_geometria'=>$geo['id_geometria'],'version'=>$versionActual,'estado'=>'APROBADO','hash_wkb'=>$geo['huella']],
                        'despues'=>['id_geometria'=>$idGeomNueva,'version'=>$nuevaVersion,'estado'=>'APROBADO',
                            'hash_wkb'=>$propuesta['huella'],'validacion'=>$reporteGis,'id_solicitud'=>$id],
                        'observacion'=>'Publicación geométrica aprobada, solicitud #'.$id
                    ]);
                }
                $datos=json_decode((string)($s['datos_propuestos']??''),true);
                if(!is_array($datos))throw new RuntimeException('Los datos propuestos no son JSON válido.',409);
                // Aplicar exclusivamente columnas editables: nunca cambiar referencias SURC/RRHH ni color.
                $permitidas=['nombre','id_localidad','clasificacion','descripcion'];
                $actual=[];$nuevo=[];
                foreach($permitidas as $campo){
                    if(!array_key_exists($campo,$datos))continue;
                    $valor=$datos[$campo];
                    if($campo==='id_localidad'){
                        if(!is_numeric($valor)||(int)$valor<=0)throw new RuntimeException('Localidad propuesta inválida.',409);
                        $valor=(int)$valor;
                        if(!$this->uno('SELECT localidad_id FROM db_gral.localidades WHERE localidad_id=:id LIMIT 1',[':id'=>$valor]))
                            throw new RuntimeException('La localidad propuesta ya no existe.',409);
                    }else{
                        if(!is_string($valor))throw new RuntimeException('Dato propuesto inválido: '.$campo,409);
                        $valor=trim($valor);
                        if($campo==='nombre'&&($valor===''||mb_strlen($valor)>200))throw new RuntimeException('Nombre propuesto inválido.',409);
                        if($campo==='clasificacion'){
                            if(mb_strlen($valor)>50)throw new RuntimeException('Clasificación inválida.',409);
                            if($valor!==''&&!$this->uno('SELECT codigo FROM db_barrios.bar_clasificaciones WHERE codigo=:c AND activo=1 LIMIT 1',[':c'=>$valor]))
                                throw new RuntimeException('La clasificación no está habilitada.',409);
                        }
                        if($campo==='descripcion'&&mb_strlen($valor)>5000)throw new RuntimeException('Descripción demasiado extensa.',409);
                    }
                    $actual[$campo]=$barrio[$campo]??null;
                    $nuevo[$campo]=$valor;
                }
                if(!$nuevo && !$geomCambiada)throw new RuntimeException('Solicitud sin campos modificables ni cambio geométrico.',409);
                $cambios=[];$param=[':id'=>$barrioId];
                foreach($nuevo as $k=>$v){
                    // Null/'' se consideran distintos, para conservar la intención explícita.
                    if((string)($barrio[$k]??'')===(string)$v)continue;
                    $cambios[]="`$k`=:$k";$param[":$k"]=$v;
                }
                if($cambios){
                    // La normalización del nombre debe seguir el modelo vigente si se cambia.
                    if(array_key_exists('nombre',$nuevo)&&isset($param[':nombre'])){
                        $cambios[]='nombre_normalizado=:normalizado';
                        $param[':normalizado']=mb_strtoupper(trim(preg_replace('/\s+/u',' ',$nuevo['nombre'])),'UTF-8');
                    }
                    $cambios[]='fecha_modificacion=NOW()';
                    $stmt=$this->db->prepare('UPDATE db_barrios.bar_barrios SET '.implode(',',$cambios).' WHERE id_barrio=:id');
                    $stmt->execute($param);
                    $aud->registrarABM([
                        'usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::MODIFICACION,
                        'tabla'=>'db_barrios.bar_barrios','registro'=>(string)$barrioId,
                        'correlacion'=>$correlacion,'antes'=>array_intersect_key($actual,$nuevo),
                        'despues'=>array_intersect_key($nuevo,$actual),
                        'observacion'=>'Publicación de datos aprobados en solicitud #'.$id
                    ]);
                }
            }
            $stmt=$this->db->prepare('UPDATE db_barrios.bar_solicitudes SET estado=:estado WHERE id_solicitud=:id AND estado=\'PENDIENTE\'');
            $stmt->execute([':estado'=>$decision,':id'=>$id]);
            if($stmt->rowCount()!==1)throw new RuntimeException('No se pudo resolver la solicitud por concurrencia.',409);
            $stmt=$this->db->prepare('INSERT INTO db_barrios.bar_solicitudes_resoluciones (id_solicitud,decision,motivo,usuario_resolutor,correlacion_id,version_oficial_base,hubo_cambio_geometria) VALUES (:id,:decision,:motivo,:usuario,:corr,:ver,:geo)');
            $stmt->execute([':id'=>$id,':decision'=>$decision,':motivo'=>$motivo,':usuario'=>$usuario,':corr'=>$correlacion,':ver'=>$versionActual,':geo'=>$geomCambiada?1:0]);
            $aud->registrarABM([
                'usuario'=>$usuario,'tipo'=>$decision==='APROBADA'?AuditoriaTerritorio::APROBACION:AuditoriaTerritorio::RECHAZO,
                'tabla'=>'db_barrios.bar_solicitudes','registro'=>(string)$id,'correlacion'=>$correlacion,
                'antes'=>['estado'=>'PENDIENTE','id_barrio'=>$barrioId?:null,'version_base'=>$s['version_base']],
                'despues'=>['estado'=>$decision,'id_barrio'=>$barrioId?:null,'fundamento'=>$motivo,
                    'version_oficial'=>$nuevaVersion??$versionActual,'geometria_diferente'=>$geomCambiada,'id_geometria_publicada'=>$idGeomNueva],
                'observacion'=>($decision==='APROBADA'?'Aprobación':'Rechazo').' solicitud #'.$id
            ]);
            // Compatibilidad con auditoría heredada, en la misma transacción.
            $old=$this->db->prepare('INSERT INTO geoterr.geo_auditoria (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent) VALUES (\'TERRITORIO\',\'SOLICITUD_BARRIO\',:id,:accion,:usuario,:motivo,:antes,:despues,:ip,:agente)');
            $old->execute([
                ':id'=>$id,':accion'=>$decision==='APROBADA'?'APROBAR_SOLICITUD':'RECHAZAR_SOLICITUD',':usuario'=>$usuario,':motivo'=>$motivo,
                ':antes'=>json_encode(['estado'=>'PENDIENTE'],JSON_THROW_ON_ERROR),
                ':despues'=>json_encode(['estado'=>$decision,'id_barrio'=>$barrioId?:null],JSON_THROW_ON_ERROR),
                ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),':agente'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)
            ]);
            $this->db->commit();
            return ['id_solicitud'=>$id,'estado'=>$decision,'id_barrio'=>$barrioId?:null,'version_oficial'=>$nuevaVersion??$versionActual,'geometria_publicada'=>$idGeomNueva!==null,'id_geometria_nueva'=>$idGeomNueva];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
