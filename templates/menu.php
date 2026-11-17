<?php
/** Menú lateral Territorio: 3 niveles con permisos efectivos y rama activa. */
require_once __DIR__ . '/../clases/Menu.php';
$modulo = (int)($_SESSION['moduleid'] ?? 0);
$usuario = (int)($_SESSION['usuario_id'] ?? 0);
$menus = new Menu();
$menuArbol = $menus->getUserMenuTree($usuario, $modulo, 3);

$paginaActiva = (string)($paginaActiva ?? $_GET['pagina'] ?? 'inicio');
if (!function_exists('territorioEnlaceActivo')) {
    function territorioEnlaceActivo(string $enlace, string $paginaActual): bool
    {
        $enlace = trim($enlace);
        if ($enlace === '' || $enlace === '#') return false;
        $url = parse_url($enlace);
        if ($url === false || isset($url['host']) || isset($url['scheme'])) return false;
        $ruta = basename((string)($url['path'] ?? ''));
        $scriptActual = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($ruta === '' || $ruta !== $scriptActual) return false;
        parse_str((string)($url['query'] ?? ''), $args);
        if ($ruta === 'ctrlpanel.php') {
            return (string)($args['pagina'] ?? 'inicio') === $paginaActual;
        }
        return true;
    }
}

$marcarActivos = function (array $nodos) use (&$marcarActivos, $paginaActiva): array {
    foreach ($nodos as &$nodo) {
        $nodo['hijos'] = $marcarActivos($nodo['hijos'] ?? []);
        $nodo['activoDirecto'] = territorioEnlaceActivo((string)($nodo['objeto_link'] ?? ''), $paginaActiva);
        $nodo['ramaActiva'] = $nodo['activoDirecto'];
        foreach ($nodo['hijos'] as $hijo) {
            if ($hijo['ramaActiva']) $nodo['ramaActiva'] = true;
        }
    }
    unset($nodo);
    return $nodos;
};
$menuArbol = $marcarActivos($menuArbol);

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$renderNodos = function (array $nodos, int $nivel = 1) use (&$renderNodos, $esc): void {
    foreach ($nodos as $nodo) {
        $hijos = $nodo['hijos'] ?? [];
        $tieneHijos = count($hijos) > 0;
        $ruta = trim((string)($nodo['objeto_link'] ?? ''));
        $href = $tieneHijos || $ruta === '' ? '#' : $ruta;
        $activo = !empty($nodo['ramaActiva']);
        $icono = trim((string)($nodo['objeto_ico'] ?? '')) ?: 'bi bi-circle';
        ?>
        <li class="nav-item <?= ($tieneHijos && $activo) ? 'menu-open' : '' ?>">
            <a href="<?= $esc($href) ?>"
               class="nav-link <?= $activo ? 'active' : '' ?>"
               <?= $tieneHijos ? 'aria-expanded="' . ($activo ? 'true' : 'false') . '"' : '' ?>>
                <i class="nav-icon <?= $esc($icono) ?>"></i>
                <p><?= $esc($nodo['objeto_descrip']) ?>
                    <?php if ($tieneHijos): ?>
                        <i class="nav-arrow bi bi-chevron-right"></i>
                    <?php endif; ?>
                </p>
            </a>
            <?php if ($tieneHijos): ?>
                <ul class="nav nav-treeview">
                    <?php $renderNodos($hijos, $nivel + 1); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
};
?>
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="ctrlpanel.php" class="brand-link">
            <img src="./assets/img/sips-logo.png" alt="Logo" class="brand-image opacity-75 shadow">
            <span class="brand-text fw-bold">Territorio</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview" role="navigation"
                data-accordion="false" id="navigation">
                <li class="nav-header">MENÚ PRINCIPAL</li>
                <li class="nav-item">
                    <a href="ctrlpanel.php" class="nav-link <?= $paginaActiva === 'inicio' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-house"></i><p>Inicio</p>
                    </a>
                </li>
                <?php $renderNodos($menuArbol); ?>
            </ul>
        </nav>
    </div>
</aside>
