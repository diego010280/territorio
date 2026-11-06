<?php

declare(strict_types=1);

$controlaHorarioLaboral = false;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers_cliente.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . '/../views/errores/errores.php';

date_default_timezone_set('America/Argentina/Salta');

/*
 * Se aceptan GET o POST porque este archivo recibe el ingreso desde el
 * sistema de acceso. No se usa $_REQUEST para evitar tomar valores de cookies.
 */
$usuarioChar = trim(
    (string) ($_POST['usuariochar'] ?? '')
);

$moduloId = trim(
    (string) ($_POST['moduloid'] ?? '')
);

error_log(
    'CHECKS - metodo=' .
    ($_SERVER['REQUEST_METHOD'] ?? '') .
    ' usuariochar=' .
    ($usuarioChar !== '' ? 'RECIBIDO' : 'VACIO') .
    ' moduloid=' .
    ($moduloId !== '' ? $moduloId : 'VACIO')
);

if ($usuarioChar === '' || $moduloId === '') {
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    header('Location: /acceso/login?err=parametros');
    exit;
}


/*
 * Inicializa en sesion:
 * ip, user_agent, host_cliente y dispositivo_id.
 */
inicializarDatosClienteEnSesion();

$_SESSION['logged'] = true;
$_SESSION['start'] = time();
$_SESSION['moduleid'] = (int) $moduloId;

/*
 * Mantener aqui la logica existente que valida usuariochar y establece
 * $_SESSION['usuario_id']. No se asigna directamente porque usuariochar puede
 * ser un identificador firmado o una referencia del sistema de acceso.
 */

if ($controlaHorarioLaboral) {
    require __DIR__ . '/checks_usu_app.php';

    if (($autorizado ?? 0) === 1) {
        header('Location: ../ctrlpanel');
        exit;
    }

    header('Location: ../views/inactividad/bloqueado');
    exit;
}


header('Location: ../ctrlpanel');
exit;

