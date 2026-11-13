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
    $accion=(string)($_GET['accion']??'');
    if($_SERVER['REQUEST_METHOD']==='POST' && $accion==='resolver'){
        $auth->exigir('revision_solicitudes_barrios.php','modifica');
        $csrf=(string)($_SESSION['terr_revision_csrf']??'');
        $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
        if(!$csrf||!hash_equals($csrf,$token))throw new RuntimeException('Token CSRF inválido.',403);
        $body=file_get_contents('php://input');
        if(strlen($body)>16000)throw new InvalidArgumentException('Solicitud demasiado extensa.',400);
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new InvalidArgumentException('Formato inválido.',400);
        require_once __DIR__.'/../clases/ResolucionSolicitudesBarrios.php';
        $out=(new ResolucionSolicitudesBarrios())->resolver(
          (int)($data['id_solicitud']??0),(string)($data['decision']??''),
          (string)($data['motivo']??''),(int)($_SESSION['usuario_id']??0),
          ($data['confirmacion_geometria']??false)===true);
        echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new RuntimeException('Método no permitido.',405);
    $srv=new RevisionSolicitudesBarrios();
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
