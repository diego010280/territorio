<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    require_once __DIR__.'/../clases/Autorizacion.php';
    $auth=new Autorizacion();
    $auth->exigir('gestion_barrios.php','modifica');
    require_once __DIR__.'/../clases/ColorBarrio.php';
    $servicio=new ColorBarrio();
    $accion=(string)($_GET['accion']??'');
    if($_SERVER['REQUEST_METHOD']==='GET'&&$accion==='consultar') {
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if (!$id || $id<1)throw new InvalidArgumentException('ID inválido',400);
        $out=$servicio->consultar((int)$id);
    } elseif($_SERVER['REQUEST_METHOD']==='POST'&&$accion==='guardar') {
        $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
        if(!$token || !hash_equals((string)($_SESSION['terr_color_csrf']??''),$token))
            throw new RuntimeException('Token de seguridad inválido. Recargá la página.',403);
        $data=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new InvalidArgumentException('Datos inválidos',400);
        $out=$servicio->guardar((int)($data['id_barrio']??0),(string)($data['color_mapa']??''),(int)($_SESSION['usuario_id']??0));
    } else {throw new InvalidArgumentException('Acción o método desconocido',400);}
    echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
    $status=(int)$e->getCode();if(!in_array($status,[400,401,403,404,409],true))$status=500;
    http_response_code($status);
    error_log('Territorio color: '.$e->getMessage());
    echo json_encode(['error'=>$status===500?'No se pudo guardar el color. Revisá el log del servidor.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
