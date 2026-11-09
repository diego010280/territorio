<?php

declare(strict_types=1);
$usarDataTables = false;
require_once __DIR__ . '/segacceso/segacceso.php';

require_once __DIR__ . '/config/database.php';
//include_once __DIR__ . "/views/errores/errores.php";
require_once __DIR__ . '/administracion/autorizaciones_perfil.php';

include_once __DIR__ . "/views/sanitizar/sanitize.php";



$tituloPagina = 'Visor';
$paginaActiva = 'visor';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/navbar.php';
require_once __DIR__ . '/templates/menu.php';
?>

<main class="app-main">

    <?php require_once __DIR__ . '/views/visor/barrios.php'; ?>

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