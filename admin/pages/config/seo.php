<?php
// admin/pages/config/seo.php
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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            // Google Analytics
            'google_analytics_id' => sanitize($_POST['google_analytics_id'] ?? ''),
            'google_analytics_enabled' => isset($_POST['google_analytics_enabled']) ? '1' : '0',
            'gtag_enhanced_ecommerce' => isset($_POST['gtag_enhanced_ecommerce']) ? '1' : '0',

            // Google Ads
            'google_ads_id' => sanitize($_POST['google_ads_id'] ?? ''),
            'google_ads_enabled' => isset($_POST['google_ads_enabled']) ? '1' : '0',
            'google_ads_conversion_id' => sanitize($_POST['google_ads_conversion_id'] ?? ''),

            // Facebook Pixel
            'facebook_pixel_id' => sanitize($_POST['facebook_pixel_id'] ?? ''),
            'facebook_pixel_enabled' => isset($_POST['facebook_pixel_enabled']) ? '1' : '0',

            // Meta Tags Globales
            'global_meta_description' => sanitize($_POST['global_meta_description'] ?? ''),
            'global_meta_keywords' => sanitize($_POST['global_meta_keywords'] ?? ''),
            'meta_author' => sanitize($_POST['meta_author'] ?? ''),
            'meta_robots' => sanitize($_POST['meta_robots'] ?? 'index,follow'),

            // Open Graph
            'og_default_image' => sanitize($_POST['og_default_image'] ?? ''),
            'og_site_name' => sanitize($_POST['og_site_name'] ?? ''),
            'og_locale' => sanitize($_POST['og_locale'] ?? 'es_ES'),

            // Twitter Cards
            'twitter_site' => sanitize($_POST['twitter_site'] ?? ''),
            'twitter_creator' => sanitize($_POST['twitter_creator'] ?? ''),
            'twitter_card_type' => sanitize($_POST['twitter_card_type'] ?? 'summary_large_image'),

            // Schema.org
            'schema_organization_name' => sanitize($_POST['schema_organization_name'] ?? ''),
            'schema_organization_logo' => sanitize($_POST['schema_organization_logo'] ?? ''),
            'schema_organization_url' => sanitize($_POST['schema_organization_url'] ?? ''),
            'schema_contact_phone' => sanitize($_POST['schema_contact_phone'] ?? ''),
            'schema_contact_email' => sanitize($_POST['schema_contact_email'] ?? ''),

            // Scripts Personalizados
            'custom_head_scripts' => $_POST['custom_head_scripts'] ?? '',
            'custom_body_scripts' => $_POST['custom_body_scripts'] ?? '',
            'custom_footer_scripts' => $_POST['custom_footer_scripts'] ?? '',

            // Configuraciones SEO
            'sitemap_enabled' => isset($_POST['sitemap_enabled']) ? '1' : '0',
            'robots_txt_enabled' => isset($_POST['robots_txt_enabled']) ? '1' : '0',
            'robots_txt_content' => $_POST['robots_txt_content'] ?? '',
            'canonical_urls_enabled' => isset($_POST['canonical_urls_enabled']) ? '1' : '0',
            'breadcrumbs_enabled' => isset($_POST['breadcrumbs_enabled']) ? '1' : '0'
        ];

        // Validaciones
        if ($settings['google_analytics_enabled'] == '1' && empty($settings['google_analytics_id'])) {
            throw new Exception('Para habilitar Google Analytics necesitas el ID de seguimiento');
        }

        if ($settings['google_ads_enabled'] == '1' && empty($settings['google_ads_id'])) {
            throw new Exception('Para habilitar Google Ads necesitas el ID de Google Ads');
        }

        if ($settings['facebook_pixel_enabled'] == '1' && empty($settings['facebook_pixel_id'])) {
            throw new Exception('Para habilitar Facebook Pixel necesitas el Pixel ID');
        }

        // Guardar configuraciones
        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
        }

        $success = 'Configuración de SEO guardada exitosamente';
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en configuración de SEO: " . $e->getMessage());
    }
}

// Obtener configuraciones actuales
$config = [
    // Google Analytics
    'google_analytics_id' => Settings::get('google_analytics_id', ''),
    'google_analytics_enabled' => Settings::get('google_analytics_enabled', '0'),
    'gtag_enhanced_ecommerce' => Settings::get('gtag_enhanced_ecommerce', '1'),

    // Google Ads
    'google_ads_id' => Settings::get('google_ads_id', ''),
    'google_ads_enabled' => Settings::get('google_ads_enabled', '0'),
    'google_ads_conversion_id' => Settings::get('google_ads_conversion_id', ''),

    // Facebook Pixel
    'facebook_pixel_id' => Settings::get('facebook_pixel_id', ''),
    'facebook_pixel_enabled' => Settings::get('facebook_pixel_enabled', '0'),

    // Meta Tags
    'global_meta_description' => Settings::get('global_meta_description', ''),
    'global_meta_keywords' => Settings::get('global_meta_keywords', ''),
    'meta_author' => Settings::get('meta_author', ''),
    'meta_robots' => Settings::get('meta_robots', 'index,follow'),

    // Open Graph
    'og_default_image' => Settings::get('og_default_image', ''),
    'og_site_name' => Settings::get('og_site_name', getSetting('site_name', 'MiSistema')),
    'og_locale' => Settings::get('og_locale', 'es_ES'),

    // Twitter
    'twitter_site' => Settings::get('twitter_site', ''),
    'twitter_creator' => Settings::get('twitter_creator', ''),
    'twitter_card_type' => Settings::get('twitter_card_type', 'summary_large_image'),

    // Schema.org
    'schema_organization_name' => Settings::get('schema_organization_name', getSetting('site_name', 'MiSistema')),
    'schema_organization_logo' => Settings::get('schema_organization_logo', ''),
    'schema_organization_url' => Settings::get('schema_organization_url', SITE_URL),
    'schema_contact_phone' => Settings::get('schema_contact_phone', ''),
    'schema_contact_email' => Settings::get('schema_contact_email', ''),

    // Scripts
    'custom_head_scripts' => Settings::get('custom_head_scripts', ''),
    'custom_body_scripts' => Settings::get('custom_body_scripts', ''),
    'custom_footer_scripts' => Settings::get('custom_footer_scripts', ''),

    // SEO
    'sitemap_enabled' => Settings::get('sitemap_enabled', '1'),
    'robots_txt_enabled' => Settings::get('robots_txt_enabled', '1'),
    'robots_txt_content' => Settings::get('robots_txt_content', "User-agent: *\nAllow: /\nSitemap: " . SITE_URL . "/sitemap.xml"),
    'canonical_urls_enabled' => Settings::get('canonical_urls_enabled', '1'),
    'breadcrumbs_enabled' => Settings::get('breadcrumbs_enabled', '1')
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración de SEO | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
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
                            <h1 class="m-0">Configuración de SEO</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Configuración</li>
                                <li class="breadcrumb-item active">SEO</li>
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

                    <form method="post">
                        <div class="row">

                            <!-- Google Analytics -->
                            <div class="col-md-6">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fab fa-google"></i> Google Analytics
                                        </h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="google_analytics_enabled" name="google_analytics_enabled"
                                                    <?php echo $config['google_analytics_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="google_analytics_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="google_analytics_id">Google Analytics ID</label>
                                            <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id"
                                                value="<?php echo htmlspecialchars($config['google_analytics_id']); ?>"
                                                placeholder="G-XXXXXXXXXX o UA-XXXXXXXX-X">
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="gtag_enhanced_ecommerce" name="gtag_enhanced_ecommerce"
                                                    <?php echo $config['gtag_enhanced_ecommerce'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="gtag_enhanced_ecommerce">Enhanced Ecommerce</label>
                                            </div>
                                            <small class="text-muted">Tracking avanzado de ventas y productos</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Google Ads -->
                            <div class="col-md-6">
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fab fa-google"></i> Google Ads
                                        </h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="google_ads_enabled" name="google_ads_enabled"
                                                    <?php echo $config['google_ads_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="google_ads_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="google_ads_id">Google Ads ID</label>
                                            <input type="text" class="form-control" id="google_ads_id" name="google_ads_id"
                                                value="<?php echo htmlspecialchars($config['google_ads_id']); ?>"
                                                placeholder="AW-XXXXXXXXX">
                                        </div>

                                        <div class="form-group">
                                            <label for="google_ads_conversion_id">Conversion ID</label>
                                            <input type="text" class="form-control" id="google_ads_conversion_id" name="google_ads_conversion_id"
                                                value="<?php echo htmlspecialchars($config['google_ads_conversion_id']); ?>"
                                                placeholder="Para tracking de conversiones">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Facebook Pixel -->
                            <div class="col-md-6">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fab fa-facebook"></i> Facebook Pixel
                                        </h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="facebook_pixel_enabled" name="facebook_pixel_enabled"
                                                    <?php echo $config['facebook_pixel_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="facebook_pixel_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="facebook_pixel_id">Facebook Pixel ID</label>
                                            <input type="text" class="form-control" id="facebook_pixel_id" name="facebook_pixel_id"
                                                value="<?php echo htmlspecialchars($config['facebook_pixel_id']); ?>"
                                                placeholder="123456789012345">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Meta Tags Globales -->
                            <div class="col-md-6">
                                <div class="card card-success">
                                    <div class="card-header">
                                        <h3 class="card-title">Meta Tags Globales</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="global_meta_description">Descripción Global</label>
                                            <textarea class="form-control" id="global_meta_description" name="global_meta_description"
                                                rows="3" placeholder="Descripción por defecto para SEO"><?php echo htmlspecialchars($config['global_meta_description']); ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="global_meta_keywords">Keywords Globales</label>
                                            <input type="text" class="form-control" id="global_meta_keywords" name="global_meta_keywords"
                                                value="<?php echo htmlspecialchars($config['global_meta_keywords']); ?>"
                                                placeholder="software, sistemas, php, desarrollo">
                                        </div>

                                        <div class="form-group">
                                            <label for="meta_author">Autor</label>
                                            <input type="text" class="form-control" id="meta_author" name="meta_author"
                                                value="<?php echo htmlspecialchars($config['meta_author']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="meta_robots">Robots</label>
                                            <select class="form-control" id="meta_robots" name="meta_robots">
                                                <option value="index,follow" <?php echo $config['meta_robots'] == 'index,follow' ? 'selected' : ''; ?>>Index, Follow</option>
                                                <option value="noindex,nofollow" <?php echo $config['meta_robots'] == 'noindex,nofollow' ? 'selected' : ''; ?>>No Index, No Follow</option>
                                                <option value="index,nofollow" <?php echo $config['meta_robots'] == 'index,nofollow' ? 'selected' : ''; ?>>Index, No Follow</option>
                                                <option value="noindex,follow" <?php echo $config['meta_robots'] == 'noindex,follow' ? 'selected' : ''; ?>>No Index, Follow</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Open Graph -->
                            <div class="col-md-6">
                                <div class="card card-secondary">
                                    <div class="card-header">
                                        <h3 class="card-title">Open Graph (Facebook)</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="og_site_name">Nombre del Sitio</label>
                                            <input type="text" class="form-control" id="og_site_name" name="og_site_name"
                                                value="<?php echo htmlspecialchars($config['og_site_name']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="og_default_image">Imagen por Defecto (URL)</label>
                                            <input type="url" class="form-control" id="og_default_image" name="og_default_image"
                                                value="<?php echo htmlspecialchars($config['og_default_image']); ?>"
                                                placeholder="https://tu-sitio.com/imagen.jpg">
                                        </div>

                                        <div class="form-group">
                                            <label for="og_locale">Idioma</label>
                                            <select class="form-control" id="og_locale" name="og_locale">
                                                <option value="es_ES" <?php echo $config['og_locale'] == 'es_ES' ? 'selected' : ''; ?>>Español</option>
                                                <option value="en_US" <?php echo $config['og_locale'] == 'en_US' ? 'selected' : ''; ?>>English</option>
                                                <option value="pt_BR" <?php echo $config['og_locale'] == 'pt_BR' ? 'selected' : ''; ?>>Português</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Twitter Cards -->
                            <div class="col-md-6">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fab fa-twitter"></i> Twitter Cards
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="twitter_site">@Usuario del Sitio</label>
                                            <input type="text" class="form-control" id="twitter_site" name="twitter_site"
                                                value="<?php echo htmlspecialchars($config['twitter_site']); ?>"
                                                placeholder="@tu_usuario">
                                        </div>

                                        <div class="form-group">
                                            <label for="twitter_creator">@Usuario Creador</label>
                                            <input type="text" class="form-control" id="twitter_creator" name="twitter_creator"
                                                value="<?php echo htmlspecialchars($config['twitter_creator']); ?>"
                                                placeholder="@tu_usuario">
                                        </div>

                                        <div class="form-group">
                                            <label for="twitter_card_type">Tipo de Card</label>
                                            <select class="form-control" id="twitter_card_type" name="twitter_card_type">
                                                <option value="summary" <?php echo $config['twitter_card_type'] == 'summary' ? 'selected' : ''; ?>>Summary</option>
                                                <option value="summary_large_image" <?php echo $config['twitter_card_type'] == 'summary_large_image' ? 'selected' : ''; ?>>Summary Large Image</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Schema.org -->
                            <div class="col-md-6">
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">Schema.org (Rich Snippets)</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="schema_organization_name">Nombre Organización</label>
                                            <input type="text" class="form-control" id="schema_organization_name" name="schema_organization_name"
                                                value="<?php echo htmlspecialchars($config['schema_organization_name']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="schema_organization_logo">Logo URL</label>
                                            <input type="url" class="form-control" id="schema_organization_logo" name="schema_organization_logo"
                                                value="<?php echo htmlspecialchars($config['schema_organization_logo']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="schema_contact_phone">Teléfono Contacto</label>
                                            <input type="text" class="form-control" id="schema_contact_phone" name="schema_contact_phone"
                                                value="<?php echo htmlspecialchars($config['schema_contact_phone']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scripts Personalizados -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card card-danger">
                                    <div class="card-header">
                                        <h3 class="card-title">Scripts en &lt;head&gt;</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="custom_head_scripts">Scripts Personalizados</label>
                                            <textarea class="form-control" id="custom_head_scripts" name="custom_head_scripts"
                                                rows="8" placeholder="<script>...código...</script>"><?php echo htmlspecialchars($config['custom_head_scripts']); ?></textarea>
                                            <small class="text-muted">Se insertará en el &lt;head&gt; de todas las páginas</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title">Scripts en &lt;body&gt;</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="custom_body_scripts">Scripts del Body</label>
                                            <textarea class="form-control" id="custom_body_scripts" name="custom_body_scripts"
                                                rows="8" placeholder="<script>...código...</script>"><?php echo htmlspecialchars($config['custom_body_scripts']); ?></textarea>
                                            <small class="text-muted">Se insertará después del &lt;body&gt;</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card card-success">
                                    <div class="card-header">
                                        <h3 class="card-title">Scripts en Footer</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="custom_footer_scripts">Scripts del Footer</label>
                                            <textarea class="form-control" id="custom_footer_scripts" name="custom_footer_scripts"
                                                rows="8" placeholder="<script>...código...</script>"><?php echo htmlspecialchars($config['custom_footer_scripts']); ?></textarea>
                                            <small class="text-muted">Se insertará antes del &lt;/body&gt;</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuraciones SEO -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card card-secondary">
                                    <div class="card-header">
                                        <h3 class="card-title">Configuraciones SEO</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="sitemap_enabled" name="sitemap_enabled"
                                                    <?php echo $config['sitemap_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="sitemap_enabled">Generar Sitemap Automático</label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="canonical_urls_enabled" name="canonical_urls_enabled"
                                                    <?php echo $config['canonical_urls_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="canonical_urls_enabled">URLs Canónicas</label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="breadcrumbs_enabled" name="breadcrumbs_enabled"
                                                    <?php echo $config['breadcrumbs_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="breadcrumbs_enabled">Breadcrumbs</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">Robots.txt</h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="robots_txt_enabled" name="robots_txt_enabled"
                                                    <?php echo $config['robots_txt_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="robots_txt_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="robots_txt_content">Contenido de robots.txt</label>
                                            <textarea class="form-control" id="robots_txt_content" name="robots_txt_content"
                                                rows="6"><?php echo htmlspecialchars($config['robots_txt_content']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Configuración de SEO
                                </button>
                                <a href="../../index.php" class="btn btn-secondary ml-2">
                                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                                </a>
                            </div>
                        </div>
                    </form>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- jQuery -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            // Habilitar/deshabilitar campos según switches
            function toggleAnalyticsConfig() {
                const enabled = $('#google_analytics_enabled').is(':checked');
                $('#google_analytics_id, #gtag_enhanced_ecommerce').prop('disabled', !enabled);
            }

            function toggleAdsConfig() {
                const enabled = $('#google_ads_enabled').is(':checked');
                $('#google_ads_id, #google_ads_conversion_id').prop('disabled', !enabled);
            }

            function togglePixelConfig() {
                const enabled = $('#facebook_pixel_enabled').is(':checked');
                $('#facebook_pixel_id').prop('disabled', !enabled);
            }

            function toggleRobotsConfig() {
                const enabled = $('#robots_txt_enabled').is(':checked');
                $('#robots_txt_content').prop('disabled', !enabled);
            }

            $('#google_analytics_enabled').on('change', toggleAnalyticsConfig);
            $('#google_ads_enabled').on('change', toggleAdsConfig);
            $('#facebook_pixel_enabled').on('change', togglePixelConfig);
            $('#robots_txt_enabled').on('change', toggleRobotsConfig);

            // Ejecutar al cargar
            toggleAnalyticsConfig();
            toggleAdsConfig();
            togglePixelConfig();
            toggleRobotsConfig();

            // Validar formato de IDs
            $('#google_analytics_id').on('blur', function() {
                const value = $(this).val();
                if (value && !value.match(/^(G-[A-Z0-9]+|UA-\d+-\d+)$/)) {
                    alert('Formato de Google Analytics ID no válido. Debe ser G-XXXXXXXXXX o UA-XXXXXXXX-X');
                }
            });

            $('#google_ads_id').on('blur', function() {
                const value = $(this).val();
                if (value && !value.match(/^AW-\d+$/)) {
                    alert('Formato de Google Ads ID no válido. Debe ser AW-XXXXXXXXX');
                }
            });
        });
    </script>
</body>

</html>