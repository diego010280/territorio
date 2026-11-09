<?php
  require_once __DIR__ . '/../clases/Menu.php';
  $modulo = filter_var($_SESSION['moduleid'], FILTER_SANITIZE_NUMBER_INT);
  $usuario = filter_var($_SESSION['usuario_id'], FILTER_SANITIZE_NUMBER_INT);
  
  $menus= new Menu();
  $menu=$menus->getUserMenu($usuario ,$modulo);

  // Rutas reales del sistema: barrios.php, ctrlpanel.php, etc.
  $paginaActiva = (string)($paginaActiva ?? $_GET['pagina'] ?? 'inicio');
  function enlaceActivo(string $enlace, string $paginaActual): bool
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
                $inicioActivo = $paginaActiva === 'inicio';
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
                                        $paginaActiva
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
                                            $paginaActiva
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
                                $paginaActiva
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