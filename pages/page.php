<?php
// pages/page.php - Páginas dinámicas del CMS
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';


// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener slug de la página
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener página
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        header("HTTP/1.0 404 Not Found");
        include '../404.php';
        exit;
    }
    
    // Incrementar contador de vistas
    $stmt = $db->prepare("UPDATE pages SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
    $stmt->execute([$page['id']]);
    
} catch (Exception $e) {
    logError("Error en página dinámica: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../500.php';
    exit;
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = $page['meta_title'] ?: $page['title'];
$pageDescription = $page['meta_description'] ?: substr(strip_tags($page['content']), 0, 160);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($siteName); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/<?php echo $page['slug']; ?>">
    
    <!-- Favicon -->
    <?php if (Settings::get('site_favicon')): ?>
        <link rel="icon" type="image/png" href="<?php echo UPLOADS_URL; ?>/logos/<?php echo Settings::get('site_favicon'); ?>">
    <?php endif; ?>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0 60px;
            margin-bottom: 50px;
        }
        
        .page-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
        }
        
        .page-content h1,
        .page-content h2,
        .page-content h3,
        .page-content h4,
        .page-content h5,
        .page-content h6 {
            color: #2c3e50;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        .page-content h1 {
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        
        .page-content h2 {
            border-left: 4px solid #007bff;
            padding-left: 15px;
        }
        
        .page-content p {
            margin-bottom: 1.5rem;
            text-align: justify;
        }
        
        .page-content ul,
        .page-content ol {
            margin-bottom: 1.5rem;
        }
        
        .page-content li {
            margin-bottom: 0.5rem;
        }
        
        .page-content blockquote {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            padding: 20px;
            margin: 30px 0;
            font-style: italic;
        }
        
        .page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .page-content table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        
        .page-content table th,
        .page-content table td {
            padding: 12px;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        
        .page-content table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .page-content code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        
        .page-content pre {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 20px 0;
        }
        
        .page-meta {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .social-share {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 40px;
        }
        
        .share-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .share-button i {
            margin-right: 8px;
        }
        
        .share-facebook {
            background: #1877f2;
            color: white;
        }
        
        .share-twitter {
            background: #1da1f2;
            color: white;
        }
        
        .share-linkedin {
            background: #0077b5;
            color: white;
        }
        
        .share-whatsapp {
            background: #25d366;
            color: white;
        }
        
        .share-email {
            background: #6c757d;
            color: white;
        }
        
        .toc {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .toc ul {
            list-style: none;
            padding-left: 0;
        }
        
        .toc ul ul {
            padding-left: 20px;
            margin-top: 10px;
        }
        
        .toc a {
            color: #007bff;
            text-decoration: none;
            display: block;
            padding: 5px 0;
        }
        
        .toc a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 mb-3"><?php echo htmlspecialchars($page['title']); ?></h1>
                    
                    <!-- Page Meta -->
                    <div class="page-meta">
                        <div class="row text-dark">
                            <div class="col-md-6">
                                <small>
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Publicado: <?php echo date('d/m/Y', strtotime($page['created_at'])); ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <i class="fas fa-edit me-2"></i>
                                    Actualizado: <?php echo date('d/m/Y', strtotime($page['updated_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                            <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($page['title']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Tabla de Contenidos (si es necesaria) -->
                <?php 
                $content = $page['content'];
                $hasHeadings = preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i', $content, $headings);
                if ($hasHeadings && count($headings[0]) > 3):
                ?>
                    <div class="toc">
                        <h5><i class="fas fa-list me-2"></i>Tabla de Contenidos</h5>
                        <ul>
                            <?php
                            foreach ($headings[0] as $index => $heading) {
                                $level = $headings[1][$index];
                                $text = strip_tags($headings[2][$index]);
                                $id = 'heading-' . $index;
                                // Agregar ID al heading en el contenido
                                $content = str_replace($heading, str_replace('>', ' id="' . $id . '">', $heading), $content);
                                
                                echo '<li><a href="#' . $id . '">' . htmlspecialchars($text) . '</a></li>';
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Contenido de la Página -->
                <div class="page-content">
                    <?php echo $content; ?>
                </div>
                
                <!-- Compartir en Redes Sociales -->
                <div class="social-share">
                    <h5 class="mb-3"><i class="fas fa-share-alt me-2"></i>Compartir esta página</h5>
                    <div class="text-center">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/' . $page['slug']); ?>" 
                           target="_blank" class="share-button share-facebook">
                            <i class="fab fa-facebook-f"></i>Facebook
                        </a>
                        
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/' . $page['slug']); ?>&text=<?php echo urlencode($page['title']); ?>" 
                           target="_blank" class="share-button share-twitter">
                            <i class="fab fa-twitter"></i>Twitter
                        </a>
                        
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(SITE_URL . '/' . $page['slug']); ?>" 
                           target="_blank" class="share-button share-linkedin">
                            <i class="fab fa-linkedin-in"></i>LinkedIn
                        </a>
                        
                        <a href="https://wa.me/?text=<?php echo urlencode($page['title'] . ' - ' . SITE_URL . '/' . $page['slug']); ?>" 
                           target="_blank" class="share-button share-whatsapp">
                            <i class="fab fa-whatsapp"></i>WhatsApp
                        </a>
                        
                        <a href="mailto:?subject=<?php echo urlencode($page['title']); ?>&body=<?php echo urlencode('Te comparto esta página: ' . SITE_URL . '/' . $page['slug']); ?>" 
                           class="share-button share-email">
                            <i class="fas fa-envelope"></i>Email
                        </a>
                    </div>
                </div>
                
                <!-- Navegación de Páginas -->
                <div class="mt-5">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary">
                                <i class="fas fa-box me-2"></i>Ver Productos
                            </a>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="<?php echo SITE_URL; ?>/contacto" class="btn btn-outline-success">
                                <i class="fas fa-envelope me-2"></i>Contactanos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        // Smooth scroll para los enlaces de la tabla de contenidos
        document.querySelectorAll('.toc a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Resaltar la sección actual en la tabla de contenidos
        window.addEventListener('scroll', function() {
            const headings = document.querySelectorAll('[id^="heading-"]');
            const tocLinks = document.querySelectorAll('.toc a');
            
            let current = '';
            headings.forEach(heading => {
                const rect = heading.getBoundingClientRect();
                if (rect.top <= 100) {
                    current = heading.id;
                }
            });
            
            tocLinks.forEach(link => {
                link.classList.remove('text-primary', 'fw-bold');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('text-primary', 'fw-bold');
                }
            });
        });
    </script>
</body>
</html>