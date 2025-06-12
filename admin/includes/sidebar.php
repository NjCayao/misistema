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
                    <a href="<?php echo ADMIN_URL; ?>/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
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
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todos los Productos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/add.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Agregar Producto</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/categories.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Órdenes -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/orders/" class="nav-link">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>Órdenes</p>
                    </a>
                </li>

                <!-- Usuarios -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/users/" class="nav-link">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Usuarios</p>
                    </a>
                </li>

                <!-- Reportes -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/reports/" class="nav-link">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>Reportes</p>
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
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/banners.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'banners.php' ? 'active' : ''; ?>">
                                <i class="fas fa-images nav-icon"></i>
                                <p>Banners</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/menus.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'menus.php' ? 'active' : ''; ?>">
                                <i class="fas fa-bars nav-icon"></i>
                                <p>Menús</p>
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

            </ul>
        </nav>
    </div>
</aside>