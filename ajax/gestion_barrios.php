<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    require_once __DIR__ . '/../clases/Autorizacion.php';
    (new Autorizacion())->exigir('gestion_barrios.php','ejec');
    require_once __DIR__ . '/../clases/GestionBarrios.php';
    $obj=new GestionBarrios();
    $accion=(string)($_GET['accion'] ?? 'listar');
    if ($accion==='listar') $out=$obj->listar($_GET);
    elseif ($accion==='sugerencias') {
        $q=trim((string)($_GET['q']??''));
        if(mb_strlen($q)<2)throw new InvalidArgumentException('Ingrese dos caracteres',400);
        $out=['data'=>$obj->sugerencias(mb_substr($q,0,80),(string)($_GET['vista']??'principales'))];
    }
    elseif ($accion==='localidades') $out=['data'=>$obj->localidades()];
    elseif ($accion==='detalle') {
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if (!$id || $id < 1) throw new InvalidArgumentException('ID inválido',400);
        $out=$obj->detalle((int)$id);
        if ($out===null) throw new RuntimeException('Barrio inexistente',404);
    } else throw new InvalidArgumentException('Acción desconocida',400);
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $status=(int)$e->getCode(); if (!in_array($status,[400,401,403,404],true)) $status=500;
    http_response_code($status);
    error_log('Territorio gestion barrios: '.$e->getMessage());
    echo json_encode(['error'=>$status===500?'No se pudo completar la consulta. Revisar logs del servidor.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
