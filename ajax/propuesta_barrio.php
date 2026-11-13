<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 require_once __DIR__.'/../clases/Autorizacion.php';
 require_once __DIR__.'/../clases/PropuestaBarrios.php';
 $auth=new Autorizacion();$action=(string)($_GET['accion']??'');$service=new PropuestaBarrios();
 if($_SERVER['REQUEST_METHOD']==='GET'&&$action==='clasificaciones'){
   $auth->exigir('gestion_barrios.php','ejec');$out=['data'=>$service->clasificaciones()];
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='localidades'){
   $auth->exigir('gestion_barrios.php','ejec');$q=trim((string)($_GET['q']??''));if(mb_strlen($q)<2)throw new InvalidArgumentException('Ingresar al menos dos caracteres.');
   $out=['data'=>$service->localidades(mb_substr($q,0,80))];
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='coincidencias'){
   $auth->exigir('gestion_barrios.php','ejec');$q=trim((string)($_GET['q']??''));if(mb_strlen($q)<2)throw new InvalidArgumentException('Ingresar al menos dos caracteres.');
   $out=['data'=>$service->coincidencias(mb_substr($q,0,80))];
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='municipales_buscar'){
   $auth->exigir('gestion_barrios.php','ejec');
   $out=['data'=>$service->buscarMunicipales((string)($_GET['q']??''))];
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='municipal_geometria'){
   $auth->exigir('gestion_barrios.php','ejec');
   $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
   if(!$id||$id<1)throw new InvalidArgumentException('Identificador municipal inválido.');
   $out=$service->geometriaMunicipal((int)$id);
   if(!$out)throw new RuntimeException('Geometría municipal no encontrada.',404);
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='detalle'){
   $auth->exigir('gestion_barrios.php','modifica');$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id||$id<1)throw new InvalidArgumentException('ID inválido.');
   $out=$service->detalle((int)$id);if(!$out)throw new RuntimeException('Barrio inexistente.',404);
 }elseif($_SERVER['REQUEST_METHOD']==='POST'&&$action==='guardar'){
   $body=file_get_contents('php://input');if(strlen($body)>4*1024*1024)throw new InvalidArgumentException('Solicitud demasiado grande.');
   $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('Datos inválidos.');
   $auth->exigir('gestion_barrios.php',!empty($data['id_barrio'])?'modifica':'alta');
   $csrf=(string)($_SESSION['geo_csrf']??'');$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
   if($csrf===''||!hash_equals($csrf,$token))throw new RuntimeException('Token CSRF inválido.',403);
   $out=['id_solicitud'=>$service->guardar($data,(int)($_SESSION['usuario_id']??0)),'estado'=>'PENDIENTE'];
 }else throw new RuntimeException('Acción no disponible.',405);
 echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,405,409],true))$status=$e instanceof InvalidArgumentException?400:500;
 http_response_code($status);error_log('Territorio propuesta barrio: '.$e->getMessage());
 echo json_encode(['error'=>$status===500?'Error al registrar o consultar. Verificar estructura de tablas y logs PHP.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
