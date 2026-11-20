<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $usuario = (int)($_SESSION['usuario_id'] ?? 0);
    $modulo = (int)($_SESSION['moduleid'] ?? 0);
    if ($usuario < 1 || $modulo !== 1239) throw new RuntimeException('Sesión no autorizada.', 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método no permitido.', 405);
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['visor18_csrf'] ?? ''), $token)) throw new RuntimeException('Token inválido.', 403);
    $body = json_decode((string)file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($body)) throw new InvalidArgumentException('Datos inválidos.', 400);
    $tipo = (string)($body['tipo'] ?? '');
    if (!in_array($tipo, ['CONSULTA','BUSQUEDA'], true)) throw new InvalidArgumentException('Acción no autorizada.',400);
    $params = $body['parametros'] ?? [];
    if (!is_array($params)) throw new InvalidArgumentException('Parámetros inválidos.',400);
    // Sólo se reciben campos de consulta. Evitar grandes objetos de geometría o datos privados.
    $permitidos = ['origen','accion','texto','resultados','tipo','id','lat','lon','coincidencias'];
    $params = array_intersect_key($params, array_flip($permitidos));
    $params['origen'] = 'Visor Territorial';
    $encoded = json_encode($params, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 2000) throw new InvalidArgumentException('Parámetros excesivos.',400);
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../clases/AuditoriaTerritorio.php';
    $db = Conexion::getConexion('BARRIOS');
    $auditoria = new AuditoriaTerritorio($db);
    $auditoria->registrarAccion([
        'usuario'=>$usuario,
        'tipo'=>$tipo === 'BUSQUEDA' ? AuditoriaTerritorio::BUSQUEDA : AuditoriaTerritorio::CONSULTA,
        'observacion'=>$tipo === 'BUSQUEDA' ? 'Busqueda en Visor Territorial' : 'Consulta en Visor Territorial',
        'parametros'=>$params
    ]);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    $status = (int)$e->getCode();
    if (!in_array($status, [400,401,403,405], true)) $status=500;
    if ($status===500) error_log('[TERRITORIO VISOR AUDITORIA V18] '. $e->getMessage());
    http_response_code($status);
    echo json_encode(['error'=>$status===500 ? 'No se pudo registrar la auditoría.' : $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
