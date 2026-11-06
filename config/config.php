<?php

declare(strict_types=1);

/*
 * Propuesta de conexiones nuevas.
 *
 * - siad: servidor MySQL 8.3, auditorias y tablas nuevas.
 * - legacy: servidor MySQL 5.5, esquemas adi/rrhh/segusuario.
 *
 * En local ambas conexiones pueden apuntar al mismo servidor.
 * En produccion se cambian solamente las variables de entorno.
 *
 * Las claves db_a, db_b, etc. deben mantenerse temporalmente en el
 * config.php actual hasta migrar las clases antiguas.
 */

function valorEntorno(string $nombre, string $predeterminado = ''): string
{
    $valor = getenv($nombre);

    return $valor === false || $valor === ''
        ? $predeterminado
        : $valor;
}

return [

     
    'BARRIOS' => [
        'host'    => valorEntorno('BARRIOS_DB_HOST', 'localhost'),
        'port'    => (int) valorEntorno('BARRIOS_DB_PORT', '3306'),
        'dbname'  => valorEntorno('BARRIOS_DB_NAME', 'db_barrios'),
        'user'    => valorEntorno('BARRIOS_DB_USER', 'polsistemas'),
        'pass'    => valorEntorno('BARRIOS_DB_PASS', 'z2CEv7awlw1qPzPd'),
        'charset' => valorEntorno('BARRIOS_DB_CHARSET', 'utf8mb4'),
    ]

    
];