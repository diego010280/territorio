<?php
declare(strict_types=1);
$usarDataTables = false;
require_once __DIR__.'/segacceso/segacceso.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/administracion/autorizaciones_perfil.php';
require_once __DIR__.'/clases/Autorizacion.php';
$auth=new Autorizacion();
$auth->exigir('revision_solicitudes_barrios.php','ejec');
$puedeResolver=$auth->puede('revision_solicitudes_barrios.php','modifica');
if(empty($_SESSION['terr_revision_csrf']))$_SESSION['terr_revision_csrf']=bin2hex(random_bytes(32));
$tituloPagina='Revisión de Solicitudes de Barrios';
$paginaActiva='gestion_barrios';
require_once __DIR__.'/templates/header.php';
require_once __DIR__.'/templates/navbar.php';
require_once __DIR__.'/templates/menu.php';
?>
<main class="app-main"><?php require __DIR__.'/views/barrios/revision_solicitudes_barrios.php'; ?></main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL='/territorio';window.TERR_REVISION={puedeResolver:<?= $puedeResolver?'true':'false' ?>,csrf:<?= json_encode($_SESSION['terr_revision_csrf'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>};</script>
<?php require_once __DIR__.'/templates/footer.php'; require_once __DIR__.'/templates/scripts.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/territorio/assets/css/revision-solicitudes-barrios.css?v=1">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/territorio/assets/js/revision-solicitudes-barrios.js?v=3"></script>
</body></html>
