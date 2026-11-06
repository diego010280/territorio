<?php
// Ruta del archivo XML que guardará los errores
require_once __DIR__ . '/../../clases/Auditoria.php';
$repositorioAuditoria = new Auditoria();

date_default_timezone_set("America/Argentina/Buenos_Aires");

define("ERROR_XML_FOLDER","/var/www/html/territorio/logs/");

// Crear carpeta si no existe
if (!file_exists(ERROR_XML_FOLDER)) {
    mkdir(ERROR_XML_FOLDER, 0777, true);
}
/**
 * Genera el nombre del archivo según la fecha actual
 * Formato: errores_ws_YYYY-MM-DD.xml
 */
function obtenerRutaErrorXML() {
    $fecha = date("Y-m-d");
    return ERROR_XML_FOLDER . "errores_territorio_" . $fecha . ".xml";
}

// Función para crear o agregar errores al archivo XML
function escribirErrorXML($tipo, $mensaje, $archivo, $linea) {
     
    $xmlPath = obtenerRutaErrorXML();

    // Crear archivo si no existe
     if (!file_exists($xmlPath)) {
        $xmlBase = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><errores></errores>');
        $xmlBase->asXML($xmlPath);
    }

    // Cargar XML existente
    $xml = simplexml_load_file($xmlPath);

    // Agregar nodo
    $error = $xml->addChild("error");
    $error->addChild("tipo", htmlspecialchars((string)$tipo, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
    $error->addChild("mensaje", htmlspecialchars((string)$mensaje, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
    $error->addChild("archivo", htmlspecialchars((string)$archivo, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
    $error->addChild("linea", (string)$linea);
    $error->addChild("fecha", date("Y-m-d H:i:s"));

    $dom = new DOMDocument("1.0", "UTF-8");
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    $dom->save($xmlPath);
}//fin escribirErrorXML


function detectarTipoError(?Throwable $e = null, ?int $errno = null): string
{
    if ($e instanceof PDOException) {
        return 'DB_ERROR';
    }

    if (class_exists('SoapFault') && $e instanceof SoapFault) {
        return 'SOAP_ERROR';
    }

    if ($e instanceof TypeError || $e instanceof Error) {
        return 'PHP_ERROR';
    }

    if ($e instanceof Exception) {
        return 'EXCEPCION';
    }

    if ($errno !== null) {
        return match ($errno) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'PHP_FATAL',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'PHP_WARNING',
            E_NOTICE, E_USER_NOTICE => 'PHP_NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'PHP_DEPRECATED',
            default => 'PHP_ERROR'
        };
    }

    return 'ERROR_GENERAL';
} //FIN detectarTipoError

function registrarErrorBD($repositorioAuditoria, array $data): void
{
    if ($repositorioAuditoria === null) {
        return;
    }

    try {
        $repositorioAuditoria->registrarAuditoriaError($data);
    } catch (Throwable $e) {
        // Evita bucle infinito si falla la auditoría
    }
} //FIN registrarErrorBD


function registrarManejadorErrores($repositorioAuditoria = null, string $modulo = 'SISTEMA'): void
{
    set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($repositorioAuditoria, $modulo) {

        if (!(error_reporting() & $errno)) {
            return false;
        }

        $tipo = detectarTipoError(null, $errno);

        escribirErrorXML($tipo, $errstr, $errfile, $errline);

        registrarErrorBD($repositorioAuditoria, [
            'siad_audit_error_idusu'   => $_SESSION['usuario_id'] ?? null,
            'siad_audit_error_idmod' => $modulo,
            'siad_audit_error_desctip_err'   => $tipo,
            'siad_audit_error_msje'      => $errstr,
            'siad_audit_error_archiv'      => $errfile,
            'siad_audit_error_linea'        => $errline,
            'siad_audit_error_codigo_err' => (string)$errno,
            'siad_audit_error_context'     => json_encode([
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'metodo' => $_SERVER['REQUEST_METHOD'] ?? null
            ], JSON_UNESCAPED_UNICODE)
        ]);

        return true;
    });


set_exception_handler(function ($exception) use ($repositorioAuditoria, $modulo) {
     $tipo = detectarTipoError($exception);

        escribirErrorXML(
            $tipo,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        registrarErrorBD($repositorioAuditoria, [
            'siad_audit_error_idusu'   => $_SESSION['usuario_id'] ?? null,
            'siad_audit_error_idmod' => $modulo,
            'siad_audit_error_desctip_err'   => $tipo,
            'siad_audit_error_msje'      => $exception->getMessage(),
            'siad_audit_error_archiv'      => $exception->getFile(),
            'siad_audit_error_linea'        => $exception->getLine(),
            'siad_audit_error_codigo_err' => (string)$exception->getCode(),
            'siad_audit_error_context'     => json_encode([
                'clase' => get_class($exception),
                'url' => $_SERVER['REQUEST_URI'] ?? null
            ], JSON_UNESCAPED_UNICODE)
        ]);

        http_response_code(500);
        echo "Ocurrió un error inesperado.";
});

register_shutdown_function(function () use ($repositorioAuditoria, $modulo) {

        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        if (!in_array($error['type'], $fatales, true)) {
            return;
        }

        $tipo = detectarTipoError(null, $error['type']);

        escribirErrorXML(
            $tipo,
            $error['message'],
            $error['file'],
            $error['line']
        );

        registrarErrorBD($repositorioAuditoria, [
            'siad_audit_error_idusu' => $_SESSION['usuario_id'] ?? null,
            'siad_audit_error_idmod' => $modulo,
            'siad_audit_error_desctip_err'   => $tipo,
            'siad_audit_error_msje'      => $error['message'],
            'siad_audit_error_archiv'      => $error['file'],
            'siad_audit_error_linea'        => $error['line'],
            'siad_audit_error_codigo_err' => (string)$error['type'],
            'siad_audit_error_context'     => json_encode([
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'metodo' => $_SERVER['REQUEST_METHOD'] ?? null
            ], JSON_UNESCAPED_UNICODE)
        ]);
    });
}

?>