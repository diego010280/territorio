<?php
/** Inicio Territorio: accesos 100% basados en objetos y permisos institucionales. */
require_once __DIR__ . '/../clases/Menu.php';

$usuarioG = (int) ($_SESSION['usuario_id'] ?? 0);
$moduloG  = (int) ($_SESSION['moduleid'] ?? 0);
$gadgets = [];

if ($usuarioG > 0 && $moduloG > 0) {
    try {
        $menuG = (isset($menus) && $menus instanceof Menu) ? $menus : new Menu();
        foreach ($menuG->getUserGadgets($usuarioG, $moduloG) as $fila) {
            $enlace = trim((string)($fila['objeto_link'] ?? ''));
            $url = parse_url($enlace);
            // Solo recursos internos del módulo (nunca destinos externos/absolutos).
            if ($url === false || isset($url['scheme'], $url['host']) ||
                isset($url['scheme']) || isset($url['host']) ||
                str_contains($enlace, '..') || str_contains($enlace, '\\') ||
                str_starts_with($enlace, '/') ||
                !preg_match('~^[a-zA-Z0-9_/-]+\.php(?:\?[a-zA-Z0-9_=&%-]+)?$~D', $enlace)) {
                continue;
            }
            $gadgets[] = $fila;
        }
    } catch (Throwable $e) {
        error_log('Territorio / inicio / gadgets: ' . $e->getMessage());
    }
}
$esc = static fn($valor): string => htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$colores = ['bosque', 'esmeralda', 'azul', 'ambar', 'violeta', 'pizarra'];
?>
<div class="app-content-header territorio-inicio-header">
    <div class="container-fluid">
        <div class="territorio-hero">
            <div class="territorio-hero-content">
                <div class="territorio-eyebrow"><i class="bi bi-compass me-1" aria-hidden="true"></i> PLATAFORMA TERRITORIAL</div>
                <h1 class="territorio-hero-title">Gestión Territorial</h1>
                <p class="territorio-hero-subtitle">Consultá mapas, barrios y recursos geográficos desde un único lugar.</p>
            </div>
            <div class="territorio-hero-emblema" aria-hidden="true"><i class="bi bi-globe-americas"></i></div>
        </div>
    </div>
</div>
<div class="app-content territorio-inicio">
    <div class="container-fluid">
        <div class="territorio-seccion-cabecera">
            <div>
                <h2 class="territorio-seccion-titulo">Accesos rápidos</h2>
                <p class="territorio-seccion-descripcion">Seleccioná una herramienta para comenzar.</p>
            </div>
            <?php if ($gadgets): ?>
                <span class="territorio-conteo"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> <?= count($gadgets) ?> herramientas disponibles</span>
            <?php endif; ?>
        </div>

        <?php if ($gadgets): ?>
            <div class="row g-3 territorio-gadgets-grid">
                <?php foreach ($gadgets as $i => $fila):
                    $icono = trim((string)($fila['objeto_ico'] ?? ''));
                    if (!preg_match('/^(?:(?:bi|nav-icon)\s+)*bi-[a-z0-9-]+(?:\s+bi-[a-z0-9-]+)?$/D', $icono)) {
                        $icono = 'bi bi-geo-alt';
                    }
                    $titulo = trim((string)($fila['objeto_descrip'] ?? 'Herramienta'));
                    $descripcion = trim((string)($fila['objeto_obs'] ?? ''));
                    if ($descripcion === '') $descripcion = 'Acceder a ' . $titulo;
                    $tema = $colores[$i % count($colores)];
                ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <a class="card geo-gadget geo-gadget--<?= $esc($tema) ?> h-100"
                           href="<?= $esc($fila['objeto_link']) ?>"
                           aria-label="Abrir <?= $esc($titulo) ?>">
                            <div class="card-body geo-gadget-body">
                                <div class="geo-gadget-top">
                                    <span class="geo-gadget-icon"><i class="<?= $esc($icono) ?>" aria-hidden="true"></i></span>
                                    <span class="geo-gadget-corner"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
                                </div>
                                <h3 class="geo-gadget-title"><?= $esc($titulo) ?></h3>
                                <p class="geo-gadget-text"><?= $esc($descripcion) ?></p>
                            </div>
                            <div class="geo-gadget-footer">
                                <span>Ingresar</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="territorio-vacio" role="status">
                <i class="bi bi-grid-1x2" aria-hidden="true"></i>
                <h3>No hay accesos rápidos habilitados</h3>
                <p>El administrador puede asignar herramientas mediante objetos, perfiles y permisos. Los objetos deben permitir ejecución y tener <code>objeto_vercomo = 2</code> o <code>3</code>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
