<?php
declare(strict_types=1);
final class ConexionTerritorio {
    public static function obtener(): PDO {
        $archivo = dirname(__DIR__) . '/config/config.local.php';
        if (!is_file($archivo)) throw new RuntimeException('Falta config/config.local.php');
        $cfg = require $archivo;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'], $cfg['db_name']);
        return new PDO($dsn, $cfg['db_user'], $cfg['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
