<?php
declare(strict_types=1);
final class TerritorioConsulta {
    public function __construct(private PDO $db) {}
    /** Geometrías MUNICIPALES NO PUBLICADAS en tabla intermedia. */
    public function cartografiaImportada(int $limite = 1000): array {
        $limite = max(1, min(1000, $limite));
        $sql = 'SELECT id_importacion, nombre_original, clasificacion, estado, id_externo, '
             . 'ST_AsGeoJSON(geometria, 6) AS geojson '
             . 'FROM bar_importacion_geometrias '
             . 'ORDER BY id_importacion LIMIT ' . $limite;
        $rows = $this->db->query($sql)->fetchAll();
        $features = [];
        foreach ($rows as $r) {
            if (empty($r['geojson'])) continue;
            $g = json_decode($r['geojson'], true, 512, JSON_THROW_ON_ERROR);
            $features[] = ['type'=>'Feature', 'geometry'=>$g, 'properties'=>[
                'id_importacion'=>(int)$r['id_importacion'],
                'nombre'=>$r['nombre_original'], 'clasificacion'=>$r['clasificacion'],
                'estado'=>$r['estado'], 'id_externo'=>$r['id_externo'],
            ]];
        }
        return ['type'=>'FeatureCollection', 'features'=>$features];
    }
    public function buscarPunto(float $lat, float $lon): array {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) throw new InvalidArgumentException('Coordenadas inválidas');
        $wkt = sprintf('POINT(%.10F %.10F)', $lon, $lat);
        $st=$this->db->prepare('SELECT id_importacion, nombre_original, estado FROM bar_importacion_geometrias '
            .'WHERE ST_Contains(geometria, ST_GeomFromText(:wkt,4326)) ORDER BY id_importacion LIMIT 20');
        $st->execute(['wkt'=>$wkt]);
        return $st->fetchAll();
    }
}
