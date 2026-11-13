<?php
declare(strict_types=1);
$usarDataTables=false;
require_once __DIR__.'/segacceso/segacceso.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/administracion/autorizaciones_perfil.php';
require_once __DIR__.'/clases/Autorizacion.php';
$auth=new Autorizacion();
$auth->exigir('revision_consolidaciones.php','ejec');
if(empty($_SESSION['geo_csrf']))$_SESSION['geo_csrf']=bin2hex(random_bytes(32));
$tituloPagina='Revisión de Consolidaciones';$paginaActiva='gestion_barrios';
require_once __DIR__.'/templates/header.php';
require_once __DIR__.'/templates/navbar.php';
require_once __DIR__.'/templates/menu.php';
?>
<main class="app-main"><?php require __DIR__.'/views/barrios/revision_consolidaciones.php';?></main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL='/territorio';window.GEO_CSRF=<?=json_encode($_SESSION['geo_csrf'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<?php require_once __DIR__.'/templates/footer.php';require_once __DIR__.'/templates/scripts.php';?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/territorio/assets/css/revision-consolidaciones.css?v=1">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/territorio/assets/js/revision-consolidaciones.js?v=2"></script>
</body></html>
