<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

class Menu
{
    private PDO $pdoA;

    public function __construct()
    {
        $this->pdoA = Conexion::getConexion('BARRIOS');
    }

    /** Opciones raíz visibles en el menú lateral. */
    public function getUserMenu($usuario_id, $modulo): array
    {
        $sql = "SELECT
                    o.objeto_id AS IdObjeto,
                    o.objeto_descrip,
                    o.objeto_link,
                    o.objeto_ico,
                    o.objeto_ico_color,
                    MAX(p.permiso_ejec) AS permiso_ejec
                FROM segusuario.autorizacion AS a
                INNER JOIN segusuario.perfiles AS pf
                    ON pf.perfil_id = a.autorizacion_perfil_id
                INNER JOIN segusuario.permisos AS p
                    ON p.permiso_perfil_id = pf.perfil_id
                INNER JOIN segusuario.objetos AS o
                    ON o.objeto_id = p.permiso_objeto_id
                WHERE a.autorizacion_usuario_id = ?
                    AND pf.perfil_modulo_id = ?
                    AND o.objeto_modulo_id = ?
                    AND pf.perfil_activo = 1
                    AND o.objeto_activo = 1
                    AND o.objeto_titulo = 1
                    AND (o.objeto_padre IS NULL OR o.objeto_padre = 0)
                    AND o.objeto_vercomo IN (1, 3)
                GROUP BY o.objeto_id, o.objeto_descrip, o.objeto_link,
                         o.objeto_ico, o.objeto_ico_color, o.objeto_orden
                HAVING MAX(p.permiso_ejec) = 1
                ORDER BY o.objeto_orden, o.objeto_descrip";
        $stmt = $this->pdoA->prepare($sql);
        $stmt->execute([(int)$usuario_id, (int)$modulo, (int)$modulo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Subopciones habilitadas del objeto padre. */
    public function optionsMenu($usuario_id, $modulo, $idobjeto): array
    {
        $sql = "SELECT
                    o.objeto_id AS IdObjeto,
                    o.objeto_link,
                    o.objeto_descrip,
                    o.objeto_orden,
                    o.objeto_ico,
                    o.objeto_ico_color,
                    MAX(p.permiso_ejec) AS permiso_ejec
                FROM segusuario.autorizacion AS a
                INNER JOIN segusuario.perfiles AS pf
                    ON pf.perfil_id = a.autorizacion_perfil_id
                INNER JOIN segusuario.permisos AS p
                    ON p.permiso_perfil_id = pf.perfil_id
                INNER JOIN segusuario.objetos AS o
                    ON o.objeto_id = p.permiso_objeto_id
                WHERE a.autorizacion_usuario_id = ?
                    AND pf.perfil_modulo_id = ?
                    AND o.objeto_modulo_id = ?
                    AND pf.perfil_activo = 1
                    AND o.objeto_activo = 1
                    AND o.objeto_titulo = 1
                    AND o.objeto_padre = ?
                    AND o.objeto_vercomo IN (1, 3)
                GROUP BY o.objeto_id, o.objeto_link, o.objeto_descrip,
                         o.objeto_orden, o.objeto_ico, o.objeto_ico_color
                HAVING MAX(p.permiso_ejec) = 1
                ORDER BY o.objeto_orden, o.objeto_descrip";
        $stmt = $this->pdoA->prepare($sql);
        $stmt->execute([(int)$usuario_id, (int)$modulo, (int)$modulo, (int)$idobjeto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Objetos habilitados para gadgets. La misma tabla de objetos y
     * permisos del menú es la única fuente de navegación.
     * objeto_vercomo: 1 menú, 2 icono, 3 ambos.
     */
    public function getUserGadgets($usuario_id, $modulo): array
    {
        $sql = "SELECT
                    o.objeto_id AS IdObjeto,
                    o.objeto_descrip,
                    o.objeto_link,
                    o.objeto_ico,
                    o.objeto_ico_color,
                    o.objeto_obs,
                    o.objeto_orden
                FROM segusuario.autorizacion AS a
                INNER JOIN segusuario.perfiles AS pf
                    ON pf.perfil_id = a.autorizacion_perfil_id
                INNER JOIN segusuario.permisos AS p
                    ON p.permiso_perfil_id = pf.perfil_id
                INNER JOIN segusuario.objetos AS o
                    ON o.objeto_id = p.permiso_objeto_id
                WHERE a.autorizacion_usuario_id = ?
                    AND pf.perfil_modulo_id = ?
                    AND o.objeto_modulo_id = ?
                    AND pf.perfil_activo = 1
                    AND o.objeto_activo = 1
                    AND o.objeto_titulo = 1
                    AND o.objeto_vercomo IN (2, 3)
                    AND o.objeto_link IS NOT NULL
                    AND TRIM(o.objeto_link) NOT IN ('', '#')
                GROUP BY o.objeto_id, o.objeto_descrip, o.objeto_link,
                         o.objeto_ico, o.objeto_ico_color,
                         o.objeto_obs, o.objeto_orden
                HAVING MAX(p.permiso_ejec) = 1
                ORDER BY o.objeto_orden, o.objeto_descrip";
        $stmt = $this->pdoA->prepare($sql);
        $stmt->execute([(int)$usuario_id, (int)$modulo, (int)$modulo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
