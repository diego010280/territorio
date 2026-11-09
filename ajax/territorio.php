<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once dirname(__DIR__) . '/clases/AccesoTerritorio.php';
require_once dirname(__DIR__) . '/clases/ConexionTerritorio.php';
require_once dirname(__DIR__) . '/clases/TerritorioConsulta.php';
AccesoTerritorio::exigirSesion();
try {
    $servicio = new TerritorioConsulta(ConexionTerritorio::obtener());
    $accion = $_GET['accion'] ?? 'cartografia';
    if ($accion === 'cartografia') {
        $resultado = $servicio->cartografiaImportada();
    } elseif ($accion === 'punto') {
        $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
        $lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lat === null || $lon === false || $lon === null) throw new InvalidArgumentException('Latitud/longitud inválidas');
        $resultado = ['resultados'=>$servicio->buscarPunto($lat,$lon), 'tipo'=>'cartografia_importada'];
    } else {
        http_response_code(400); $resultado=['error'=>'Acción no disponible'];
    }
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
     $status = (int) $e->getCode();

    if (!in_array($status, [400, 401, 403, 404, 405], true)) {
        $status = $e instanceof InvalidArgumentException ? 400 : 500;
    }

    $codigoError = 'TERR-' . date('Ymd-His')
        . '-' . bin2hex(random_bytes(3));

    error_log(
        $codigoError . ' | Propuesta barrio | ' .
        get_class($e) . ' | ' .
        $e->getMessage() . ' | ' .
        $e->getFile() . ':' . $e->getLine()
    );

    http_response_code($status);

    echo json_encode([
        'ok' => false,
        'codigo_error' => $codigoError,
        'error' => $status === 500
            ? 'No se pudo registrar la solicitud.'
            : $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
