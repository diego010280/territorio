<?php 

$controlahorariolaboral=false; // si va a controlar poner en true la variable

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers_cliente.php';
require_once __DIR__ . '/../clases/Usuario.php';
require_once __DIR__ . "/../views/errores/errores.php";

//$autorizado=0;
$datosCliente = obtenerDatosCliente();
//$repositorioAuditoria = new Auditoria();

date_default_timezone_set('America/Argentina/Salta');

if (empty($_REQUEST['usuariochar']) or empty($_REQUEST['moduloid'])) {
    
	$_SESSION = array();
	session_destroy();
	
	header('Location: /acceso/login?err=usnull');
	exit;
	
} else {
    
    /* si esta correcto */

	$_SESSION['logged'] = true;
	$_SESSION['start'] = time();
	$_SESSION['moduleid']= htmlspecialchars($_REQUEST['moduloid']); 
    
	$_SESSION['dispositivo_id'] = $datosCliente['dispositivo_id'];
	$_SESSION['ip'] = $datosCliente['ip'];
	$_SESSION['user_agent'] = $datosCliente['user_agent'];
	$_SESSION['host_cliente'] = $datosCliente['host_cliente'];

	
	if ($controlahorariolaboral) {
		/*********** Si va controlar si esta trabajando usar estas lineas y script para el control********/

		/* Si el perfil que le damos al usuario le ponemos avanzado o experto no controla tampoco si esta laburando */
		/*  */
		include 'checks_usu_app.php';

		if ($autorizado == 1) {
			header("Location: ../ctrlpanel");
			exit;

		} else {
			// mostrar panel que no puede ingresar por estar fuera de rango
			header("Location: ../views/inactividad/bloqueado");
			exit;
		}
		/* ********************************************************************************************* */
	}else{
		/* Si no va a controlar que este trabajando o no tiene funciones asignadas descomentar esta linea*/
		header('Location: ../ctrlpanel');
		exit;
		
	}
	
	
    
}