
<?php

$datousuario=new Usuario();
$data=$datousuario->datosusuario($_SESSION['usuario_id']);

$datapersonalrrhh=$datousuario->buscarUsuarioPorLegajo($_SESSION['usuario_id']);
$personaladicional= $datapersonalrrhh['data'] ??[];
$depenusu=$datousuario->getAuthorization($pagina);

?>

<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">

        <ul class="navbar-nav">

            <!-- Botón para abrir/cerrar sidebar -->
            <li class="nav-item">
                <a
                    class="nav-link"
                    data-lte-toggle="sidebar"
                    href="#"
                    role="button"
                    aria-label="Abrir o cerrar menú lateral"
                >
                    <i class="bi bi-list"></i>
                </a>
            </li>

            <!-- Usuario y dependencia -->
            <li class="nav-item d-none d-md-flex align-items-center">
                <span class="nav-link navbar-user-info">

                    <i class="bi bi-person-fill"></i>
                    <input type="hidden" id="user_idx" value="<?php echo $_SESSION['usuario_id'] ?>">
                    <?php
						$nombres= explode(" ", trim($data['usuario_nombre'] ))[0];
						$apellidos= explode(" ", trim($data['usuario_apellido']))[0];
						$dependencia_delmodulo= $depenusu[0]['Desc_depend'];
						 
					?>	
                    <strong>
                        <?= htmlspecialchars(
                            strtoupper(($nombres . ' ' . $apellidos) ?? 'USUARIO'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </strong>

                    <span class="mx-1">-</span>

                    <span class="navbar-dependencia">
                        <?= htmlspecialchars(
                            strtoupper($dependencia_delmodulo ?? 'SIN DEPENDENCIA'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </span>

                </span>
            </li>

        </ul>

        <ul class="navbar-nav ms-auto">

            <!-- Pantalla completa -->
            <li class="nav-item">
                <a
                    class="nav-link"
                    href="#"
                    data-lte-toggle="fullscreen"
                    aria-label="Pantalla completa"
                >
                    <i
                        data-lte-icon="maximize"
                        class="bi bi-arrows-fullscreen"
                    ></i>

                    <i
                        data-lte-icon="minimize"
                        class="bi bi-fullscreen-exit d-none"
                    ></i>
                </a>
            </li>

            <!-- Menú y avatar del usuario -->
            <?php
            require_once __DIR__ . '/../views/cuenta_usuario/datoscuenta.php';
            ?>

        </ul>

    </div>
</nav>