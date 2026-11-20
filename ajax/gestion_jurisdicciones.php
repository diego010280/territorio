<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 require_once __DIR__.'/../clases/Autorizacion.php';
 require_once __DIR__.'/../clases/AuditoriaTerritorio.php';
 require_once __DIR__.'/../clases/GestionJurisdicciones.php';
 require_once __DIR__.'/../clases/RevisionJurisdicciones.php';
 require_once __DIR__.'/../clases/ColorJurisdiccion.php';
 $auth=new Autorizacion();$auth->exigir('gestion_jurisdicciones.php','ejec');
 $srv=new GestionJurisdicciones(Conexion::getConexion('BARRIOS'));
 $accion=(string)($_GET['accion']??'');$usuario=(int)($_SESSION['usuario_id']??0);
 if($_SERVER['REQUEST_METHOD']==='GET') {
   if($accion==='solicitud_pendiente')$result=$srv->solicitudPendiente((int)($_GET['id']??0));
   elseif($accion==='consultar_color')$result=(new ColorJurisdiccion(Conexion::getConexion('BARRIOS')))->consultar((int)($_GET['id']??0));
   elseif($accion==='buscar_rrhh')$result=['data'=>$srv->buscarRRHH((string)($_GET['q']??''))];
   elseif($accion==='listar')$result=['data'=>$srv->listar((string)($_GET['q']??''))];
   elseif($accion==='listar_paginado')$result=$srv->listarPaginado($_GET);
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
   // ALTA: crea una jurisdicción preliminar o presenta una NUEVA solicitud.
   // MODIFICA: también puede presentar propuestas; nunca publica directamente.
   if($accion==='guardar_color') {
     $auth->exigir('gestion_jurisdicciones.php','modifica');
   } elseif($accion==='crear_desde_rrhh') {
     $auth->exigir('gestion_jurisdicciones.php','alta');
   } elseif($accion==='proponer_geometria'||$accion==='comprobar_solapamiento'||$accion==='corregir_solicitud') {
     if(!$auth->puede('gestion_jurisdicciones.php','alta') && !$auth->puede('gestion_jurisdicciones.php','modifica'))
       throw new RuntimeException('Se requiere permiso ALTA o MODIFICA para presentar una propuesta de jurisdicción.',403);
   } else {
     throw new RuntimeException('Acción desconocida.',400);
   }
   $csrf=(string)($_SESSION['terr_jurisdicciones_csrf']??'');
   if($csrf===''||!hash_equals($csrf,(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))throw new RuntimeException('CSRF inválido.',403);
   $body=file_get_contents('php://input');if(strlen($body)>600000)throw new RuntimeException('Solicitud demasiado grande.',400);
   $v=json_decode($body,true,512,JSON_THROW_ON_ERROR);if(!is_array($v))throw new RuntimeException('JSON inválido.',400);
   if($accion==='guardar_color')$result=(new ColorJurisdiccion(Conexion::getConexion('BARRIOS')))->guardar((int)($v['id_jurisdiccion']??0),(string)($v['color']??''),(string)($v['motivo']??''),$usuario);
   elseif($accion==='crear_desde_rrhh')$result=$srv->crearDesdeRRHH((int)($v['id_rrhh']??0),$usuario);
   elseif($accion==='comprobar_solapamiento'){
     $result=$srv->comprobarSolapamiento((int)($v['id_jurisdiccion']??0),(array)($v['geometria']??[]));
     if($usuario>0)(new AuditoriaTerritorio(Conexion::getConexion('BARRIOS')))->registrarAccionSegura([
       'usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::CONSULTA,
       'observacion'=>'Diagnostico de solapamiento de jurisdiccion',
       'parametros'=>['id_jurisdiccion'=>(int)($v['id_jurisdiccion']??0),'conflictos'=>count($result['conflictos']),'indeterminados'=>count($result['indeterminados'])]]);
   }
   elseif($accion==='corregir_solicitud')$result=$srv->corregirSolicitud((int)($v['id_solicitud']??0),(int)($v['revision_actual']??0),(int)($v['id_jurisdiccion']??0),(array)($v['geometria']??[]),(string)($v['motivo']??''),$usuario);
   elseif($accion==='proponer_geometria')$result=$srv->proponerGeometria((int)($v['id_jurisdiccion']??0),(array)($v['geometria']??[]),(string)($v['motivo']??''),$usuario);
   else throw new RuntimeException('Acción desconocida.',400);
 } else throw new RuntimeException('Método no permitido.',405);
 echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
 $c=(int)$e->getCode();if(!in_array($c,[400,401,403,404,405,409],true))$c=500;
 error_log('[TERRITORIO JURISDICCIONES] '.$e->getMessage());http_response_code($c);
 echo json_encode(['error'=>$c===500?'Error al procesar jurisdicciones. Consultar log del servidor.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
