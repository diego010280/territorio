<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Esta ruta es de SOLO LECTURA. Integrar luego autorización institucional por acción.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['usuario_id']) || empty($_SESSION['moduleid'])) {
    http_response_code(401);
    echo json_encode(['error'=>'Sesión no autorizada'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/clases/TerritorioConsulta.php';
require_once dirname(__DIR__) . '/clases/VisorOficial.php';
try {
    // Ajustar la clave según config/config.php (sin cambiar contraseñas).
    $db = Conexion::getConexion('BARRIOS');
    $consulta = new TerritorioConsulta($db);
    $oficial = new VisorOficial($db);
    $accion = $_GET['accion'] ?? 'cartografia';
    if ($accion === 'cartografia') {
        $resultado = $consulta->cartografiaImportada();
        // Excluir duplicados municipales únicamente de la vista, no de la BD.
        $ocultos = array_flip($oficial->idsMunicipalesPublicados());
        if (isset($resultado['features']) && is_array($resultado['features'])) {
            $resultado['features'] = array_values(array_filter(
                $resultado['features'],
                static fn(array $f): bool => !isset($ocultos[(int)($f['properties']['id_importacion'] ?? 0)])
            ));
        }
    } elseif ($accion === 'oficiales') {
        $resultado = $oficial->cartografia();
    } elseif ($accion === 'punto_oficial') {
        $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
        $lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lat === null || $lon === false || $lon === null) {
            throw new InvalidArgumentException('Coordenadas inválidas');
        }
        $resultado = ['resultados' => $oficial->buscarPunto($lat,$lon)];
    } elseif ($accion === 'punto') {
        $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
        $lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lat === null || $lon === false || $lon === null) {
            throw new InvalidArgumentException('Coordenadas inválidas');
        }
        $resultado = ['resultados' => $consulta->buscarPunto($lat,$lon)];
    } else {
        throw new InvalidArgumentException('Acción desconocida');
    }
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[territorio visor] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'No se pudo procesar la consulta'],JSON_UNESCAPED_UNICODE);
}
