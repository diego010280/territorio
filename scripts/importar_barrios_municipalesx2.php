<?php
/**
 * Importador cartográfico municipal a db_barrios.bar_importacion_geometrias
 * Uso: php importar_barrios_municipales.php /ruta/barrio3.js
 *       php importar_barrios_municipales.php /ruta/barrio3.js --importar
 * Requiere PHP 8.1+, PDO MySQL. SOLO entorno local.
 */
declare(strict_types=1);
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ejecutar desde CLI'); }
$archivo = $argv[1] ?? '';
$importar = in_array('--importar', $argv, true);
if (!is_file($archivo) || !is_readable($archivo)) {
    fwrite(STDERR, "Uso: php importar_barrios_municipales.php barrio3.js [--importar]\n");
    exit(1);
}
$raw = file_get_contents($archivo);
if ($raw === false) { throw new RuntimeException('No se pudo leer el archivo'); }

// El archivo original incluye un GeoJSON comentado, seguido por el GeoJSON activo.
// Localizar el inicio del objeto ACTIVO (línea que comienza literalmente con {).
if (!preg_match('/^\s*\{\s*$/m', $raw, $match, PREG_OFFSET_CAPTURE)) {
    throw new RuntimeException('No se encontró el inicio del GeoJSON activo');
}
$start = $match[0][1];
$json = substr($raw, $start);
$json = preg_replace('/;\s*$/', '', trim($json));
$coleccion = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
if (($coleccion['type'] ?? '') !== 'FeatureCollection' || !is_array($coleccion['features'] ?? null)) {
    throw new RuntimeException('No se detectó FeatureCollection válido');
}
$features = $coleccion['features'];
echo 'Registros GeoJSON activos: ', count($features), PHP_EOL;
if (count($features) < 400) {
    throw new RuntimeException('Cantidad inesperadamente baja: revisar origen antes de importar');
}

function aWkt(array $geom): string {
    if (($geom['type'] ?? null) !== 'MultiPolygon' || !is_array($geom['coordinates'] ?? null)) {
        throw new RuntimeException('Se esperaba una geometría MultiPolygon');
    }
    $polys = [];
    foreach ($geom['coordinates'] as $polygon) {
        if (!is_array($polygon) || !$polygon) { throw new RuntimeException('Polígono vacío'); }
        $rings = [];
        foreach ($polygon as $ring) {
            if (!is_array($ring) || count($ring) < 4) { throw new RuntimeException('Anillo con menos de 4 puntos'); }
            $puntos = [];
            foreach ($ring as $point) {
                if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
                    throw new RuntimeException('Coordenadas inválidas');
                }
                $lon = (float) $point[0]; $lat = (float) $point[1];
                if (!is_finite($lon) || !is_finite($lat) || $lon < -180 || $lon > 180 || $lat < -90 || $lat > 90) {
                    throw new RuntimeException('Coordenadas fuera de rango');
                }
                $puntos[] = sprintf('%.15F %.15F', $lon, $lat);
            }
            if ($puntos[0] !== $puntos[count($puntos)-1]) {
                throw new RuntimeException('Anillo no cerrado');
            }
            $rings[] = '(' . implode(',', $puntos) . ')';
        }
        $polys[] = '(' . implode(',', $rings) . ')';
    }
    if (!$polys) { throw new RuntimeException('Multipolígono vacío'); }
    return 'MULTIPOLYGON(' . implode(',', $polys) . ')';
}

$datos = [];
foreach ($features as $idx => $f) {
    try {
        $p = $f['properties'] ?? [];
        $nombre = trim((string) ($p['BARRIO'] ?? ''));
        if ($nombre === '') { throw new RuntimeException('Nombre vacío'); }
        $datos[] = [
            'id' => isset($p['ID']) ? (string) $p['ID'] : null,
            'nombre' => $nombre,
            'normalizado' => mb_strtoupper($nombre, 'UTF-8'),
            'clase' => isset($p['BARRIO_CL']) ? (string) $p['BARRIO_CL'] : null,
            'tipo' => isset($p['TIPO']) ? (string) $p['TIPO'] : null,
            'anio' => isset($p['AÑO']) && $p['AÑO'] !== null ? (int) $p['AÑO'] : null,
            'wkt' => aWkt($f['geometry'] ?? []),
        ];
    } catch (Throwable $e) {
        throw new RuntimeException('Feature #'.($idx+1).': '.$e->getMessage(), 0, $e);
    }
}
echo 'Geometrías pre-validadas: ', count($datos), PHP_EOL;
if (!$importar) {
    echo "MODO PRUEBA: no se escribió nada. Para importar: php importar_barrios_municipales.php barrio3.js --importar\n";
    exit(0);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
if ($user === '') { throw new RuntimeException('Definir DB_USER y DB_PASS en variables de entorno'); }
$pdo = new PDO("mysql:host={$host};port={$port};dbname=db_barrios;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->beginTransaction();
try {
    $existentes = (int) $pdo->query("SELECT COUNT(*) FROM bar_importacion_geometrias WHERE fuente='MUNICIPALIDAD_SALTA'")->fetchColumn();
    if ($existentes > 0) { throw new RuntimeException("Ya existen {$existentes} registros municipales: importación detenida para evitar duplicados"); }
    $sql = "INSERT INTO bar_importacion_geometrias
      (fuente, id_externo, nombre_original, nombre_normalizado, clasificacion, tipo, anio, geometria, estado, observaciones)
      VALUES ('MUNICIPALIDAD_SALTA', :id, :nombre, :normalizado, :clase, :tipo, :anio,
              ST_GeomFromText(:wkt, 4326), 'SIN_VINCULAR', 'Importación inicial barrio3.js')";
    $st = $pdo->prepare($sql);
    foreach ($datos as $d) {
        $st->execute([
            'id'=>$d['id'], 'nombre'=>$d['nombre'], 'normalizado'=>$d['normalizado'],
            'clase'=>$d['clase'], 'tipo'=>$d['tipo'], 'anio'=>$d['anio'], 'wkt'=>$d['wkt']
        ]);
    }
    $total = (int) $pdo->query("SELECT COUNT(*) FROM bar_importacion_geometrias WHERE fuente='MUNICIPALIDAD_SALTA'")->fetchColumn();
    if ($total !== count($datos)) { throw new RuntimeException("Verificación fallida: {$total} != ".count($datos)); }
    $pdo->commit();
    echo "IMPORTACIÓN LOCAL EXITOSA: {$total} geometrías SIN_VINCULAR. No se publicó ningún barrio.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'ROLLBACK: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
