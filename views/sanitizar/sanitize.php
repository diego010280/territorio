<?php

require_once __DIR__ . "/../errores/errores.php"; // opcional

/**
 * Sanitiza cualquier tipo de dato (string, int, float, array).
 */
function sanitize($value)
{
    // Si es array, sanitizamos recursivamente
    if (is_array($value)) {
        $clean = [];
        foreach ($value as $k => $v) {
            $clean[$k] = sanitize($v);
        }
        return $clean;
    }

    // Si es null
    if ($value === null) {
        return null;
    }

    // Eliminar espacios
    $value = trim($value);

    // Si es INT válido
    if (ctype_digit($value)) {
        return (int)$value;
    }

    // Si es FLOAT válido
    if (is_numeric($value)) {
        return (float)$value;
    }

    // SIEMPRE sanitizamos string en última instancia
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Devuelve un array $_POST sanitizado COMPLETO.
 */
function sanitize_post(): array
{
    $clean = [];

    foreach ($_POST as $key => $value) {
        $clean[$key] = sanitize($value);
    }

    return $clean;
}