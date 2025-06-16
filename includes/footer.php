<?php
// includes/footer.php - CORREGIDO
$siteName = getSetting('site_name', 'MiSistema');
$siteDescription = getSetting('site_description', 'Plataforma de venta de software');

// Obtener menú footer
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT * FROM menu_items 
        WHERE menu_location = 'footer' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $footerMenuItems = $stmt->fetchAll();
    
    // Obtener categorías para el footer
    $stmt = $db->query("
        SELECT * FROM categories 
        WHERE is_active = 1 
        ORDER BY name ASC 
        LIMIT 6
    ");
    $footerCategories = $stmt->fetchAll();
    
} catch (Exception $e) {
    $footerMenuItems = [];
    $footerCategories = [];
}
?>

<footer class="main-footer">
    <!-- Footer Content -->
    <div class="footer-content">
        <div class="container">
            <div class="row g-4">
                <!-- Company Info -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title"><?php echo htmlspecialchars($siteName); ?></h5>
                        <p class="footer-description">
                            <?php echo htmlspecialchars($siteDescription); ?>. 
                            Ofrecemos software de calidad para impulsar tu negocio.
                        </p>
                        
                        <!-- Contact Info -->
                        <div class="contact-info">
                            <?php if (getSetting('site_email')): ?>
                                <div class="contact-item">
                                    <i class="fas fa-envelope me-2"></i>
                                    <a href="mailto:<?php echo getSetting('site_email'); ?>">
                                        <?php echo getSetting('site_email'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (getSetting('contact_phone')): ?>
                                <div class="contact-item">
                                    <i class="fas fa-phone me-2"></i>
                                    <a href="tel:<?php echo getSetting('contact_phone'); ?>">
                                        <?php echo getSetting('contact_phone'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (getSetting('contact_address')): ?>
                                <div class="contact-item">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <span><?php echo htmlspecialchars(getSetting('contact_address')); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Social Media -->
                        <div class="social-media mt-4">
                            <h6 class="social-title">Síguenos</h6>
                            <div class="social-links">
                                <?php if (getSetting('facebook_url')): ?>
                                    <a href="<?php echo getSetting('facebook_url'); ?>" target="_blank" class="social-link facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (getSetting('twitter_url')): ?>
                                    <a href="<?php echo getSetting('twitter_url'); ?>" target="_blank" class="social-link twitter">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (getSetting('instagram_url')): ?>
                                    <a href="<?php echo getSetting('instagram_url'); ?>" target="_blank" class="social-link instagram">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (getSetting('linkedin_url')): ?>
                                    <a href="<?php echo getSetting('linkedin_url'); ?>" target="_blank" class="social-link linkedin">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (getSetting('youtube_url')): ?>
                                    <a href="<?php echo getSetting('youtube_url'); ?>" target="_blank" class="social-link youtube">
                                        <i class="fab fa-youtube"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Enlaces Rápidos</h5>
                        <ul class="footer-links">
                            <li><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/productos">Productos</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/sobre-nosotros">Sobre Nosotros</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/contacto">Contacto</a></li>
                            <?php if (isLoggedIn()): ?>
                                <li><a href="<?php echo SITE_URL; ?>/dashboard">Mi Cuenta</a></li>
                            <?php else: ?>
                                <li><a href="<?php echo SITE_URL; ?>/login">Iniciar Sesión</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/register">Registrarse</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Categories -->
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Categorías</h5>
                        <ul class="footer-links">
                            <?php if (empty($footerCategories)): ?>
                                <li><a href="<?php echo SITE_URL; ?>/categoria/sistemas-php">Sistemas PHP</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/categoria/zona-codigo">Zona de Código</a></li>
                            <?php else: ?>
                                <?php foreach ($footerCategories as $category): ?>
                                    <li>
                                        <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Newsletter & Menu Items -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Mantente Conectado</h5>
                        <p class="newsletter-text">Recibe las últimas noticias y ofertas especiales</p>
                        
                        <!-- Newsletter Form -->
                        <form class="newsletter-form mb-4" action="<?php echo SITE_URL; ?>/api/newsletter" method="POST">
                            <div class="input-group">
                                <input type="email" class="form-control" placeholder="Tu email" name="email" required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Menu Items dinámicos del footer -->
                        <?php if (!empty($footerMenuItems)): ?>
                            <div class="footer-menu-items">
                                <h6>Enlaces Útiles</h6>
                                <ul class="footer-links">
                                    <?php foreach ($footerMenuItems as $item): ?>
                                        <li>
                                            <a href="<?php echo processMenuUrl($item['url']); ?>" 
                                               <?php echo $item['target'] == '_blank' ? 'target="_blank"' : ''; ?>>
                                                <?php if ($item['icon']): ?>
                                                    <i class="<?php echo $item['icon']; ?> me-1"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="copyright">
                        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. Todos los derechos reservados.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="footer-bottom-links text-md-end">
                        <a href="<?php echo SITE_URL; ?>/poltica-de-privacidad">Política de Privacidad</a>
                        <span class="separator">|</span>
                        <a href="<?php echo SITE_URL; ?>/terminos-condiciones">Términos y Condiciones</a>
                        <?php if (isAdmin()): ?>
                            <span class="separator">|</span>
                            <a href="<?php echo ADMIN_URL; ?>">Admin</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Top -->
    <button class="back-to-top" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>
</footer>

<!-- Scripts adicionales -->
<script>
// Back to top functionality
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Show/hide back to top button
window.addEventListener('scroll', function() {
    const backToTop = document.querySelector('.back-to-top');
    if (backToTop) {
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    }
});

// Newsletter form handling
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[name="email"]').value;
            
            // TODO: Implementar envío real más adelante
            alert('¡Gracias por suscribirte! Te enviaremos nuestras novedades.');
            this.reset();
        });
    }
});
</script>