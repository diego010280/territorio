<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 require_once __DIR__.'/../clases/Autorizacion.php';
 require_once __DIR__.'/../clases/AuditoriaTerritorio.php';
 require_once __DIR__.'/../clases/RevisionJurisdicciones.php';
 $auth=new Autorizacion();$auth->exigir('revision_jurisdicciones.php','ejec');
 $db=Conexion::getConexion('BARRIOS');$srv=new RevisionJurisdicciones($db);
 $user=(int)($_SESSION['usuario_id']??0);$accion=(string)($_GET['accion']??'');
 if($_SERVER['REQUEST_METHOD']==='GET'){
   if($accion==='listar')$result=['data'=>$srv->listar()];
   elseif($accion==='detalle')$result=$srv->detalle((int)($_GET['id']??0));
   else throw new InvalidArgumentException('Acción de revisión inexistente.',400);
   (new AuditoriaTerritorio($db))->registrarAccionSegura(['usuario'=>$user,'tipo'=>AuditoriaTerritorio::CONSULTA,
       'observacion'=>'Consulta revisión de jurisdicciones','parametros'=>['accion'=>$accion,'solicitud'=>(int)($_GET['id']??0)]]);
 }elseif($_SERVER['REQUEST_METHOD']==='POST'&&$accion==='resolver'){
   $auth->exigir('revision_jurisdicciones.php','modifica');
   $csrf=(string)($_SESSION['terr_revision_juris_csrf']??'');
   if(!$csrf||!hash_equals($csrf,(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))throw new RuntimeException('CSRF inválido.',403);
   $raw=file_get_contents('php://input');if(strlen($raw)>12000)throw new RuntimeException('Datos demasiado grandes.',400);
   $v=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($v))throw new InvalidArgumentException('JSON inválido.',400);
   $result=$srv->resolver((int)($v['id_solicitud']??0),(string)($v['decision']??''),(string)($v['fundamento']??''),$user,($v['confirmado']??false)===true);
 }else throw new RuntimeException('Método no permitido.',405);
 echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 $c=(int)$e->getCode();if(!in_array($c,[400,401,403,404,405,409],true))$c=500;
 error_log('[TERRITORIO REVISION JURISDICCIONES] '.$e->getMessage());http_response_code($c);
 echo json_encode(['error'=>$c===500?'Error interno al revisar. Consultar logs.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
