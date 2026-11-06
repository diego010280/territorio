<?php

require_once __DIR__ . '/../clases/Usuario.php';

$allowed = [];
$nperfil=[];
$perfilesUsuario=[];
$Aut_organismosId=[];
$Aut_org=[];


if (isset($_SESSION['logged'], $_SESSION['moduleid'])) {
	$consulta = false;
	$alta = false;
	$baja = false;
	$modifica = false;
	$pagina = basename($_SERVER['PHP_SELF']);
	
	$niveldeperfil = false;
	$niveldeautorizacion = false;
	$A_izq = 0;
	$A_der = 0;
	$cantidad_result=0;

	$autorizaciones = new Usuario();
	$resultados = $autorizaciones->getAuthorization($pagina);

	$cantidad_result=count($resultados);

	if ($cantidad_result > 0) {

		foreach ($resultados as $row_autorizado_a) {
			/*
			 * Los permisos se acumulan. Si el usuario tiene dos perfiles,
			 * alcanza con que uno de ellos autorice la operación.
			 */
			if ((int)$row_autorizado_a['permiso_ejec'] === 1) $consulta = true;
			if ($row_autorizado_a['permiso_alta'] == 1) $alta = true;
			if ($row_autorizado_a['permiso_baja'] == 1) $baja = true;
			if ($row_autorizado_a['permiso_mod'] == 1)  $modifica = true;

			if (empty($niveldeperfil) or $niveldeperfil < $row_autorizado_a['perfil_EsAdm']) {
				$niveldeperfil = $row_autorizado_a['perfil_EsAdm'];
			}

			if (empty($niveldeautorizacion) or $niveldeautorizacion < $row_autorizado_a['autorizacion_nivel']) {
			$niveldeautorizacion = $row_autorizado_a['autorizacion_nivel'];
			}

			if (($row_autorizado_a['Aut_izq'] < $A_izq) or ($row_autorizado_a['Aut_der'] > $A_der)) {
			
			$A_izq = $row_autorizado_a['Aut_izq'];
			$A_der = $row_autorizado_a['Aut_der'];
			$A_idorg = $row_autorizado_a['Aut_idorg'];
			$Aut_org[] = array ('Aut_idorg' => $row_autorizado_a['Aut_idorg'], 'Aut_izq' => $A_izq, 'Aut_der' => $A_der, 'A_descdepend'=> $row_autorizado_a['Desc_depend']);
			}

			$nperfil[] = (int)$row_autorizado_a['perfil_id'];

			/*
			 * Se usa la descripción como identificador funcional porque los
			 * perfil_id pueden cambiar entre desarrollo y producción.
			 */
			$perfilDescripcion = strtolower(
				trim((string)($row_autorizado_a['perfil_descrip'] ?? ''))
			);

			if ($perfilDescripcion !== '') {
				$perfilesUsuario[] = $perfilDescripcion;
			}

			$Aut_organismosId[] = $row_autorizado_a['Aut_idorg']; 
	
		}

		$nperfil = array_values(array_unique($nperfil));
		$perfilesUsuario = array_values(array_unique($perfilesUsuario));

		/* Perfiles funcionales del módulo SIAD. */
		$esPerfilAdministrador = in_array(
			'administrador',
			$perfilesUsuario,
			true
		);

		$esPerfilCobranzaAdicional = in_array(
			'cobranza adicional',
			$perfilesUsuario,
			true
		);

		$esPerfilGestionAdicional = in_array(
			'gestion_adicional',
			$perfilesUsuario,
			true
		);

		$puedeFiltrarEstados =
			$esPerfilAdministrador ||
			$esPerfilCobranzaAdicional;

		$soloPuedeVerPagados =
			!$puedeFiltrarEstados &&
			$esPerfilGestionAdicional;

	}else {
		header('Location: /acceso/login?access=private');
		exit;
	}


} else {
	header('Location: /acceso/login?access=private');
	exit;
}


?>
