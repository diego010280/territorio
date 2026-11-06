<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: no-cache, private", false);
header("Pragma: no-cache");
header("Expires: 0");
$tiempoespera = 1800; // 30 minutos

if (empty($_SESSION['logged']) or !is_numeric($_SESSION['moduleid']) or ($_SESSION['moduleid'] < 1)) {

	header('Location:../acceso/login?access=private');
	exit;

} 

if (!isset($_SESSION['start'])) {
    $_SESSION['start'] = time();
}

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
}

if ((time() - $_SESSION['last_regeneration']) > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

$iniciosesion = $_SESSION['start'];
$ahora = time();
$tiempotransc = $ahora - $iniciosesion;


		
if ($tiempotransc > $tiempoespera) {
		
		$_SESSION = array();

		if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
		session_destroy();
		
		header('Location: ../acceso/login?access=timeout');
		exit;
		
    
}

?>