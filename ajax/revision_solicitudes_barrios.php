<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    require_once __DIR__.'/../clases/Autorizacion.php';
    require_once __DIR__.'/../clases/RevisionSolicitudesBarrios.php';
    $auth=new Autorizacion();$auth->exigir('revision_solicitudes_barrios.php','ejec');
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new RuntimeException('Método no permitido.',405);
    $srv=new RevisionSolicitudesBarrios();$accion=(string)($_GET['accion']??'');
    if($accion==='listar')$result=['data'=>$srv->listar((string)($_GET['estado']??'PENDIENTE'))];
    elseif($accion==='detalle'){
       $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
       $result=$srv->detalle((int)$id);
    }else throw new InvalidArgumentException('Acción inválida.',400);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(Throwable $e){
    $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,405,409],true))$status=500;
    error_log('Territorio revisión solicitud barrio: '.$e->getMessage());
    http_response_code($status);
    echo json_encode(['error'=>$status===500?'No se pudo obtener la información. Revisar logs PHP.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
