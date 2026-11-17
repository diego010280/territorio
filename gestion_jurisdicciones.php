<?php
declare(strict_types=1);
$usarDataTables=false;
require_once __DIR__.'/segacceso/segacceso.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/administracion/autorizaciones_perfil.php';
require_once __DIR__.'/clases/Autorizacion.php';
$auth=new Autorizacion();$auth->exigir('gestion_jurisdicciones.php','ejec');
if(empty($_SESSION['terr_jurisdicciones_csrf']))$_SESSION['terr_jurisdicciones_csrf']=bin2hex(random_bytes(32));
$perm=$auth->permisos('gestion_jurisdicciones.php');
$tituloPagina='Gestión de Jurisdicciones';$paginaActiva='gestion_jurisdicciones';
require_once __DIR__.'/templates/header.php';
require_once __DIR__.'/templates/navbar.php';
require_once __DIR__.'/templates/menu.php';
?>
<main class="app-main">
<?php require __DIR__.'/views/jurisdicciones/gestion.php'; ?>
</main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL = '/territorio';</script>
<?php require_once __DIR__.'/templates/footer.php'; ?>
<?php require_once __DIR__.'/templates/scripts.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="/territorio/assets/js/gestion-jurisdicciones.js?v=5"></script>
</body></html>
