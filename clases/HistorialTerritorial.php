<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
/** Consultas historicas estrictamente de solo lectura. */
final class HistorialTerritorial {
    private PDO $pdo;
    public function __construct(){
        $this->pdo=Conexion::getConexion('BARRIOS');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function listar(int $id): array {
        $q=$this->pdo->prepare('SELECT id_barrio,nombre,estado FROM db_barrios.bar_barrios WHERE id_barrio=:id LIMIT 1');
        $q->execute([':id'=>$id]);$b=$q->fetch(PDO::FETCH_ASSOC);
        if(!$b)throw new RuntimeException('Barrio inexistente',404);
        $q=$this->pdo->prepare('SELECT id_geometria,version,origen,estado,observaciones,usuario_creacion,fecha_creacion,usuario_aprobacion,fecha_aprobacion,ST_SRID(geometria) AS srid FROM db_barrios.bar_geometrias WHERE id_barrio=:id ORDER BY version DESC,id_geometria DESC');
        $q->execute([':id'=>$id]);$geos=$q->fetchAll(PDO::FETCH_ASSOC);
        // Se relacionan resoluciones por id de barrio; NO se presupone que cada
        // resolucion creó una versión nueva. Eso se determina por el evento ABM.
        $q=$this->pdo->prepare('SELECT s.id_solicitud,s.tipo_solicitud,s.estado AS estado_solicitud,s.version_base,r.decision,r.motivo,r.usuario_resolutor,r.fecha_resolucion,r.correlacion_id,r.hubo_cambio_geometria FROM db_barrios.bar_solicitudes s INNER JOIN db_barrios.bar_solicitudes_resoluciones r ON r.id_solicitud=s.id_solicitud WHERE s.id_barrio=:id ORDER BY r.fecha_resolucion DESC,r.id_resolucion DESC LIMIT 100');
        $q->execute([':id'=>$id]);$res=$q->fetchAll(PDO::FETCH_ASSOC);
        return ['barrio'=>$b,'geometrias'=>$geos,'resoluciones'=>$res];
    }
    public function geometria(int $barrio,int $idGeom):array {
        $q=$this->pdo->prepare('SELECT id_geometria,id_barrio,version,estado,origen,ST_SRID(geometria) AS srid,ST_AsGeoJSON(geometria) AS geojson FROM db_barrios.bar_geometrias WHERE id_barrio=:b AND id_geometria=:g LIMIT 1');
        $q->execute([':b'=>$barrio,':g'=>$idGeom]);$g=$q->fetch(PDO::FETCH_ASSOC);
        if(!$g)throw new RuntimeException('Versión geométrica no encontrada para ese barrio',404);
        if((int)$g['srid']!==4326)throw new RuntimeException('Geometría histórica con SRID inesperado',409);
        $geo=json_decode((string)$g['geojson'],true,512,JSON_THROW_ON_ERROR);
        if(!is_array($geo)||($geo['type']??'')!=='MultiPolygon')throw new RuntimeException('Formato geométrico no soportado',409);
        unset($g['geojson']);$g['geometry']=$geo;
        return $g;
    }
}
