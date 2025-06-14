<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo ADMIN_URL; ?>/index.php" class="brand-link">
        <img src="<?php echo SITE_URL; ?>/vendor/adminlte/dist/img/AdminLTELogo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light"><?php echo getSetting('site_name', 'MiSistema'); ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?php echo SITE_URL; ?>/vendor/adminlte/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block"><?php echo htmlspecialchars($_SESSION[ADMIN_SESSION_NAME]['username']); ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && !isset($_GET['page']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Productos -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/products/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/products/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-box"></i>
                        <p>
                            Productos
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/products/') !== false && basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                                <i class="fas fa-list nav-icon"></i>
                                <p>Todos los Productos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/?create=1" class="nav-link">
                                <i class="fas fa-plus nav-icon"></i>
                                <p>Agregar Producto</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tags nav-icon"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Órdenes -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/orders/" class="nav-link">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>
                            Órdenes
                            <span class="badge badge-info right">Próximo</span>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Usuarios
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todos los Usuarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/create.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Nuevo Usuario</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/index.php?status=active" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Usuarios Activos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/index.php?verified=unverified" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Sin Verificar</p>
                            </a>
                        </li>
                    </ul>
                </li>


                <!-- Reportes -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/reports/" class="nav-link">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Reportes
                            <span class="badge badge-info right">Próximo</span>
                        </p>
                    </a>
                </li>

                <!-- Contenido -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/content/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/content/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>
                            Contenido
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/pages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pages.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file nav-icon"></i>
                                <p>Páginas</p>
                            </a>
                        </li>                        
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/menus.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'menus.php' ? 'active' : ''; ?>">
                                <i class="fas fa-bars nav-icon"></i>
                                <p>Menús</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/banners.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'banners.php' ? 'active' : ''; ?>">
                                <i class="fas fa-images nav-icon"></i>
                                <p>Banners</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Configuración -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/config/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/config/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>
                            Configuración
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/general.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'general.php' ? 'active' : ''; ?>">
                                <i class="fas fa-cog nav-icon"></i>
                                <p>General</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/payments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                                <i class="fas fa-credit-card nav-icon"></i>
                                <p>Pagos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/email.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email.php' ? 'active' : ''; ?>">
                                <i class="fas fa-envelope nav-icon"></i>
                                <p>Email</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/seo.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'seo.php' ? 'active' : ''; ?>">
                                <i class="fas fa-search nav-icon"></i>
                                <p>SEO</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Separador -->
                <li class="nav-header">FRONTEND</li>

                <!-- Ver Sitio -->
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>" class="nav-link" target="_blank">
                        <i class="nav-icon fas fa-external-link-alt"></i>
                        <p>
                            Ver Sitio Web
                            <span class="badge badge-success right">Nuevo</span>
                        </p>
                    </a>
                </li>

                <!-- Separador -->
                <li class="nav-header">HERRAMIENTAS</li>

                <!-- Limpiar Cache -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="clearCache()">
                        <i class="nav-icon fas fa-broom"></i>
                        <p>Limpiar Cache</p>
                    </a>
                </li>

                <!-- Backup -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="generateBackup()">
                        <i class="nav-icon fas fa-download"></i>
                        <p>Generar Backup</p>
                    </a>
                </li>

                <!-- Separador -->
                <li class="nav-header">PROGRESO</li>

                <!-- Progreso del desarrollo -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showProgress()">
                        <i class="nav-icon fas fa-tasks"></i>
                        <p>
                            Progreso del Proyecto
                            <span class="badge badge-warning right">47%</span>
                        </p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>

<!-- Scripts adicionales para funcionalidades del sidebar -->
<script>
    function clearCache() {
        if (confirm('¿Limpiar cache del sistema?')) {
            // Aquí implementaremos la limpieza de cache
            toastr.info('Funcionalidad próximamente disponible');
        }
    }

    function generateBackup() {
        if (confirm('¿Generar backup de la base de datos?')) {
            // Aquí implementaremos el backup
            toastr.info('Funcionalidad próximamente disponible');
        }
    }

    function showProgress() {
        // Modal con progreso del desarrollo
        const progressModal = `
        <div class="modal fade" id="progressModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">
                            <i class="fas fa-tasks"></i> Progreso del Proyecto MiSistema
                        </h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="progress-group">
                            <span class="progress-text">Fase 1: Estructura Base</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 2: Dashboard Admin</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 3: Frontend Público</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 4: Sistema de Pagos</span>
                            <span class="float-right"><b>0%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-secondary" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 5: Funcionalidades Avanzadas</span>
                            <span class="float-right"><b>0%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-secondary" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 6: Optimización y Lanzamiento</span>
                            <span class="float-right"><b>0%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-secondary" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="progress-group">
                            <span class="progress-text"><strong>Progreso Total</strong></span>
                            <span class="float-right"><b>62%</b></span>
                            <div class="progress">
                                <div class="progress-bar bg-primary progress-bar-striped" style="width: 62%"></div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <h5><i class="icon fas fa-info"></i> Próximo:</h5>
                            Fase 4 - Sistema de Pagos: Implementación de pasarelas de pago, gestión de transacciones y seguridad.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

        // Remover modal anterior si existe
        $('#progressModal').remove();

        // Agregar modal al body
        $('body').append(progressModal);

        // Mostrar modal
        $('#progressModal').modal('show');
    }
</script>