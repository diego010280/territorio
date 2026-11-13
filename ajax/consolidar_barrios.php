<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 require_once __DIR__.'/../clases/Autorizacion.php';
 require_once __DIR__.'/../clases/ConsolidacionBarrios.php';
 $a=new Autorizacion();$a->exigir('gestion_barrios.php','modifica');
 $srv=new ConsolidacionBarrios();$accion=(string)($_GET['accion']??'');
 if($_SERVER['REQUEST_METHOD']==='GET') {
  if($accion==='localidades'||$accion==='barrios'||$accion==='municipales') {
   $q=trim((string)($_GET['q']??''));if(mb_strlen($q)<2 || mb_strlen($q)>100)throw new InvalidArgumentException('Escribí entre 2 y 100 caracteres.',400);
   if($accion==='localidades')$out=['data'=>$srv->localidades($q)];
   elseif($accion==='municipales')$out=['data'=>$srv->municipales($q)];
   else { $loc=filter_input(INPUT_GET,'localidad',FILTER_VALIDATE_INT);if(!$loc)throw new InvalidArgumentException('Seleccioná localidad.',400);$out=['data'=>$srv->barrios((int)$loc,$q)];}
  }elseif($accion==='surc_buscar'){
    $q=trim((string)($_GET['q']??''));
    $loc=filter_input(INPUT_GET,'localidad',FILTER_VALIDATE_INT);
    if(!$loc)throw new InvalidArgumentException('Seleccioná localidad primero.',400);
    $out=$srv->buscarSurc($q,(int)$loc);
  }elseif($accion==='surc'){$codigo=filter_input(INPUT_GET,'codigo',FILTER_VALIDATE_INT);if(!$codigo)throw new InvalidArgumentException('Código SURC inválido.',400);$out=['data'=>$srv->surc((int)$codigo)];}
  else throw new InvalidArgumentException('Acción desconocida.',400);
 }elseif($_SERVER['REQUEST_METHOD']==='POST'&&$accion==='guardar') {
  $csrf=(string)($_SESSION['geo_csrf']??'');$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
  if(!$csrf||!hash_equals($csrf,$token))throw new RuntimeException('Token CSRF inválido.',403);
  $raw=file_get_contents('php://input');if(strlen($raw)>65536)throw new InvalidArgumentException('Solicitud demasiado grande.',400);
  $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($d))throw new InvalidArgumentException('JSON inválido.',400);
  $out=['ok'=>true,'id_consolidacion'=>$srv->guardar($d,(int)($_SESSION['usuario_id']??0)),'estado'=>'PENDIENTE'];
 }else throw new RuntimeException('Acción no permitida.',405);
 echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,405],true))$status=$e instanceof InvalidArgumentException?400:500;http_response_code($status);$id='TERR-CONS-'.date('YmdHis').'-'.bin2hex(random_bytes(3));error_log($id.' | '.$e->getMessage());echo json_encode(['ok'=>false,'error'=>$status===500?'No se pudo registrar la consolidación. Código: '.$id:$e->getMessage(),'codigo'=>$id],JSON_UNESCAPED_UNICODE);}
