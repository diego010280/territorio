<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
final class RevisionSolicitudesBarrios {
    private PDO $db;
    public function __construct(){ $this->db=Conexion::getConexion('BARRIOS'); $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    public function listar(string $estado):array {
        if(!in_array($estado,['PENDIENTE','APROBADA','RECHAZADA','CANCELADA','TODAS'],true)) throw new InvalidArgumentException('Estado inválido.',400);
        $sql="SELECT s.id_solicitud,s.id_barrio,s.tipo_solicitud,s.estado,s.version_base,
           s.fecha_presentacion,b.nombre AS nombre_actual,l.localidad_nombre
           FROM db_barrios.bar_solicitudes s
           LEFT JOIN db_barrios.bar_barrios b ON b.id_barrio=s.id_barrio
           LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad";
        if($estado!=='TODAS')$sql.=' WHERE s.estado=:estado';
        $sql.=' ORDER BY s.id_solicitud DESC LIMIT 250';
        $q=$this->db->prepare($sql); if($estado!=='TODAS')$q->bindValue(':estado',$estado);$q->execute();
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function detalle(int $id):array {
        if($id<1)throw new InvalidArgumentException('Identificador inválido.',400);
        $q=$this->db->prepare("SELECT s.*,b.nombre AS nombre_actual,b.id_localidad AS localidad_actual,
                 b.clasificacion AS clasificacion_actual,b.descripcion AS descripcion_actual,
                 b.estado AS estado_barrio,b.activo AS barrio_activo
                 FROM db_barrios.bar_solicitudes s
                 LEFT JOIN db_barrios.bar_barrios b ON b.id_barrio=s.id_barrio
                 WHERE s.id_solicitud=:id LIMIT 1");
        $q->execute([':id'=>$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r) throw new RuntimeException('Solicitud inexistente.',404);
        $datos=json_decode((string)($r['datos_propuestos']??'{}'),true);
        if(!is_array($datos))throw new RuntimeException('Los datos propuestos no tienen formato JSON válido.',409);
        $r['propuestos']=$datos;unset($r['datos_propuestos']);
        $q=$this->db->prepare("SELECT id_geometria,version,origen,ST_SRID(geometria) AS srid,
                    ST_AsGeoJSON(geometria) AS geojson
                 FROM db_barrios.bar_geometrias WHERE id_barrio=:b AND estado='APROBADO'
                 ORDER BY version DESC,id_geometria DESC LIMIT 1");
        $q->execute([':b'=>(int)$r['id_barrio']]);$vig=$q->fetch(PDO::FETCH_ASSOC);
        $r['geometria_vigente']=$vig?[
           'id_geometria'=>$vig['id_geometria'],'version'=>$vig['version'],
           'origen'=>$vig['origen'],'srid'=>(int)$vig['srid'],
           'geometry'=>json_decode((string)$vig['geojson'],true,512,JSON_THROW_ON_ERROR)]:null;
        $q=$this->db->prepare("SELECT id_solicitud_geometria,origen,ST_SRID(geometria) AS srid,
                          ST_AsGeoJSON(geometria) AS geojson
                 FROM db_barrios.bar_solicitudes_geometrias WHERE id_solicitud=:id LIMIT 1");
        $q->execute([':id'=>$id]);$prop=$q->fetch(PDO::FETCH_ASSOC);
        $r['geometria_propuesta']=$prop?[
            'id_solicitud_geometria'=>$prop['id_solicitud_geometria'],
            'origen'=>$prop['origen'],'srid'=>(int)$prop['srid'],
            'geometry'=>json_decode((string)$prop['geojson'],true,512,JSON_THROW_ON_ERROR)]:null;
        $versionActual=$vig?(int)$vig['version']:0;
        $r['controles']=[
            'version_base_vigente'=> $r['tipo_solicitud']==='ALTA' || (int)$r['version_base']===$versionActual,
            'historico_subordinado'=>false,
            'geometria_srid_4326'=>!$prop || (int)$prop['srid']===4326,
            'requiere_revision_topologica'=>!!$prop,
            'publicacion_habilitada'=>false
        ];
        if($r['id_barrio']){
            $q=$this->db->prepare("SELECT COUNT(*) FROM db_barrios.bar_relaciones_barrios WHERE id_barrio_origen=:b AND estado='APROBADA'");
            $q->execute([':b'=>$r['id_barrio']]);
            $r['controles']['historico_subordinado']=(int)$q->fetchColumn()>0;
        }
        return $r;
    }
}
