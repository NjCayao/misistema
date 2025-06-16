<?php
// admin/pages/content/pages.php - CÓDIGO COMPLETO FINAL
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$success = '';
$error = '';

// Manejar mensajes de éxito
if (isset($_GET['created'])) {
    $success = 'Página creada exitosamente y agregada a elementos disponibles';
} elseif (isset($_GET['updated'])) {
    $success = 'Página actualizada exitosamente';
} elseif (isset($_GET['deleted'])) {
    $success = 'Página eliminada exitosamente';
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $title = sanitize($_POST['title'] ?? '');
                    $content = $_POST['content'] ?? '';
                    $meta_title = sanitize($_POST['meta_title'] ?? '');
                    $meta_description = sanitize($_POST['meta_description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    if (empty($title)) {
                        throw new Exception('El título de la página es obligatorio');
                    }

                    $slug = generateSlug($title);

                    // Verificar que el slug no exista
                    $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una página con ese título');
                    }

                    // Iniciar transacción
                    $db->beginTransaction();

                    // Crear página
                    $stmt = $db->prepare("
                        INSERT INTO pages (title, slug, content, meta_title, meta_description, is_active, view_count) 
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([$title, $slug, $content, $meta_title, $meta_description, $is_active]);
                    $pageId = $db->lastInsertId();

                    // SIEMPRE crear en menu_items automáticamente
                    $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM menu_items WHERE menu_location = 'available_pages'");
                    $stmt->execute();
                    $nextOrder = $stmt->fetch()['next_order'];

                    $stmt = $db->prepare("
                        INSERT INTO menu_items (title, url, menu_location, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, '/' . $slug, 'available_pages', 1, $nextOrder]);

                    $db->commit();
                    redirect($_SERVER['PHP_SELF'] . '?created=1');
                    break;

                case 'update':
                    $id = intval($_POST['id'] ?? 0);
                    $title = sanitize($_POST['title'] ?? '');
                    $content = $_POST['content'] ?? '';
                    $meta_title = sanitize($_POST['meta_title'] ?? '');
                    $meta_description = sanitize($_POST['meta_description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    if (empty($title) || $id <= 0) {
                        throw new Exception('Datos inválidos');
                    }

                    // Obtener slug actual
                    $stmt = $db->prepare("SELECT slug FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentPage = $stmt->fetch();
                    if (!$currentPage) {
                        throw new Exception('Página no encontrada');
                    }

                    $slug = generateSlug($title);

                    // Verificar que el slug no exista (excepto para el mismo registro)
                    $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una página con ese título');
                    }

                    // Iniciar transacción
                    $db->beginTransaction();

                    // Actualizar página
                    $stmt = $db->prepare("
                        UPDATE pages SET title = ?, slug = ?, content = ?, meta_title = ?, meta_description = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $content, $meta_title, $meta_description, $is_active, $id]);

                    // Actualizar en menu_items (solo título y URL)
                    $currentUrl = '/' . $currentPage['slug'];
                    $newUrl = '/' . $slug;

                    $stmt = $db->prepare("UPDATE menu_items SET title = ?, url = ? WHERE url = ?");
                    $stmt->execute([$title, $newUrl, $currentUrl]);

                    $db->commit();
                    redirect($_SERVER['PHP_SELF'] . '?updated=1');
                    break;

                case 'delete':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }

                    // PROTEGER PÁGINAS DEL SISTEMA (ID 1 y 2)
                    if ($id <= 2) {
                        throw new Exception('No se puede eliminar esta página del sistema. Usa el editor de páginas especiales.');
                    }

                    // Obtener datos de la página
                    $stmt = $db->prepare("SELECT slug FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $pageToDelete = $stmt->fetch();

                    if (!$pageToDelete) {
                        throw new Exception('Página no encontrada');
                    }

                    // Iniciar transacción
                    $db->beginTransaction();

                    // Eliminar página
                    $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
                    $stmt->execute([$id]);

                    // Eliminar automáticamente de todos los menús
                    $stmt = $db->prepare("DELETE FROM menu_items WHERE url = ?");
                    $stmt->execute(['/' . $pageToDelete['slug']]);

                    $db->commit();
                    redirect($_SERVER['PHP_SELF'] . '?deleted=1');
                    break;
            }
        }
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
        logError("Error en páginas: " . $e->getMessage());
    }
}

// Obtener páginas - ORDEN DESCENDENTE POR ID (nuevos primero)
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT p.*, 
               CASE WHEN m.id IS NOT NULL THEN 'Sí' ELSE 'No' END as in_menus
        FROM pages p 
        LEFT JOIN menu_items m ON m.url = CONCAT('/', p.slug) AND m.menu_location = 'available_pages'
        ORDER BY p.id DESC
    ");
    $pages = $stmt->fetchAll();
} catch (Exception $e) {
    $pages = [];
    $error = 'Error al obtener páginas: ' . $e->getMessage();
}

// Si hay un ID en GET, obtener datos para editar
$editPage = null;
$createMode = isset($_GET['create']);

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editPage = $stmt->fetch();
        if (!$editPage) {
            $error = 'Página no encontrada';
            redirect($_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $error = 'Error al obtener página para editar';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Páginas | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">

    <!-- Summernote -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/summernote/summernote-bs4.min.css">

    <style>
        .page-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .note-editor {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        .menu-auto-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .menu-auto-info h6 {
            color: #1976d2;
            margin-bottom: 10px;
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
                            <h1 class="m-0">Gestión de Páginas</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Contenido</li>
                                <li class="breadcrumb-item active">Páginas</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

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

                    <?php if (!$editPage && !$createMode): ?>
                        <!-- Vista de Lista -->
                        <div class="row">
                            <div class="col-12">
                                <!-- Info sobre sistema automático -->
                                <div class="menu-auto-info">
                                    <h6><i class="fas fa-magic"></i> Sistema Automático de Menús</h6>
                                    <p class="mb-0">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Todas las páginas aparecen automáticamente</strong> en "Páginas Disponibles" del editor de menús.
                                        El estado de la página controla si se muestra en el sitio público.
                                    </p>
                                </div>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Información sobre Páginas del Sistema</h6>
                                    <p class="mb-1">
                                        <strong>Páginas Protegidas:</strong> Las páginas "Política de Privacidad" y "Términos y Condiciones"
                                        son páginas del sistema y no pueden ser eliminadas.
                                    </p>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Páginas del Sitio</h3>
                                        <div class="card-tools">
                                            <a href="?create=1" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Nueva Página
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="pagesTable">
                                                <thead>
                                                    <tr>
                                                        <th>Título</th>
                                                        <th>Slug</th>
                                                        <th>Contenido</th>
                                                        <th width="100">Estado</th>
                                                        <th width="100">En Menús</th>
                                                        <th width="100">Vistas</th>
                                                        <th width="150">Fecha</th>
                                                        <th width="120">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pages as $page): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($page['title']); ?></strong>
                                                                <?php if ($page['id'] <= 2): ?>
                                                                    <span class="badge badge-primary ml-2">SISTEMA</span>
                                                                <?php endif; ?>
                                                                <?php if ($page['meta_title']): ?>
                                                                    <br><small class="text-muted">SEO: <?php echo htmlspecialchars($page['meta_title']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <code><?php echo htmlspecialchars($page['slug']); ?></code>
                                                                <br><a href="<?php echo SITE_URL; ?>/<?php echo $page['slug']; ?>" target="_blank" class="text-info">
                                                                    <i class="fas fa-external-link-alt"></i> Ver
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <div class="page-preview">
                                                                    <?php echo strip_tags(substr($page['content'], 0, 100)); ?>...
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php if ($page['is_active']): ?>
                                                                    <span class="badge badge-success">Activa</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">Inactiva</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge badge-<?php echo $page['in_menus'] == 'Sí' ? 'success' : 'warning'; ?>">
                                                                    <?php echo $page['in_menus']; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-info"><?php echo number_format($page['view_count']); ?></span>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    Creada: <?php echo date('d/m/Y', strtotime($page['created_at'])); ?><br>
                                                                    Editada: <?php echo date('d/m/Y', strtotime($page['updated_at'])); ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <a href="?edit=<?php echo $page['id']; ?>" class="btn btn-sm btn-info">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <?php if ($page['id'] > 2): ?>
                                                                    <button class="btn btn-sm btn-danger" onclick="deletePage(<?php echo $page['id']; ?>, '<?php echo htmlspecialchars($page['title']); ?>')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="btn btn-sm btn-secondary disabled" title="Página del sistema protegida">
                                                                        <i class="fas fa-shield-alt"></i>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Vista de Edición/Creación -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <?php echo $createMode ? 'Nueva Página' : 'Editar Página'; ?>
                                        </h3>
                                        <div class="card-tools">
                                            <a href="?" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left"></i> Volver a la Lista
                                            </a>
                                        </div>
                                    </div>
                                    <form method="post">
                                        <div class="card-body">
                                            <input type="hidden" name="action" value="<?php echo $createMode ? 'create' : 'update'; ?>">
                                            <?php if ($editPage): ?>
                                                <input type="hidden" name="id" value="<?php echo $editPage['id']; ?>">
                                            <?php endif; ?>

                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="form-group">
                                                        <label for="title">Título de la Página *</label>
                                                        <input type="text" class="form-control" id="title" name="title"
                                                            value="<?php echo $editPage ? htmlspecialchars($editPage['title']) : ''; ?>" required>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="content">Contenido</label>
                                                        <textarea class="form-control" id="content" name="content" rows="15"><?php echo $editPage ? htmlspecialchars($editPage['content']) : ''; ?></textarea>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">Configuración</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <div class="custom-control custom-switch">
                                                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                                                        <?php echo (!$editPage || $editPage['is_active']) ? 'checked' : ''; ?>>
                                                                    <label class="custom-control-label" for="is_active">
                                                                        <i class="fas fa-eye"></i> Página Activa
                                                                    </label>
                                                                </div>
                                                                <small class="text-muted">
                                                                    Controla si la página se muestra en el sitio público.
                                                                    <strong>Siempre aparece en el editor de menús.</strong>
                                                                </small>
                                                            </div>

                                                            <div class="form-group">
                                                                <label>URL de la Página</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><?php echo SITE_URL; ?>/</span>
                                                                    </div>
                                                                    <input type="text" class="form-control" id="slugPreview" readonly>
                                                                </div>
                                                            </div>

                                                            <?php if ($editPage): ?>
                                                                <div class="form-group">
                                                                    <label>Estadísticas</label>
                                                                    <div class="info-box bg-info">
                                                                        <span class="info-box-icon"><i class="fas fa-eye"></i></span>
                                                                        <div class="info-box-content">
                                                                            <span class="info-box-text">Visualizaciones</span>
                                                                            <span class="info-box-number"><?php echo number_format($editPage['view_count']); ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">SEO</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <label for="meta_title">Meta Título</label>
                                                                <input type="text" class="form-control" id="meta_title" name="meta_title"
                                                                    value="<?php echo $editPage ? htmlspecialchars($editPage['meta_title']) : ''; ?>"
                                                                    maxlength="60">
                                                                <small class="text-muted">Recomendado: 50-60 caracteres</small>
                                                            </div>

                                                            <div class="form-group">
                                                                <label for="meta_description">Meta Descripción</label>
                                                                <textarea class="form-control" id="meta_description" name="meta_description"
                                                                    rows="3" maxlength="160"><?php echo $editPage ? htmlspecialchars($editPage['meta_description']) : ''; ?></textarea>
                                                                <small class="text-muted">Recomendado: 150-160 caracteres</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> <?php echo $createMode ? 'Crear Página' : 'Actualizar Página'; ?>
                                            </button>
                                            <a href="?" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Cancelar
                                            </a>
                                            <?php if (!$createMode && $editPage): ?>
                                                <a href="<?php echo SITE_URL; ?>/<?php echo $editPage['slug']; ?>" target="_blank" class="btn btn-info">
                                                    <i class="fas fa-external-link-alt"></i> Ver Página
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Modal de confirmación de eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Confirmar Eliminación</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar la página <strong id="pageNameToDelete"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer y también eliminará la página de todos los menús.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="pageIdToDelete">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <!-- Summernote -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/summernote/summernote-bs4.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/summernote/lang/summernote-es-ES.js"></script>
    <!-- AdminLTE App -->
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            // Generar slug inicial si estamos editando
            <?php if (!$createMode && $editPage): ?>
                $('#slugPreview').val('<?php echo $editPage['slug']; ?>');
            <?php endif; ?>

            // Inicializar DataTable
            $('#pagesTable').DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "order": [], // Sin orden inicial - respeta el ORDER BY de SQL
                "columnDefs": [{
                        "orderable": false,
                        "targets": [7]
                    } // Desactivar orden en columna de acciones
                ]
            });

            // Inicializar Summernote
            $('#content').summernote({
                height: 400,
                lang: 'es-ES',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });

            // Generar slug automáticamente
            $('#title').on('input', function() {
                const title = $(this).val();
                const slug = generateSlug(title);
                $('#slugPreview').val(slug);
            });

            // Contador de caracteres para SEO
            $('#meta_title').on('input', function() {
                const length = $(this).val().length;
                const color = length > 60 ? 'text-danger' : (length > 50 ? 'text-warning' : 'text-success');
                $(this).next('small').removeClass('text-muted text-success text-warning text-danger').addClass(color);
            });

            $('#meta_description').on('input', function() {
                const length = $(this).val().length;
                const color = length > 160 ? 'text-danger' : (length > 150 ? 'text-warning' : 'text-success');
                $(this).next('small').removeClass('text-muted text-success text-warning text-danger').addClass(color);
            });
        });

        function generateSlug(text) {
            return text
                .toLowerCase()
                .replace(/[áàäâ]/g, 'a')
                .replace(/[éèëê]/g, 'e')
                .replace(/[íìïî]/g, 'i')
                .replace(/[óòöô]/g, 'o')
                .replace(/[úùüû]/g, 'u')
                .replace(/[ñ]/g, 'n')
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }

        function deletePage(id, name) {
            $('#pageNameToDelete').text(name);
            $('#pageIdToDelete').val(id);
            $('#deleteModal').modal('show');
        }
    </script>
</body>

</html>