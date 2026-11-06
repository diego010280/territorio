<?php

declare(strict_types=1);

final class Conexion
{
    /** @var array<string, PDO> */
    private static array $conexiones = [];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $configuraciones = null;

    public static function getConexion(string $clave): PDO
    {
        if (isset(self::$conexiones[$clave])) {
            return self::$conexiones[$clave];
        }

        $configuraciones = self::obtenerConfiguraciones();

        if (!isset($configuraciones[$clave])) {
            throw new InvalidArgumentException(
                "No existe la configuracion para la conexion '{$clave}'."
            );
        }

        $conf = $configuraciones[$clave];

        foreach (['host', 'dbname', 'user', 'pass', 'charset'] as $campo) {
            if (!array_key_exists($campo, $conf)) {
                throw new RuntimeException(
                    "Falta el parametro '{$campo}' en la conexion '{$clave}'."
                );
            }
        }

        $host = (string) $conf['host'];
        $puerto = (int) ($conf['port'] ?? 3306);
        $base = (string) $conf['dbname'];
        $charset = (string) $conf['charset'];

        $dsn = "mysql:host={$host};port={$puerto};" .
            "dbname={$base};charset={$charset}";

        try {
            $pdo = new PDO(
                $dsn,
                (string) $conf['user'],
                (string) $conf['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );

            self::$conexiones[$clave] = $pdo;

            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException(
                "No se pudo establecer la conexion '{$clave}'.",
                0,
                $e
            );
        }
    }

    public static function cerrarConexion(string $clave): void
    {
        unset(self::$conexiones[$clave]);
    }

    public static function cerrarTodas(): void
    {
        self::$conexiones = [];
    }

    /** @return array<string, array<string, mixed>> */
    private static function obtenerConfiguraciones(): array
    {
        if (self::$configuraciones !== null) {
            return self::$configuraciones;
        }

        $rutaConfig = __DIR__ . '/config.php';

        if (!is_file($rutaConfig)) {
            throw new RuntimeException('No se encontro el archivo config.php.');
        }

        $configuraciones = require $rutaConfig;

        if (!is_array($configuraciones)) {
            throw new RuntimeException(
                'El archivo config.php debe devolver un arreglo.'
            );
        }

        self::$configuraciones = $configuraciones;

        return self::$configuraciones;
    }
}

