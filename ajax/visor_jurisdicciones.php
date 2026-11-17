<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    $user=(int)($_SESSION['usuario_id']??0);
    $modulo=(int)($_SESSION['moduleid']??0);
    if($user<1||$modulo!==1239) throw new RuntimeException('Sesión no autorizada en Territorio.',401);
    require_once __DIR__.'/../config/database.php';
    require_once __DIR__.'/../clases/GestionJurisdicciones.php';
    $estado=(string)($_GET['estado']??'APROBADO');
    if(!in_array($estado,['BORRADOR','APROBADO'],true))throw new InvalidArgumentException('Capa inválida.',400);
    // Los borradores requieren permiso específico. Nunca se muestran como oficiales.
    if($estado==='BORRADOR') {
        require_once __DIR__.'/../clases/Autorizacion.php';
        (new Autorizacion())->exigir('gestion_jurisdicciones.php','ejec');
    }
    $db=Conexion::getConexion('BARRIOS');
    $result=(new GestionJurisdicciones($db))->capas($estado,null,200);
    if(class_exists('AuditoriaTerritorio',false) || is_file(__DIR__.'/../clases/AuditoriaTerritorio.php')) {
        require_once __DIR__.'/../clases/AuditoriaTerritorio.php';
        (new AuditoriaTerritorio($db))->registrarAccionSegura([
           'usuario'=>$user,'tipo'=>AuditoriaTerritorio::CONSULTA,
           'observacion'=>'Visor: capa de jurisdicciones '.$estado,
           'parametros'=>['estado'=>$estado,'cantidad'=>count($result['features'])]
        ]);
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
    $code=(int)$e->getCode();if(!in_array($code,[400,401,403],true))$code=500;
    if($code===500)error_log('[TERRITORIO CAPAS JURISDICCIONES] '.$e->getMessage());
    http_response_code($code);
    echo json_encode(['error'=>$code===500?'No se pudo cargar la capa jurisdiccional.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
