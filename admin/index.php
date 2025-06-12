<?php
// admin/index.php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$admin = $_SESSION[ADMIN_SESSION_NAME];

// Obtener estadísticas del dashboard
try {
    $db = Database::getInstance()->getConnection();
    
    // Total de productos
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
    $totalProducts = $stmt->fetch()['total'];
    
    // Total de usuarios
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $totalUsers = $stmt->fetch()['total'];
    
    // Total de órdenes este mes
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')");
    $ordersThisMonth = $stmt->fetch()['total'];
    
    // Ingresos este mes
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')");
    $incomeThisMonth = $stmt->fetch()['total'];
    
    // Productos más descargados
    $stmt = $db->query("
        SELECT p.name, COUNT(dl.id) as downloads 
        FROM products p 
        LEFT JOIN download_logs dl ON p.id = dl.product_id 
        WHERE p.is_active = 1 
        GROUP BY p.id 
        ORDER BY downloads DESC 
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll();
    
    // Órdenes recientes
    $stmt = $db->query("
        SELECT o.order_number, o.total_amount, o.payment_status, o.created_at, o.customer_name 
        FROM orders o 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $recentOrders = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en dashboard admin: " . $e->getMessage());
    $totalProducts = $totalUsers = $ordersThisMonth = $incomeThisMonth = 0;
    $topProducts = $recentOrders = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Info boxes -->
                <div class="row">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-box"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Productos</span>
                                <span class="info-box-number"><?php echo number_format($totalProducts); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box mb-3">
                            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-users"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Usuarios</span>
                                <span class="info-box-number"><?php echo number_format($totalUsers); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box mb-3">
                            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-shopping-cart"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Órdenes (Este Mes)</span>
                                <span class="info-box-number"><?php echo number_format($ordersThisMonth); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box mb-3">
                            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-dollar-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Ingresos (Este Mes)</span>
                                <span class="info-box-number"><?php echo formatPrice($incomeThisMonth); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Productos más descargados -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Productos Más Descargados</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Descargas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($topProducts)): ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">No hay datos disponibles</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($topProducts as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><span class="badge badge-primary"><?php echo number_format($product['downloads']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Órdenes recientes -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Órdenes Recientes</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentOrders)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No hay órdenes</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo formatPrice($order['total_amount']); ?></td>
                                                    <td>
                                                        <?php
                                                        $statusClass = match($order['payment_status']) {
                                                            'completed' => 'success',
                                                            'pending' => 'warning',
                                                            'failed' => 'danger',
                                                            'refunded' => 'secondary',
                                                            default => 'secondary'
                                                        };
                                                        ?>
                                                        <span class="badge badge-<?php echo $statusClass; ?>">
                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bienvenida -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-tachometer-alt mr-1"></i>
                                    Bienvenido, <?php echo htmlspecialchars($admin['full_name']); ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <p>Panel de administración de MiSistema. Desde aquí se podrá gestionar todos los aspectos de la plataforma de venta de software.</p>
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="pages/products/" class="btn btn-app">
                                            <i class="fas fa-box"></i>
                                            Productos
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="pages/orders/" class="btn btn-app">
                                            <i class="fas fa-shopping-cart"></i>
                                            Órdenes
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="pages/users/" class="btn btn-app">
                                            <i class="fas fa-users"></i>
                                            Usuarios
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="pages/config/" class="btn btn-app">
                                            <i class="fas fa-cog"></i>
                                            Configuración
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </section>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
</div>

<!-- jQuery -->
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>