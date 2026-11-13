<?php
declare(strict_types=1);
$usarDataTables=false;
require_once __DIR__.'/segacceso/segacceso.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/administracion/autorizaciones_perfil.php';
require_once __DIR__.'/clases/Autorizacion.php';
$auth=new Autorizacion();$auth->exigir('gestion_barrios.php','modifica');
if(empty($_SESSION['geo_csrf']))$_SESSION['geo_csrf']=bin2hex(random_bytes(32));
$tituloPagina='Jurisdicciones propuestas';$paginaActiva='gestion_barrios';
require_once __DIR__.'/templates/header.php';
require_once __DIR__.'/templates/navbar.php';
require_once __DIR__.'/templates/menu.php';
?>
<main class="app-main"><?php require __DIR__.'/views/barrios/jurisdicciones_consolidacion.php'; ?></main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL='/territorio';window.GEO_CSRF=<?=json_encode($_SESSION['geo_csrf'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<?php require_once __DIR__.'/templates/footer.php'; require_once __DIR__.'/templates/scripts.php'; ?>
<link rel="stylesheet" href="/territorio/assets/css/jurisdicciones-consolidacion.css?v=1">
<script src="/territorio/assets/js/jurisdicciones-consolidacion.js?v=2"></script>
</body></html>
