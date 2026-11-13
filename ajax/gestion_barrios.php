<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    require_once __DIR__ . '/../clases/Autorizacion.php';
    (new Autorizacion())->exigir('gestion_barrios.php','ejec');
    require_once __DIR__ . '/../clases/GestionBarrios.php';
    $obj=new GestionBarrios();
    $accion=(string)($_GET['accion'] ?? 'listar');
    if ($accion==='listar') $out=$obj->listar($_GET);
    elseif ($accion==='sugerencias') {
        $q=trim((string)($_GET['q']??''));
        if(mb_strlen($q)<2)throw new InvalidArgumentException('Ingrese dos caracteres',400);
        $out=['data'=>$obj->sugerencias(mb_substr($q,0,80),(string)($_GET['vista']??'principales'))];
    }
    elseif ($accion==='localidades') $out=['data'=>$obj->localidades()];
    elseif ($accion==='detalle') {
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if (!$id || $id < 1) throw new InvalidArgumentException('ID inválido',400);
        $out=$obj->detalle((int)$id);
        if ($out===null) throw new RuntimeException('Barrio inexistente',404);
    } else throw new InvalidArgumentException('Acción desconocida',400);
    // Registrar acciones del usuario en la auditoria central. Las consultas no se
    // bloquean por fallo de auditoria, pero los fallos se registran en el log PHP.
    if (in_array($accion,['listar','detalle'],true)) {
        require_once __DIR__ . '/../clases/AuditoriaTerritorio.php';
        require_once __DIR__ . '/../config/database.php';
        $usuarioAud=(int)($_SESSION['usuario_id']??0);
        if($usuarioAud>0){
            $tipoAud=$accion==='detalle'?AuditoriaTerritorio::CONSULTA:AuditoriaTerritorio::BUSQUEDA;
            $paramAud=$accion==='detalle'
                ? ['id_barrio'=>(int)($_GET['id']??0)]
                : [
                    'vista'=>mb_substr((string)($_GET['vista']??'principales'),0,30),
                    'nombre'=>mb_substr(trim((string)($_GET['nombre']??'')),0,150),
                    'localidad'=>mb_substr((string)($_GET['localidad']??''),0,30),
                    'estado'=>mb_substr((string)($_GET['estado']??''),0,30),
                    'id_barrio'=>mb_substr((string)($_GET['id_barrio']??''),0,30),
                    'pagina_inicio'=>max(0,(int)($_GET['start']??0)),
                    'cantidad'=>max(0,min(100,(int)($_GET['length']??10))),
                    'total_filtrados'=>($accion==='listar'?(int)($out['recordsFiltered']??0):null),
                ];
            (new AuditoriaTerritorio(Conexion::getConexion('BARRIOS')))->registrarAccionSegura([
                'usuario'=>$usuarioAud,
                'tipo'=>$tipoAud,
                'observacion'=>$accion==='detalle'?'Visualizacion de detalle del barrio':'Busqueda/listado de barrios desde Gestion Barrios',
                'parametros'=>$paramAud,
            ]);
        }else error_log('[TERRITORIO] Sin usuario_id de sesion para auditar Gestion Barrios');
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $status=(int)$e->getCode(); if (!in_array($status,[400,401,403,404],true)) $status=500;
    http_response_code($status);
    error_log('Territorio gestion barrios: '.$e->getMessage());
    echo json_encode(['error'=>$status===500?'No se pudo completar la consulta. Revisar logs del servidor.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
