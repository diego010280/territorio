<?php
declare(strict_types=1);
$usarDataTables = true;
require_once __DIR__ . '/segacceso/segacceso.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/administracion/autorizaciones_perfil.php';
require_once __DIR__ . '/clases/Autorizacion.php';
(new Autorizacion())->exigir('auditoria_territorio.php','ejec');
$tituloPagina = 'Auditoría de Territorio';
$paginaActiva = 'auditoria_territorio';
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/navbar.php';
require_once __DIR__ . '/templates/menu.php';
?>
<main class="app-main">
<?php require __DIR__ . '/views/auditoria/index.php'; ?>
</main>
<?php require_once __DIR__ . '/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL = '/territorio';</script>
<?php
require_once __DIR__ . '/templates/footer.php';
require_once __DIR__ . '/templates/scripts.php';
?>
<script src="/territorio/assets/js/auditoria-territorio.js?v=1"></script>
</body></html>
