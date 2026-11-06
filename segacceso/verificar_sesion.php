<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$tiempoEspera = 1800;
//$tiempoEspera = 120;
$ahora = time();

$sesionValida =
    !empty($_SESSION['logged']) &&
    $_SESSION['logged'] === true &&
    isset($_SESSION['moduleid']) &&
    is_numeric($_SESSION['moduleid']) &&
    (int) $_SESSION['moduleid'] > 0;
// Validación de sesión personalizada
if (!$sesionValida) {
    
    echo json_encode([
        'success' => false,
        'expired' => true,
        'message' => 'Sesión no válida',
        'activa' => false
    ]);
    exit;
}

$ultimaActividad =
    (int) ($_SESSION['ultima_actividad'] ?? 0);

$sesionVencida =
    $ultimaActividad <= 0 ||
    ($ahora - $ultimaActividad) >= $tiempoEspera;

if ($sesionVencida) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    echo json_encode([
        'success' => false,
        'expired' => true,
        'activa'  => false,
        'message' => 'Sesión vencida por inactividad'
    ]);

    exit;
}

/*
 * Importante: no actualizar ultima_actividad aquí.
 */
// Si la sesión es válida
echo json_encode([
    'success' => true,
    'expired' => false,
    'activa' => true
]);
exit;