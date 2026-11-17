<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
try {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    require_once __DIR__.'/../clases/Autorizacion.php';
    (new Autorizacion())->exigir('gestion_barrios.php','ejec');
    require_once __DIR__.'/../clases/HistorialTerritorial.php';
    $b=filter_input(INPUT_GET,'id_barrio',FILTER_VALIDATE_INT);
    if(!$b||$b<1)throw new InvalidArgumentException('ID de barrio inválido',400);
    $accion=(string)($_GET['accion']??'listar');
    $hist=new HistorialTerritorial();
    if($accion==='listar')$out=$hist->listar($b);
    elseif($accion==='geometria'){
        $g=filter_input(INPUT_GET,'id_geometria',FILTER_VALIDATE_INT);
        if(!$g||$g<1)throw new InvalidArgumentException('ID de geometría inválido',400);
        $out=$hist->geometria($b,$g);
    }else throw new InvalidArgumentException('Acción desconocida',400);
    // Nunca ocultar silenciosamente una falla de auditoria.
    // La lectura se entrega, pero el cliente recibira una advertencia verificable.
    require_once __DIR__.'/../clases/AuditoriaTerritorio.php';
    $usuario=(int)($_SESSION['usuario_id']??0);
    $auditOk=false;
    $auditId=null;
    try {
        if ($usuario <= 0) {
            throw new RuntimeException('No se pudo identificar el usuario autenticado para auditoria');
        }
        $auditId=(new AuditoriaTerritorio(Conexion::getConexion('BARRIOS')))->registrarAccion([
            'usuario'=>$usuario,
            'tipo'=>AuditoriaTerritorio::CONSULTA,
            'observacion'=>$accion==='listar' ? 'Consulta de historial territorial' : 'Visualización de versión geométrica histórica',
            'parametros'=>[
                'origen'=>'Gestion Barrios',
                'id_barrio'=>$b,
                'accion'=>$accion,
                'id_geometria'=>$accion==='geometria' ? $g : null,
            ],
        ]);
        $auditOk=$auditId>0;
        if (!$auditOk) throw new RuntimeException('INSERT de auditoria sin identificador confirmado');
    } catch (Throwable $e) {
        error_log('[TERRITORIO HISTORIAL AUDITORIA] usuario='.$usuario.' barrio='.$b.' accion='.$accion.' error='.$e->getMessage());
    }
    // Indicador de estado no sensible. Permite diagnosticar desde Network sin
    // exponer detalles de SQL, credenciales, sesiones ni rutas internas.
    header('X-Territorio-Auditoria: '.($auditOk ? 'registrada' : 'fallida'));
    if (is_array($out)) {
        $out['_auditoria']=['registrada'=>$auditOk,'id'=>$auditOk?$auditId:null];
    }
    echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,409],true))$status=500;
    http_response_code($status);error_log('[TERRITORIO HISTORIAL] '.$e->getMessage());
    echo json_encode(['error'=>$status===500?'No se pudo consultar el historial. Revisá los logs.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
