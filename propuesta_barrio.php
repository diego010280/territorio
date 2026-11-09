<?php
declare(strict_types=1);
$usarDataTables = false;
require_once __DIR__.'/segacceso/segacceso.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/administracion/autorizaciones_perfil.php';
require_once __DIR__.'/clases/Autorizacion.php';
$aut = new Autorizacion();
$modo = isset($_GET['id']) ? 'modifica' : 'alta';
$aut->exigir('gestion_barrios.php', $modo);
$tituloPagina='Propuesta de barrio';
$paginaActiva='gestion_barrios';

// Habilitar Leaflet en el formulario geográfico
$cargarLeaflet = true;

require_once __DIR__.'/templates/header.php';
require_once __DIR__.'/templates/navbar.php';
require_once __DIR__.'/templates/menu.php';
?>
<main class="app-main"><?php require __DIR__.'/views/barrios/propuesta_barrio.php'; ?></main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL='/territorio';</script>
<?php require_once __DIR__.'/templates/footer.php'; require_once __DIR__.'/templates/scripts.php'; ?>
<!-- Leaflet: si ya se carga desde templates/scripts.php, no duplicar. -->
<script src="/territorio/assets/js/propuesta-barrio.js?v=5"></script>
</body></html>
