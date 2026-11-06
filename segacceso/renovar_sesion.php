<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$sesionValida =
    !empty($_SESSION['logged']) &&
    $_SESSION['logged'] === true &&
    isset($_SESSION['moduleid']) &&
    is_numeric($_SESSION['moduleid']) &&
    (int) $_SESSION['moduleid'] > 0;


if (!$sesionValida) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'activa'  => false,
        'message' => 'Sesión no válida'
    ]);
    exit;
}

/* $_SESSION['start'] = time();

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
}

if ((time() - $_SESSION['last_regeneration']) > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}


echo json_encode([
    'success' => true,
    'expired' => false
]); */
$_SESSION['ultima_actividad'] = time();

echo json_encode([
    'success' => true,
    'activa'  => true
]);
exit;

?>