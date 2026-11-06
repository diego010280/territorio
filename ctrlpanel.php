<?php

declare(strict_types=1);
$usarDataTables = false;
require_once __DIR__ . '/segacceso/segacceso.php';

require_once __DIR__ . '/config/database.php';
//include_once __DIR__ . "/views/errores/errores.php";
require_once __DIR__ . '/administracion/autorizaciones_perfil.php';

include_once __DIR__ . "/views/sanitizar/sanitize.php";

// Rutas internas permitidas: no incluir rutas arbitrarias por GET.
$paginaSolicitada = (string)($_GET['pagina'] ?? 'inicio');
$paginasPermitidas = [
    'inicio' => ['titulo' => 'Inicio', 'vista' => 'views/inicio.php'],
    'visor'  => ['titulo' => 'Visor Territorial', 'vista' => 'views/visor/visor.php'],
];
if (!isset($paginasPermitidas[$paginaSolicitada])) {
    http_response_code(404);
    $paginaSolicitada = 'inicio';
}
$paginaActiva = $paginaSolicitada;
$tituloPagina = $paginasPermitidas[$paginaActiva]['titulo'];


require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/navbar.php';
require_once __DIR__ . '/templates/menu.php';
?>

<main class="app-main">

    <?php require __DIR__ . '/' . $paginasPermitidas[$paginaActiva]['vista']; ?>

</main>
<!-- Bloqueo Total por inactividad -->

<?php require_once __DIR__ . '/views/inactividad/bloqueototal.php';?>

<!-- ***************************************************************** -->
<script>
    window.APP_BASE_URL = '/territorio';/* carpteta del modulo nombre */
</script>
<?php

require_once __DIR__ . '/templates/footer.php';
require_once __DIR__ . '/templates/scripts.php';

?>
</body>
</html>