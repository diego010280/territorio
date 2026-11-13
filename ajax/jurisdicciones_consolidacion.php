<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
  if(session_status()!==PHP_SESSION_ACTIVE)session_start();
  require_once __DIR__.'/../clases/Autorizacion.php';
  require_once __DIR__.'/../clases/JurisdiccionesConsolidacion.php';
  $auth=new Autorizacion();$auth->exigir('gestion_barrios.php','modifica');
  $srv=new JurisdiccionesConsolidacion();$accion=(string)($_GET['accion']??'');
  if($_SERVER['REQUEST_METHOD']==='GET'){
    if($accion==='buscar')$out=['data'=>$srv->buscar((string)($_GET['q']??''))];
    elseif($accion==='detalle'){
      $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
      if(!$id||$id<1)throw new InvalidArgumentException('Consolidación inválida.',400);
      $out=$srv->detalle((int)$id);
    }else throw new InvalidArgumentException('Acción desconocida.',400);
  }elseif($_SERVER['REQUEST_METHOD']==='POST'&&$accion==='guardar'){
    $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if(empty($_SESSION['geo_csrf'])||!hash_equals($_SESSION['geo_csrf'],$token))throw new RuntimeException('Token CSRF inválido.',403);
    $raw=file_get_contents('php://input');if(strlen($raw)>65536)throw new InvalidArgumentException('Solicitud demasiado grande.',400);
    $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($d)||!is_array($d['items']??null))throw new InvalidArgumentException('Datos JSON inválidos.',400);
    $id=filter_var($d['id_consolidacion']??null,FILTER_VALIDATE_INT);
    if(!$id||$id<1)throw new InvalidArgumentException('Consolidación inválida.',400);
    $out=$srv->guardar((int)$id,$d['items'],(int)($_SESSION['usuario_id']??0),
      (string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??''));
  }else throw new RuntimeException('Método no permitido.',405);
  echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
  $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,405,409],true))$status=500;
  http_response_code($status);
  $codigo='TERR-JUR-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
  error_log($codigo.' | Jurisdicciones consolidación | '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>$status===500?'No se pudo registrar. Código: '.$codigo:$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
