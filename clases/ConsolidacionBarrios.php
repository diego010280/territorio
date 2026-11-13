<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
final class ConsolidacionBarrios {
 private PDO $db;
 public function __construct() { $this->db=Conexion::getConexion('BARRIOS'); }
 public function localidades(string $q):array {
  $st=$this->db->prepare('SELECT localidad_id AS id,localidad_nombre AS nombre,departamento_id,municipio_id FROM db_gral.localidades WHERE localidad_nombre LIKE :q ORDER BY localidad_nombre,localidad_id LIMIT 25');
  $st->execute([':q'=>'%'.$q.'%']);return $st->fetchAll(PDO::FETCH_ASSOC);
 }
 public function barrios(int $loc,string $q):array {
  $st=$this->db->prepare('SELECT id_barrio,nombre,id_origen_dbgral,estado FROM db_barrios.bar_barrios WHERE id_localidad=:loc AND nombre LIKE :q ORDER BY nombre,id_barrio LIMIT 40');
  $st->bindValue(':loc',$loc,PDO::PARAM_INT);$st->bindValue(':q','%'.$q.'%');$st->execute();return $st->fetchAll(PDO::FETCH_ASSOC);
 }
 public function municipales(string $q):array {
  $st=$this->db->prepare('SELECT id_importacion,id_externo,nombre_original,estado FROM db_barrios.bar_importacion_geometrias WHERE nombre_original LIKE :q ORDER BY nombre_original LIMIT 30');
  $st->execute([':q'=>'%'.$q.'%']);return $st->fetchAll(PDO::FETCH_ASSOC);
 }
 /**
  * Buscador por nombre del catálogo SURC. La localidad 8 se utiliza
  * únicamente cuando la localidad territorial es SALTA (mapeo comprobado).
  * En otras localidades NO se presupone equivalencia entre catálogos.
  */
 public function buscarSurc(string $q,int $idLocalidad):array {
  $q=trim($q);
  if(mb_strlen($q)<2 || mb_strlen($q)>100)throw new InvalidArgumentException('Escribí entre 2 y 100 caracteres.',400);
  if($idLocalidad<1)throw new InvalidArgumentException('Seleccioná una localidad territorial.',400);
  $loc=$this->db->prepare('SELECT localidad_id FROM db_gral.localidades WHERE localidad_id=:id LIMIT 1');
  $loc->execute([':id'=>$idLocalidad]);
  if(!$loc->fetchColumn())throw new InvalidArgumentException('Localidad territorial inexistente.',400);
  $saltaCapital=$idLocalidad===3266028028050;
  $sql='SELECT s.Barrio_Codigo,s.Barrio_Descrip,s.Localidad_Codigo, e.id_barrio AS asociado,
             e.estado AS estado_asociacion
        FROM dbseg.ref_barrio s
        LEFT JOIN db_barrios.bar_equivalencias_surc e
             ON e.barrio_codigo_surc=s.Barrio_Codigo
        WHERE s.Barrio_Descrip LIKE :q';
  if($saltaCapital)$sql.=' AND s.Localidad_Codigo=8';
  $sql.=' ORDER BY s.Barrio_Descrip,s.Localidad_Codigo,s.Barrio_Codigo LIMIT 35';
  $st=$this->db->prepare($sql);
  $st->bindValue(':q','%'.$q.'%',PDO::PARAM_STR);$st->execute();
  return ['data'=>$st->fetchAll(PDO::FETCH_ASSOC),
    'localidad_surc_verificada'=>$saltaCapital ? 8 : null,
    'advertencia'=>$saltaCapital ? null : 'No existe una equivalencia de localidad SURC confirmada para la localidad elegida. Revisar manualmente antes de seleccionar.'];
 }
 public function surc(int $codigo):?array {
  $st=$this->db->prepare('SELECT s.Barrio_Codigo,s.Barrio_Descrip,s.Localidad_Codigo,e.id_barrio AS asociado,e.estado AS estado_asociacion FROM dbseg.ref_barrio s LEFT JOIN db_barrios.bar_equivalencias_surc e ON e.barrio_codigo_surc=s.Barrio_Codigo WHERE s.Barrio_Codigo=:id LIMIT 1');
  $st->execute([':id'=>$codigo]);return $st->fetch(PDO::FETCH_ASSOC) ?: null;
 }
 public function guardar(array $d,int $usuario):int {
  $nombre=trim((string)($d['nombre']??''));$loc=filter_var($d['id_localidad']??null,FILTER_VALIDATE_INT);
  $tipo=(string)($d['tipo']??'');$motivo=trim((string)($d['motivo']??''));
  $ids=$d['origenes']??[];
  if ($usuario<1 || mb_strlen($nombre)<3 || mb_strlen($nombre)>200 || !$loc || $loc<1 || mb_strlen($motivo)<5 || mb_strlen($motivo)>4000 || !in_array($tipo,['SECTOR','DUPLICADO','ABSORBIDO'],true) || !is_array($ids)) throw new InvalidArgumentException('Revisá nombre, localidad, tipo de relación y fundamento.',400);
  $ids=array_values(array_unique(array_map('intval',$ids)));
  if (count($ids)<2 || count($ids)>30 || min($ids)<1) throw new InvalidArgumentException('Seleccioná entre 2 y 30 barrios históricos.',400);
  $gob=isset($d['id_gobernante']) && $d['id_gobernante']!=='' ? filter_var($d['id_gobernante'],FILTER_VALIDATE_INT):null;
  if($gob!==null && (!$gob || in_array((int)$gob,$ids,true))) throw new InvalidArgumentException('El gobernante existente no puede ser uno de los históricos seleccionados.',400);
  $muni=isset($d['id_municipal']) && $d['id_municipal']!=='' ? filter_var($d['id_municipal'],FILTER_VALIDATE_INT):null;
  $surc=isset($d['codigo_surc']) && $d['codigo_surc']!=='' ? filter_var($d['codigo_surc'],FILTER_VALIDATE_INT):null;
  if(($muni!==null && (!$muni || $muni<1))||($surc!==null && (!$surc || $surc<1)))throw new InvalidArgumentException('Código municipal o SURC inválido.',400);
  $this->db->beginTransaction();
  try {
   $st=$this->db->prepare('SELECT localidad_id FROM db_gral.localidades WHERE localidad_id=:loc');$st->execute([':loc'=>$loc]);if(!$st->fetchColumn())throw new InvalidArgumentException('Localidad inexistente.',400);
   $marcas=implode(',',array_fill(0,count($ids),'?'));
   $st=$this->db->prepare("SELECT id_barrio,nombre FROM db_barrios.bar_barrios WHERE id_localidad=? AND id_barrio IN ($marcas) FOR UPDATE");
   $st->execute(array_merge([$loc],$ids));$rows=$st->fetchAll(PDO::FETCH_ASSOC);
   if(count($rows)!==count($ids))throw new InvalidArgumentException('Los barrios deben pertenecer a la localidad indicada.',400);
   $st=$this->db->prepare("SELECT COUNT(*) FROM db_barrios.bar_relaciones_barrios WHERE estado='APROBADA' AND id_barrio_origen IN ($marcas)");$st->execute($ids);
   if((int)$st->fetchColumn()>0)throw new InvalidArgumentException('Algún histórico ya tiene un gobernante aprobado.',400);
   $st=$this->db->prepare("SELECT COUNT(*) FROM db_barrios.bar_consolidaciones_origen o JOIN db_barrios.bar_consolidaciones c ON c.id_consolidacion=o.id_consolidacion WHERE c.estado='PENDIENTE' AND o.id_barrio_origen IN ($marcas)");$st->execute($ids);
   if((int)$st->fetchColumn()>0)throw new InvalidArgumentException('Algún histórico ya está incluido en otra consolidación pendiente.',400);
   if ($gob!==null) { $st=$this->db->prepare('SELECT COUNT(*) FROM db_barrios.bar_barrios WHERE id_barrio=:id AND id_localidad=:loc');$st->execute([':id'=>$gob,':loc'=>$loc]);if(!$st->fetchColumn())throw new InvalidArgumentException('El gobernante no pertenece a esta localidad.',400); }
   if ($muni!==null) {$st=$this->db->prepare('SELECT COUNT(*) FROM db_barrios.bar_importacion_geometrias WHERE id_importacion=:id');$st->execute([':id'=>$muni]);if(!$st->fetchColumn())throw new InvalidArgumentException('Geometría municipal inexistente.',400);}
   if ($surc!==null) {$s=$this->surc((int)$surc);if(!$s)throw new InvalidArgumentException('Código SURC inexistente.',400);if((int)$loc===3266028028050 && (int)$s['Localidad_Codigo']!==8)throw new InvalidArgumentException('Para SALTA, el barrio SURC debe corresponder a la localidad SURC 8.',400);if($s['asociado']!==null && $s['estado_asociacion']==='VALIDADA' && (int)$s['asociado']!==(int)$gob)throw new InvalidArgumentException('El código SURC ya está validado para otro barrio.',400);}
   $st=$this->db->prepare('INSERT INTO db_barrios.bar_consolidaciones (nombre_gobernante,id_localidad,id_barrio_gobernante,id_importacion_municipal,barrio_codigo_surc,tipo_relacion,motivo,usuario_solicitante) VALUES (:n,:l,:g,:m,:s,:t,:o,:u)');
   $st->execute([':n'=>$nombre,':l'=>$loc,':g'=>$gob,':m'=>$muni,':s'=>$surc,':t'=>$tipo,':o'=>$motivo,':u'=>$usuario]);$id=(int)$this->db->lastInsertId();
   $st=$this->db->prepare('INSERT INTO db_barrios.bar_consolidaciones_origen (id_consolidacion,id_barrio_origen,nombre_origen_snapshot) VALUES (?,?,?)');
   foreach($rows as $r)$st->execute([$id,$r['id_barrio'],$r['nombre']]);
   $this->db->commit();return $id;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
