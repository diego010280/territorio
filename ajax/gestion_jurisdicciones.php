<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 require_once __DIR__.'/../clases/Autorizacion.php';
 require_once __DIR__.'/../clases/AuditoriaTerritorio.php';
 require_once __DIR__.'/../clases/GestionJurisdicciones.php';
 $auth=new Autorizacion();$auth->exigir('gestion_jurisdicciones.php','ejec');
 $srv=new GestionJurisdicciones(Conexion::getConexion('BARRIOS'));
 $accion=(string)($_GET['accion']??'');$usuario=(int)($_SESSION['usuario_id']??0);
 if($_SERVER['REQUEST_METHOD']==='GET') {
   if($accion==='buscar_rrhh')$result=['data'=>$srv->buscarRRHH((string)($_GET['q']??''))];
   elseif($accion==='listar')$result=['data'=>$srv->listar((string)($_GET['q']??''))];
   elseif($accion==='capas') {
     $estado=(string)($_GET['estado']??'APROBADO');
     if($estado==='BORRADOR')$auth->exigir('gestion_jurisdicciones.php','ejec');
     $id=isset($_GET['id']) ? filter_var($_GET['id'],FILTER_VALIDATE_INT) : null;
     if($id===false||($id!==null&&$id<1))throw new InvalidArgumentException('ID inválido.',400);
     $result=$srv->capas($estado,$id,200);
   }
   else throw new InvalidArgumentException('Acción desconocida.',400);
   if($usuario>0)(new AuditoriaTerritorio(Conexion::getConexion('BARRIOS')))->registrarAccionSegura([
      'usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::BUSQUEDA,'observacion'=>'Consulta de jurisdicciones / organismos RRHH',
      'parametros'=>['accion'=>$accion,'q'=>mb_substr((string)($_GET['q']??''),0,90)]]);
 } elseif($_SERVER['REQUEST_METHOD']==='POST') {
   $auth->exigir('gestion_jurisdicciones.php',$accion==='crear_desde_rrhh'?'alta':'modifica');
   $csrf=(string)($_SESSION['terr_jurisdicciones_csrf']??'');
   if($csrf===''||!hash_equals($csrf,(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))throw new RuntimeException('CSRF inválido.',403);
   $body=file_get_contents('php://input');if(strlen($body)>600000)throw new RuntimeException('Solicitud demasiado grande.',400);
   $v=json_decode($body,true,512,JSON_THROW_ON_ERROR);if(!is_array($v))throw new RuntimeException('JSON inválido.',400);
   if($accion==='crear_desde_rrhh')$result=$srv->crearDesdeRRHH((int)($v['id_rrhh']??0),$usuario);
   elseif($accion==='proponer_geometria')$result=$srv->proponerGeometria((int)($v['id_jurisdiccion']??0),(array)($v['geometria']??[]),(string)($v['motivo']??''),$usuario);
   else throw new RuntimeException('Acción desconocida.',400);
 } else throw new RuntimeException('Método no permitido.',405);
 echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
 $c=(int)$e->getCode();if(!in_array($c,[400,401,403,404,405,409],true))$c=500;
 error_log('[TERRITORIO JURISDICCIONES] '.$e->getMessage());http_response_code($c);
 echo json_encode(['error'=>$c===500?'Error al procesar jurisdicciones. Consultar log del servidor.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
