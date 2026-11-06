<?php
  require_once __DIR__ . '/../clases/Menu.php';
  $modulo = filter_var($_SESSION['moduleid'], FILTER_SANITIZE_NUMBER_INT);
  $usuario = filter_var($_SESSION['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
  
  $menus= new Menu();
  $menu=$menus->getUserMenu($usuario ,$modulo);

  $paginaActual = basename(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

function enlaceActivo(string $enlace, string $paginaActual): bool
{
    if ($enlace === '' || $enlace === '#') {
        return false;
    }

    $paginaEnlace = basename(
        parse_url($enlace, PHP_URL_PATH)
    );

    return $paginaEnlace === $paginaActual;
}
  
?>


<aside
    class="app-sidebar bg-body-secondary shadow"
    data-bs-theme="dark"
>

    <!-- Marca -->
    <div class="sidebar-brand">
        <a href="ctrlpanel.php" class="brand-link">

            <img
                src="./assets/img/sips-logo.png"
                alt="Logo"
                class="brand-image opacity-75 shadow"
            >

            <span class="brand-text fw-bold">
                Territorio
            </span>

        </a>
    </div>

    <!-- Menú -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">

            <ul
                class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview"
                role="navigation"
                data-accordion="false"
                id="navigation"
            >

                <li class="nav-header">
                    MENÚ PRINCIPAL
                </li>

                <?php
                $inicioActivo =
                    $paginaActual === 'ctrlpanel.php' && (($_GET['pagina'] ?? 'inicio') === 'inicio');
                ?>

                <li class="nav-item">
                    <a
                        href="ctrlpanel.php"
                        class="nav-link <?= $inicioActivo ? 'active' : '' ?>"
                    >
                        <i class="nav-icon bi bi-house"></i>
                        <p>Inicio</p>
                    </a>
                </li>

                <!-- Acceso inicial al visor; puede migrarse luego al menú institucional -->
                <li class="nav-item">
                    <a href="ctrlpanel.php?pagina=visor"
                       class="nav-link <?= ($paginaActiva ?? '') === 'visor' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-map"></i><p>Visor territorial</p>
                    </a>
                </li>

                <?php
                if (count($menu) > 0) {

                    foreach ($menu as $row_obj) {

                        if ((int) $row_obj['permiso_ejec'] !== 1) {
                            continue;
                        }

                        $submenu = $menus->optionsMenu(
                            $usuario,
                            $modulo,
                            $row_obj['IdObjeto']
                        );

                        if (count($submenu) > 0) {

                            /*
                             * Comprobar si alguno de los hijos
                             * corresponde a la página actual.
                             */
                            $submenuActivo = false;

                            foreach ($submenu as $row_hijoActivo) {
                                if (
                                    enlaceActivo(
                                        $row_hijoActivo['objeto_link'],
                                        $paginaActual
                                    )
                                ) {
                                    $submenuActivo = true;
                                    break;
                                }
                            }
                            ?>

                            <li
                                class="nav-item <?= $submenuActivo ? 'menu-open' : '' ?>"
                            >

                                <a
                                    href="#"
                                    class="nav-link <?= $submenuActivo ? 'active' : '' ?>"
                                >
                                    <i
                                        class="nav-icon <?= htmlspecialchars(
                                            $row_obj['objeto_ico'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    ></i>

                                    <p>
                                        <?= htmlspecialchars(
                                            $row_obj['objeto_descrip'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <i
                                        class="nav-arrow bi bi-chevron-right"
                                    ></i>
                                </a>

                                <ul class="nav nav-treeview">

                                    <?php
                                    foreach ($submenu as $row_hijos) {

                                        $hijoActivo = enlaceActivo(
                                            $row_hijos['objeto_link'],
                                            $paginaActual
                                        );
                                        ?>

                                        <li class="nav-item">
                                            <a
                                                href="<?= htmlspecialchars(
                                                    $row_hijos['objeto_link'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                class="nav-link <?= $hijoActivo ? 'active' : '' ?>"
                                            >
                                                <i
                                                    class="nav-icon <?= htmlspecialchars(
                                                        $row_hijos['objeto_ico'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                ></i>

                                                <p>
                                                    <?= htmlspecialchars(
                                                        $row_hijos['objeto_descrip'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </p>
                                            </a>
                                        </li>

                                    <?php } ?>

                                </ul>
                            </li>

                        <?php } else {

                            $menuActivo = enlaceActivo(
                                $row_obj['objeto_link'],
                                $paginaActual
                            );
                            ?>

                            <li class="nav-item">
                                <a
                                    href="<?= htmlspecialchars(
                                        $row_obj['objeto_link'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    class="nav-link <?= $menuActivo ? 'active' : '' ?>"
                                >
                                    <i
                                        class="nav-icon <?= htmlspecialchars(
                                            $row_obj['objeto_ico'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    ></i>

                                    <p>
                                        <?= htmlspecialchars(
                                            $row_obj['objeto_descrip'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>
                                </a>
                            </li>

                        <?php }
                    }
                }
                ?>

            </ul>
        </nav>
    </div>
</aside>