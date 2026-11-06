<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

class Menu {
    private $pdoA;

    public function __construct() {
       $this->pdoA = Conexion::getConexion('BARRIOS');
                
    }

    public function getUserMenu($usuario_id, $modulo){
        $query = "SELECT DISTINCT(objetos.objeto_id) IdObjeto, objetos.objeto_descrip, permiso_ejec, objetos.objeto_link,objeto_ico,objeto_ico_color
			FROM segusuario.autorizacion
			JOIN segusuario.perfiles ON perfiles.perfil_id = autorizacion.autorizacion_perfil_id
			JOIN segusuario.objetos ON objetos.objeto_modulo_id = perfiles.perfil_modulo_id
			JOIN segusuario.permisos ON permisos.permiso_perfil_id = perfiles.perfil_id
				AND permisos.permiso_objeto_id = objetos.objeto_id
			WHERE autorizacion.autorizacion_usuario_id = ?
			AND perfiles.perfil_modulo_id = ?
			AND objetos.objeto_activo = 1
			AND objetos.objeto_titulo = 1
			AND (objetos.objeto_padre IS NULL or objetos.objeto_padre = 0)
			AND objetos.objeto_vercomo IN (1,3)
			GROUP BY IdObjeto";
        
        $stmt = $this->pdoA->prepare($query);
        $stmt->execute([$usuario_id, $modulo]);
       		
		return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    public function optionsMenu($usuario_id, $modulo, $idobjeto){

        $query = "SELECT DISTINCT(objetos.objeto_id) IdObjeto, objetos.objeto_link, objetos.objeto_descrip,objeto_orden,objeto_ico,objeto_ico_color
			FROM segusuario.autorizacion
			JOIN segusuario.perfiles ON autorizacion.autorizacion_perfil_id = perfiles.perfil_id
			JOIN segusuario.permisos ON perfiles.perfil_id = permisos.permiso_perfil_id
			JOIN segusuario.objetos ON permisos.permiso_objeto_id = objetos.objeto_id
			WHERE autorizacion.autorizacion_usuario_id = ?
			AND perfiles.perfil_modulo_id = ?
			AND permisos.permiso_ejec = 1
			AND objetos.objeto_activo = 1
			AND objetos.objeto_titulo = 1
			AND objetos.objeto_padre = ?
			AND objetos.objeto_vercomo IN (1,3)
			ORDER BY objetos.objeto_descrip";
        
        $stmt = $this->pdoA->prepare($query);
        $stmt->execute([$usuario_id,$modulo,$idobjeto]);
       		
		return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

}

?>