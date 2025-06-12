Estructura de Carpetas - sistema de Software
misistema/
├── admin/                          # Dashboard AdminLTE3
│   ├── assets/                     # CSS, JS, imágenes del admin
│   ├── includes/                   # Headers, sidebars del admin
│   ├── pages/                      # Páginas del dashboard
│   │   ├── config/                 # Configuraciones
│   │   ├── products/               # Gestión de productos
│   │   ├── users/                  # Gestión de usuarios
│   │   ├── orders/                 # Gestión de órdenes
│   │   └── reports/                # Reportes y estadísticas
│   ├── login.php                   # Login del admin
│   └── index.php                   # Dashboard principal
│
├── assets/                         # Frontend público
│   ├── css/                        # Estilos del sitio
│   ├── js/                         # JavaScript del sitio
│   ├── images/                     # Imágenes generales
│   └── uploads/                    # Imágenes subidas
│
├── config/                         # Configuraciones generales
│   ├── database.php                # Conexión a BD
│   ├── constants.php               # Constantes del sistema
│   ├── functions.php               # Funciones globales
│   └── settings.php                # Configuraciones del sitio
│
├── includes/                       # Componentes del frontend
│   ├── header.php                  # Header del sitio
│   ├── footer.php                  # Footer del sitio
│   ├── navbar.php                  # Navegación
│   └── modals.php                  # Modales reutilizables
│
├── pages/                          # Páginas públicas
│   ├── product.php                 # Página de producto
│   ├── category.php                # Página de categoría
│   ├── cart.php                    # Carrito de compras
│   ├── checkout.php                # Proceso de pago
│   ├── login.php                   # Login de usuarios
│   ├── register.php                # Registro de usuarios
│   └── dashboard.php               # Dashboard del cliente
│
├── api/                            # APIs y webhooks
│   ├── payments/                   # Webhooks de pagos
│   ├── downloads/                  # Control de descargas
│   └── auth/                       # Autenticación
│
├── downloads/                      # Archivos de productos
│   ├── products/                   # Organizados por producto
│   │   ├── sistema-ventas/
│   │   │   ├── v1.0/
│   │   │   ├── v1.1/
│   │   │   └── v1.2/
│   │   └── otro-sistema/
│   └── .htaccess                   # Protección de acceso directo
│
├── vendor/                         # Librerías externas
│   ├── adminlte/                   # AdminLTE3
│   ├── stripe/                     # SDK de Stripe
│   ├── paypal/                     # SDK de PayPal
│   └── mercadopago/                # SDK de MercadoPago
│
├── logs/                           # Archivos de log
│   ├── errors.log
│   ├── payments.log
│   └── downloads.log
│
├── .htaccess                       # URLs amigables
├── index.php                       # Página principal
├── robots.txt                      # SEO
└── sitemap.xml                     # Sitemap automático

# progreso del plan de desarollo del word
12/06/25 completado hasta la 2.2