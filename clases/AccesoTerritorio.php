<?php
declare(strict_types=1);
/** Puente de autenticación INDEPENDIENTE. Adaptar a SIPS antes de producción. */
final class AccesoTerritorio {
    public static function exigirSesion(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        // No se confía en parámetros GET ni POST para autenticar.
        // Si SIPS comparte la sesión institucional, adaptar aquí su validación.
        // Hasta configurarla, el acceso permanece bloqueado para desconocidos.
        $valida = !empty($_SESSION['logged']) && !empty($_SESSION['usuario_id']);
        if (!$valida) {
            http_response_code(401);
            if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/ajax/')) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['error'=>'Sesión institucional requerida']));
            }
            exit('Acceso restringido. Integrar autenticación institucional de Territorio.');
        }
    }
}
