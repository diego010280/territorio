 <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                         <img
                  src="<?= '../../acceso/users/fotoperfil/'.$data['usuario_avatar']?>"
                  class="user-image rounded-circle shadow"
                  alt="User Image"
                />
                <!-- <span class="d-none d-md-inline">Alexander Pierce</span> -->
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a href="segacceso/salir.php" class="dropdown-item">
                                <i class="bi bi-door-closed me-2"></i>
                               Cerrar Modulo
                            </a>
                        </li>

                         <li>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-info-circle me-2"></i>
                               Ayuda
                            </a>
                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a href="segacceso/logout.php" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Salir
                            </a>
                        </li>
                    </ul>
                </li>