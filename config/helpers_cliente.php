<?php

declare(strict_types=1);

function obtenerDispositivoId(): string
{
    $nombreCookie = 'dispositivo_id';
    $valorActual = (string) ($_COOKIE[$nombreCookie] ?? '');

    // El identificador creado con random_bytes(16) tiene 32 caracteres hex.
    if (preg_match('/^[a-f0-9]{32}$/', $valorActual) === 1) {
        return $valorActual;
    }

    $dispositivoId = bin2hex(random_bytes(16));
    $usaHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie($nombreCookie, $dispositivoId, [
        'expires'  => time() + (86400 * 365),
        'path'     => '/',
        'secure'   => $usaHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[$nombreCookie] = $dispositivoId;

    return $dispositivoId;
}

function obtenerDatosCliente(): array
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    /*
     * No se usa gethostbyaddr() en cada peticion porque una resolucion DNS
     * lenta puede demorar toda la consulta. REMOTE_HOST se usa si Apache ya
     * lo resolvio; de lo contrario queda NULL.
     */
    $hostCliente = trim((string) ($_SERVER['REMOTE_HOST'] ?? ''));

    return [
        'ip'             => $ip !== '' ? $ip : null,
        'user_agent'     => $userAgent !== ''
            ? mb_substr($userAgent, 0, 500)
            : null,
        'host_cliente'   => $hostCliente !== '' ? $hostCliente : null,
        'dispositivo_id' => obtenerDispositivoId(),
    ];
}

function inicializarDatosClienteEnSesion(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $datosCliente = obtenerDatosCliente();

    $_SESSION['ip'] = $datosCliente['ip'] ?? '';
    $_SESSION['user_agent'] = $datosCliente['user_agent'] ?? '';
    $_SESSION['host_cliente'] = $datosCliente['host_cliente'] ?? '';
    $_SESSION['dispositivo_id'] = $datosCliente['dispositivo_id'] ?? '';

    return $datosCliente;
}

