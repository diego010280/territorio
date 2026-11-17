<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
try {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    require_once __DIR__.'/../clases/Autorizacion.php';
    (new Autorizacion())->exigir('auditoria_territorio.php','ejec');
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Método no permitido',405);
    $csrf=(string)($_POST['csrf']??'');
    if(!$csrf || !hash_equals((string)($_SESSION['terr_auditoria_csrf']??''),$csrf))throw new RuntimeException('Token inválido, actualizá la página',403);
    require_once __DIR__.'/../clases/AuditoriaConsultaTerritorio.php';
    require_once __DIR__.'/../clases/AuditoriaTerritorio.php';
    $svc=new AuditoriaConsultaTerritorio();
    $accion=(string)($_POST['accion']??'listar');
    $usuario=(int)($_SESSION['usuario_id']??0);
    if($usuario<1)throw new RuntimeException('Sesión inválida',401);
    $audit=new AuditoriaTerritorio($svc->conexion());
    $f=AuditoriaConsultaTerritorio::filtros($_POST);
    if($accion==='tipos'){
        $out=['data'=>$svc->tipos()];
    }elseif($accion==='listar'){
        $inicio=max(0,min(1000000,(int)($_POST['start']??0)));
        $limite=max(1,min(100,(int)($_POST['length']??15)));
        $out=$svc->listar($f,$inicio,$limite);
        // También la consulta de auditoría queda registrada; no silenciar fallos.
        $audit->registrarAccion(['usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::BUSQUEDA,'observacion'=>'Consulta de auditoría Territorio','parametros'=>array_merge($f,['inicio'=>$inicio,'limite'=>$limite,'resultados'=>$out['recordsFiltered']])]);
        $out['draw']=max(0,(int)($_POST['draw']??1));
    }elseif($accion==='detalle'){
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)throw new InvalidArgumentException('Identificador inválido',400);
        $out=['data'=>$svc->detalle($f['vista'],$id)];
        $audit->registrarAccion(['usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::CONSULTA,'observacion'=>'Consulta detalle de auditoría Territorio','parametros'=>['vista'=>$f['vista'],'id'=>$id]]);
    }elseif($accion==='exportar'){
        $formato=(string)($_POST['formato']??'');
        $tipos=['excel'=>AuditoriaTerritorio::EXPORTAR_EXCEL,'pdf'=>AuditoriaTerritorio::EXPORTAR_PDF,'print'=>AuditoriaTerritorio::IMPRIMIR];
        if(!isset($tipos[$formato]))throw new InvalidArgumentException('Formato no permitido',400);
        $tope=$formato==='excel'?10000:300;
        $filas=$svc->exportar($f,$tope+1);
        if(count($filas)>$tope)throw new RuntimeException('La exportación supera '.$tope.' registros; acotá los filtros o el rango de fechas.',409);
        // La exportación se audita antes de entregar datos.
        $audit->registrarAccion(['usuario'=>$usuario,'tipo'=>$tipos[$formato],'observacion'=>'Exportación de auditoría Territorio','parametros'=>array_merge($f,['formato'=>$formato,'filas'=>count($filas)])]);
        if($formato==='excel'){
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="auditoria_territorio_'.$f['vista'].'_'.date('Ymd_His').'.csv"');
            echo "\xEF\xBB\xBF";
            $h=fopen('php://output','wb');
            fputcsv($h,['ID','Fecha','Usuario','Módulo','Tipo','Tabla','Registro','Observación','Correlación','IP']);
            foreach($filas as $r){
                $v=[];foreach(['id','fecha','usuario_id','modulo_id','tipo','tabla','registro','observacion','correlacion','ip'] as $k){
                    $val=(string)($r[$k]??'');
                    if(preg_match('/^\s*[=+@-]/u',$val))$val="'".$val;
                    $v[]=$val;
                }fputcsv($h,$v);
            }
            fclose($h);exit;
        }
        // Impresión / Guardar PDF: HTML generado en el navegador mediante su diálogo de impresión.
        $out=['data'=>$filas,'formato'=>$formato,'limite'=>$tope];
    }else throw new InvalidArgumentException('Operación desconocida',400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,405,409],true))$status=500;
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');
    error_log('[AUDITORIA TERRITORIO] '.$e->getMessage());
    echo json_encode(['error'=>$status===500?'Error al consultar o auditar. Revisá el log PHP.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
