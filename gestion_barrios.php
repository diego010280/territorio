<?php
declare(strict_types=1);
$usarDataTables = true;
require_once __DIR__ . '/segacceso/segacceso.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/administracion/autorizaciones_perfil.php';
require_once __DIR__ . '/clases/Autorizacion.php';
$authBarrios = new Autorizacion();
$authBarrios->exigir('gestion_barrios.php', 'ejec');
$tituloPagina = 'Administración de Barrios';
$paginaActiva = 'gestion_barrios';
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/navbar.php';
require_once __DIR__ . '/templates/menu.php';
?>
<main class="app-main">
<?php require __DIR__ . '/views/barrios/gestion_barrios.php'; ?>
</main>
<?php require_once __DIR__ . '/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL = '/territorio';</script>
<?php
require_once __DIR__ . '/templates/footer.php';
require_once __DIR__ . '/templates/scripts.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/territorio/assets/js/historial-territorial.js?v=2"></script>
<script src="/territorio/assets/js/gestion-barrios.js?v=12"></script>
</body>
</html>
