<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: no-cache, private', false);
header('Pragma: no-cache');
header('Expires: 0');

$tiempoEspera = 1800; // 30 minutos de inactividad
//$tiempoEspera = 120; // minimo tiempo para prueba
$ahora = time();

if (empty($_SESSION['logged']) or !is_numeric($_SESSION['moduleid']) or ($_SESSION['moduleid'] < 1)) {

	header('Location:../acceso/login?access=private');
	exit;

} 

/* =========================================================
   INICIALIZAR ÚLTIMA ACTIVIDAD
========================================================= */

if (!isset($_SESSION['ultima_actividad'])) {
    $_SESSION['ultima_actividad'] = $ahora;
}

/* =========================================================
   CONTROLAR TIEMPO DE INACTIVIDAD
========================================================= */

$tiempoInactivo =
    $ahora - (int) $_SESSION['ultima_actividad'];

if ($tiempoInactivo >= $tiempoEspera) {

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

    header('Location: ../acceso/login?access=timeout');
    exit;
}

/* =========================================================
   REGISTRAR ACTIVIDAD REAL
========================================================= */

/*
 * Como el usuario ingresó a una página protegida,
 * esta petición se considera actividad.
 */
$_SESSION['ultima_actividad'] = $ahora;


?>