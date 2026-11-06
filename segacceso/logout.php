<?php

session_start();
$_SESSION = array();
session_unset();

	if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"]
    );
	}
session_destroy();

header('Location: /acceso/login');
exit;

?>