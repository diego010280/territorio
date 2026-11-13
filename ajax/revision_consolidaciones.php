<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 require_once __DIR__.'/../clases/Autorizacion.php';
 require_once __DIR__.'/../clases/RevisionConsolidaciones.php';
 require_once __DIR__.'/../clases/PublicarConsolidacion.php';
 $auth=new Autorizacion();$auth->exigir('revision_consolidaciones.php','ejec');
 $srv=new RevisionConsolidaciones();$a=(string)($_GET['accion']??'');
 if($_SERVER['REQUEST_METHOD']==='GET' && $a==='listar'){
    $out=['data'=>$srv->listar((string)($_GET['estado']??'PENDIENTE'))];
 } elseif($_SERVER['REQUEST_METHOD']==='GET' && $a==='detalle'){
    $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
    $out=$srv->detalle((int)$id);
 }elseif($_SERVER['REQUEST_METHOD']==='POST' && $a==='rechazar'){
    $auth->exigir('revision_consolidaciones.php','modifica');
    $csrf=(string)($_SESSION['geo_csrf']??'');$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if(!$csrf || !hash_equals($csrf,$token))throw new RuntimeException('Token CSRF incorrecto.',403);
    $raw=file_get_contents('php://input');if(strlen($raw)>16384)throw new InvalidArgumentException('Solicitud demasiado grande.',400);
    $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new InvalidArgumentException('Datos incorrectos.',400);
    $id=filter_var($data['id_consolidacion']??null,FILTER_VALIDATE_INT);
    $srv->rechazar((int)$id,(int)($_SESSION['usuario_id']??0),(string)($data['motivo']??''));
    $out=['ok'=>true,'estado'=>'RECHAZADA'];
 }elseif($_SERVER['REQUEST_METHOD']==='POST' && $a==='resolver_jurisdiccion'){
    $auth->exigir('revision_consolidaciones.php','modifica');
    $csrf=(string)($_SESSION['geo_csrf']??'');$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if(!$csrf||!hash_equals($csrf,$token))throw new RuntimeException('Token CSRF incorrecto.',403);
    $raw=file_get_contents('php://input');if(strlen($raw)>16384)throw new InvalidArgumentException('Solicitud demasiado grande.',400);
    $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new InvalidArgumentException('Datos incorrectos.',400);
    $id=filter_var($data['id_consolidacion']??null,FILTER_VALIDATE_INT);
    $dep=filter_var($data['gen_organismos_id']??null,FILTER_VALIDATE_INT);
    $out=$srv->resolverJurisdiccion((int)$id,(int)$dep,(int)($_SESSION['usuario_id']??0),
           (string)($data['decision']??''),(string)($data['fundamento']??''));
 }elseif($_SERVER['REQUEST_METHOD']==='POST' && in_array($a,['simular_aprobacion','aprobar'],true)){
    $auth->exigir('revision_consolidaciones.php','modifica');
    $csrf=(string)($_SESSION['geo_csrf']??'');$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if(!$csrf || !hash_equals($csrf,$token))throw new RuntimeException('Token CSRF incorrecto.',403);
    $raw=file_get_contents('php://input');if(strlen($raw)>16384)throw new InvalidArgumentException('Solicitud demasiado grande.',400);
    $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    $id=filter_var($data['id_consolidacion']??null,FILTER_VALIDATE_INT);
    if(!$id || $id<1)throw new InvalidArgumentException('Identificador inválido.',400);
    $usuario=(int)($_SESSION['usuario_id']??0);
    $pub=new PublicarConsolidacion();
    if($a==='simular_aprobacion') $out=$pub->simular((int)$id,$usuario);
    else {
      if(($data['confirmacion']??'')!=='APROBAR')throw new InvalidArgumentException('Confirmación administrativa incorrecta.',400);
      if(($data['revision_cartografica']??false)!==true)throw new InvalidArgumentException('Debe confirmar revisión cartográfica presencial/documental.',400);
      $out=$pub->aprobar((int)$id,$usuario,(string)($data['fundamento']??''));
    }
 }else throw new RuntimeException('Operación no permitida.',405);
 echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,405,409],true))$status=$e instanceof InvalidArgumentException?400:500;
 $ref='TERR-REV-'.date('YmdHis').'-'.bin2hex(random_bytes(3));error_log($ref.' | '.get_class($e).' | '.$e->getMessage());
 http_response_code($status); echo json_encode(['ok'=>false,'error'=>$status===500?'Error interno. Referencia: '.$ref:$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
