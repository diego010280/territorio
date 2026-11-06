<!doctype html>
<html lang="es">
<head>
    <?php
    $_SESSION['nonce'] = bin2hex(openssl_random_pseudo_bytes(7));
    ?>

     <title>
        <?= htmlspecialchars(
            $tituloPagina ?? 'Territorio',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>
    </title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Fuente -->
    <!-- <link
         rel="stylesheet"
        href="/siad/assets/plugins/fonts/css/index.css"
    > -->

    <!-- OverlayScrollbars -->
    <link
        rel="stylesheet"
        href="/territorio/assets/plugins/overlayscrollbars/css/overlayscrollbars.min.css"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="/territorio/assets/plugins/bootstrap-icons/bootstrap-icons.min.css"
    >

    <!-- AdminLTE -->
    <link rel="stylesheet" href="/territorio/assets/css/adminlte.css?v=3.0.22">

    

    <?php if (!empty($usarDataTables)): ?>
         
            <link rel="stylesheet" href="/territorio/assets/plugins/datatables/css/datatables.min.css">
<?php endif; ?>

<!-- Estilos propios -->
    <link rel="stylesheet" href="/territorio/assets/css/style.css?v=3.0.29">
<!-- Diseño común de búsquedas -->
    <link rel="stylesheet" href="/territorio/assets/css/busqueda.css?v=3.0.21">

    <!-- Personalización visual de Territorio -->
<link rel="stylesheet" href="/territorio/assets/css/territorio.css">

<?php if (($paginaActiva ?? '') === 'visor'): ?>
    <!-- Leaflet para la primera prueba; en producción alojar localmente -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="/territorio/assets/css/visor.css?v=1">
<?php endif; ?>

</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

<div class="app-wrapper">