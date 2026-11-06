<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Autorización centralizada por objetos y permisos.
 *
 * Requiere que segacceso.php haya iniciado y validado la sesión.
 * El usuario y el módulo siempre se toman de la sesión general.
 */
final class Autorizacion
{
    private PDO $pdo;
    private int $usuarioId;
    private int $moduloId;

    /** @var array<string, array{ejec: bool, alta: bool, baja: bool, modifica: bool}>|null */
    private ?array $mapaPermisos = null;

    public function __construct()
    {
        $this->usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
        $this->moduloId = (int) ($_SESSION['moduleid'] ?? 0);

        if (
            empty($_SESSION['logged']) ||
            $this->usuarioId < 1 ||
            $this->moduloId < 1
        ) {
            throw new RuntimeException('La sesión del usuario no es válida.', 401);
        }

        $this->pdo = Conexion::getConexion('BARRIOS');
    }

    /**
     * Devuelve los cuatro permisos acumulados para un objeto.
     * Si el usuario tiene varios perfiles, se aplica el permiso más amplio.
     *
     * @return array{ejec: bool, alta: bool, baja: bool, modifica: bool}
     */
    public function permisos(string $objetoLink): array
    {
        $objetoLink = trim($objetoLink);

        if ($objetoLink === '') {
            return $this->permisosVacios();
        }

        $mapa = $this->cargarMapaPermisos();

        return $mapa[$objetoLink] ?? $this->permisosVacios();
    }

    /**
     * Comprueba un permiso puntual.
     * Acciones aceptadas: ejec, consulta, alta, baja, mod y modifica.
     */
    public function puede(string $objetoLink, string $accion = 'ejec'): bool
    {
        $accionNormalizada = strtolower(trim($accion));

        $equivalencias = [
            'ejec' => 'ejec',
            'consulta' => 'ejec',
            'alta' => 'alta',
            'baja' => 'baja',
            'mod' => 'modifica',
            'modifica' => 'modifica',
            'modificacion' => 'modifica',
        ];

        if (!isset($equivalencias[$accionNormalizada])) {
            throw new InvalidArgumentException('La acción de autorización no es válida.');
        }

        $permisos = $this->permisos($objetoLink);

        return $permisos[$equivalencias[$accionNormalizada]];
    }

    /**
     * Interrumpe el proceso cuando falta el permiso requerido.
     */
    public function exigir(string $objetoLink, string $accion = 'ejec'): void
    {
        if (!$this->puede($objetoLink, $accion)) {
            throw new RuntimeException('No posee autorización para realizar esta operación.', 403);
        }
    }

    /**
     * Carga una vez por petición todos los permisos del módulo actual.
     * Ante un error devuelve un mapa vacío: la autorización falla de forma segura.
     *
     * @return array<string, array{ejec: bool, alta: bool, baja: bool, modifica: bool}>
     */
    private function cargarMapaPermisos(): array
    {
        if ($this->mapaPermisos !== null) {
            return $this->mapaPermisos;
        }

        $query = "SELECT
                    o.objeto_link,
                    MAX(p.permiso_ejec) AS permiso_ejec,
                    MAX(p.permiso_alta) AS permiso_alta,
                    MAX(p.permiso_baja) AS permiso_baja,
                    MAX(p.permiso_mod) AS permiso_mod
                  FROM segusuario.autorizacion AS a
                  INNER JOIN segusuario.perfiles AS pf
                    ON pf.perfil_id = a.autorizacion_perfil_id
                  INNER JOIN segusuario.permisos AS p
                    ON p.permiso_perfil_id = pf.perfil_id
                  INNER JOIN segusuario.objetos AS o
                    ON o.objeto_id = p.permiso_objeto_id
                  WHERE a.autorizacion_usuario_id = :usuario_id
                    AND pf.perfil_activo = 1
                    AND pf.perfil_modulo_id = :modulo_perfil
                    AND o.objeto_modulo_id = :modulo_objeto
                    AND o.objeto_activo = 1
                  GROUP BY o.objeto_link";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':usuario_id', $this->usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':modulo_perfil', $this->moduloId, PDO::PARAM_INT);
            $stmt->bindValue(':modulo_objeto', $this->moduloId, PDO::PARAM_INT);
            $stmt->execute();

            $this->mapaPermisos = [];

            while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $link = trim((string) ($fila['objeto_link'] ?? ''));

                if ($link === '') {
                    continue;
                }

                $this->mapaPermisos[$link] = [
                    'ejec' => (int) ($fila['permiso_ejec'] ?? 0) === 1,
                    'alta' => (int) ($fila['permiso_alta'] ?? 0) === 1,
                    'baja' => (int) ($fila['permiso_baja'] ?? 0) === 1,
                    'modifica' => (int) ($fila['permiso_mod'] ?? 0) === 1,
                ];
            }
        } catch (Throwable $e) {
            $this->mapaPermisos = [];

            if (function_exists('escribirErrorXML')) {
                escribirErrorXML(
                    'Error al cargar permisos del usuario',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                );
            }
        }

        return $this->mapaPermisos;
    }

    /**
     * @return array{ejec: bool, alta: bool, baja: bool, modifica: bool}
     */
    private function permisosVacios(): array
    {
        return [
            'ejec' => false,
            'alta' => false,
            'baja' => false,
            'modifica' => false,
        ];
    }
}
