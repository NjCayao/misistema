<?php
// admin/pages/users/index.php - Lista de usuarios
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');

// Parámetros de filtrado y paginación
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$verified = $_GET['verified'] ?? '';
$country = $_GET['country'] ?? '';
$page = intval($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir WHERE clause
    $whereConditions = [];
    $params = [];
    
    if ($search) {
        $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($status === 'active') {
        $whereConditions[] = "is_active = 1";
    } elseif ($status === 'inactive') {
        $whereConditions[] = "is_active = 0";
    }
    
    if ($verified === 'verified') {
        $whereConditions[] = "is_verified = 1";
    } elseif ($verified === 'unverified') {
        $whereConditions[] = "is_verified = 0";
    }
    
    if ($country) {
        $whereConditions[] = "country = ?";
        $params[] = $country;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Obtener usuarios con paginación
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone, country, 
               is_verified, is_active, last_login, created_at,
               (SELECT COUNT(*) FROM orders WHERE user_id = users.id AND payment_status = 'completed') as total_orders,
               (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = users.id AND payment_status = 'completed') as total_spent
        FROM users 
        $whereClause
        ORDER BY created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Contar total para paginación
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users $whereClause");
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $perPage);
    
    // Obtener estadísticas
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_last_month
        FROM users
    ");
    $stats = $statsStmt->fetch();
    
    // Obtener países únicos para filtro
    $countriesStmt = $db->query("
        SELECT DISTINCT country 
        FROM users 
        WHERE country IS NOT NULL AND country != '' 
        ORDER BY country
    ");
    $countries = $countriesStmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en lista de usuarios: " . $e->getMessage());
    $users = [];
    $stats = ['total_users' => 0, 'active_users' => 0, 'verified_users' => 0, 'active_last_month' => 0];
    $countries = [];
    $totalUsers = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Usuarios | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-verified { background: #cce7ff; color: #004085; }
        .status-unverified { background: #fff3cd; color: #856404; }
        
        .stats-card {
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            background: #f8f9fa;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Gestión de Usuarios</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Usuarios</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    
                    <!-- Mensajes -->
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-check"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info stats-card">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                                    <p>Total Usuarios</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success stats-card">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['active_users']); ?></h3>
                                    <p>Usuarios Activos</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning stats-card">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['verified_users']); ?></h3>
                                    <p>Verificados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-secondary stats-card">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['active_last_month']); ?></h3>
                                    <p>Activos (30 días)</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Nombre, apellido o email">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activos</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Verificación</label>
                                <select name="verified" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="verified" <?php echo $verified === 'verified' ? 'selected' : ''; ?>>Verificados</option>
                                    <option value="unverified" <?php echo $verified === 'unverified' ? 'selected' : ''; ?>>Sin verificar</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">País</label>
                                <select name="country" class="form-control">
                                    <option value="">Todos los países</option>
                                    <?php foreach ($countries as $countryOption): ?>
                                        <option value="<?php echo $countryOption['country']; ?>" 
                                                <?php echo $country === $countryOption['country'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($countryOption['country']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                    <a href="create.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Nuevo Usuario
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla de usuarios -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                Lista de Usuarios 
                                <?php if ($search || $status || $verified || $country): ?>
                                    <span class="badge badge-info"><?php echo number_format($totalUsers); ?> filtrados</span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        
                        <div class="card-body table-responsive p-0">
                            <?php if (empty($users)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No se encontraron usuarios</h5>
                                    <?php if ($search || $status || $verified || $country): ?>
                                        <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                                        <a href="index.php" class="btn btn-primary">Ver todos los usuarios</a>
                                    <?php else: ?>
                                        <p class="text-muted">Crea el primer usuario del sistema</p>
                                        <a href="create.php" class="btn btn-success">Crear Usuario</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Email</th>
                                            <th>País</th>
                                            <th>Estado</th>
                                            <th>Verificado</th>
                                            <th>Compras</th>
                                            <th>Último Login</th>
                                            <th>Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                            <?php if ($user['phone']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo $user['email']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($user['country']): ?>
                                                        <span class="badge badge-light"><?php echo htmlspecialchars($user['country']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $user['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $user['is_verified'] ? 'status-verified' : 'status-unverified'; ?>">
                                                        <?php echo $user['is_verified'] ? 'Verificado' : 'Pendiente'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['total_orders'] > 0): ?>
                                                        <strong><?php echo $user['total_orders']; ?></strong> compras<br>
                                                        <small class="text-success"><?php echo formatPrice($user['total_spent']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin compras</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['last_login']): ?>
                                                        <?php echo timeAgo($user['last_login']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Nunca</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($user['created_at'], 'd/m/Y'); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')"
                                                                title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            Mostrando <?php echo min($perPage, $totalUsers - $offset); ?> de <?php echo number_format($totalUsers); ?> usuarios
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Paginación">
                                            <ul class="pagination pagination-sm m-0 float-right">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                            <i class="fas fa-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                                ?>
                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $totalPages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        function confirmDelete(userId, userName) {
            if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${userName}"?\n\nEsta acción no se puede deshacer.`)) {
                // Crear formulario dinámico para enviar DELETE
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete.php';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'user_id';
                inputId.value = userId;
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'delete';
                
                form.appendChild(inputId);
                form.appendChild(inputAction);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>