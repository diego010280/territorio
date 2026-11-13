<?php
declare(strict_types=1);
/** Capa de barrios aprobados, independiente de TerritorioConsulta (municipal). */
final class VisorOficial {
    public function __construct(private PDO $db) {}

    private function tieneColor(): bool {
        $s=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='db_barrios' AND TABLE_NAME='bar_barrios' AND COLUMN_NAME='color_mapa'");
        return (int)$s->fetchColumn() > 0;
    }

    public function cartografia(): array {
        $colorSql=$this->tieneColor() ? 'b.color_mapa' : 'NULL AS color_mapa';
        $sql="SELECT b.id_barrio,b.nombre,b.clasificacion,b.id_localidad,{$colorSql},
                  g.id_geometria,g.version,g.origen,ST_AsGeoJSON(g.geometria) AS geojson,
                  (SELECT GROUP_CONCAT(CONCAT(e.barrio_codigo_surc) ORDER BY e.barrio_codigo_surc SEPARATOR ', ')
                   FROM db_barrios.bar_equivalencias_surc e WHERE e.id_barrio=b.id_barrio AND e.estado='VALIDADA') AS surc,
                  (SELECT COUNT(*) FROM db_barrios.bar_relaciones_barrios r
                   WHERE r.id_barrio_destino=b.id_barrio AND r.estado='APROBADA') AS total_historicos
              FROM db_barrios.bar_barrios b
              JOIN db_barrios.bar_geometrias g ON g.id_barrio=b.id_barrio AND g.estado='APROBADO'
              WHERE b.estado='APROBADO' AND b.activo=1
                AND NOT EXISTS (SELECT 1 FROM db_barrios.bar_relaciones_barrios r
                    WHERE r.id_barrio_origen=b.id_barrio AND r.estado='APROBADA')
                AND g.version=(SELECT MAX(g2.version) FROM db_barrios.bar_geometrias g2
                               WHERE g2.id_barrio=b.id_barrio AND g2.estado='APROBADO')
              ORDER BY b.id_barrio";
        $rows=$this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $features=[];
        foreach($rows as $r) {
            $geometry=json_decode((string)$r['geojson'],true,512,JSON_THROW_ON_ERROR);
            if (!is_array($geometry)) continue;
            $color=(string)($r['color_mapa'] ?? '');
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/D',$color)) $color='#28846A';
            $features[]=['type'=>'Feature','geometry'=>$geometry,'properties'=>[
                'fuente'=>'OFICIAL','id_barrio'=>(int)$r['id_barrio'],
                'nombre'=>$r['nombre'],'clasificacion'=>$r['clasificacion'],
                'version'=>(int)$r['version'],'origen'=>$r['origen'],
                'color_mapa'=>$color,'surc'=>$r['surc'],
                'historicos'=>(int)$r['total_historicos']
            ]];
        }
        return ['type'=>'FeatureCollection','features'=>$features];
    }

    public function buscarPunto(float $lat, float $lon): array {
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new InvalidArgumentException('Coordenadas inválidas');
        }
        // Las coordenadas WKT se construyen solo a partir de números verificados.
        $wkt = sprintf('POINT(%.8F %.8F)', $lon, $lat);
        $s=$this->db->prepare("SELECT b.id_barrio,b.nombre,g.version
                FROM db_barrios.bar_barrios b
                JOIN db_barrios.bar_geometrias g ON g.id_barrio=b.id_barrio AND g.estado='APROBADO'
                WHERE b.activo=1 AND b.estado='APROBADO'
                  AND NOT EXISTS (SELECT 1 FROM db_barrios.bar_relaciones_barrios r
                      WHERE r.id_barrio_origen=b.id_barrio AND r.estado='APROBADA')
                  AND g.version=(SELECT MAX(g2.version) FROM db_barrios.bar_geometrias g2
                      WHERE g2.id_barrio=b.id_barrio AND g2.estado='APROBADO')
                  AND ST_CONTAINS(g.geometria, ST_GeomFromText(:punto,4326))");
        $s->execute([':punto'=>$wkt]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Solo oculta cartografía preliminar si la importación pertenece a una consolidación aprobada
     *  y el gobernante tiene geometría oficial publicada. */
    public function idsMunicipalesPublicados(): array {
        $sql="SELECT DISTINCT c.id_importacion_municipal
              FROM db_barrios.bar_consolidaciones c
              JOIN db_barrios.bar_barrios b ON b.id_barrio=c.id_barrio_gobernante
              JOIN db_barrios.bar_geometrias g ON g.id_barrio=b.id_barrio AND g.estado='APROBADO'
              WHERE c.estado='APROBADA' AND b.estado='APROBADO' AND b.activo=1
                AND c.id_importacion_municipal IS NOT NULL";
        return array_map('intval',$this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}
