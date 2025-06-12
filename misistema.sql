-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-06-2025 a las 10:43:53
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `misistema`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@misistema.com', '$2y$10$iXZtL/qXXxm6rSiO4rXnp.wQmA2STYAnkxD30VZV6Q3pXi45P18TC', 'Administrador', 1, '2025-06-12 07:29:21', '2025-06-12 07:09:05', '2025-06-12 07:29:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `subtitle` varchar(300) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_url` varchar(500) DEFAULT NULL,
  `position` enum('home_slider','home_hero','sidebar','footer','header','promotion') DEFAULT 'home_slider',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `banners`
--

INSERT INTO `banners` (`id`, `title`, `subtitle`, `description`, `image`, `button_text`, `button_url`, `position`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Bienvenido a MiSistema', 'La mejor plataforma de software', 'Encuentra los mejores sistemas y componentes para tu negocio', 'banner1.jpg', 'Ver Productos', '/productos', 'home_slider', 1, 1, '2025-06-12 08:22:38', '2025-06-12 08:22:38'),
(2, 'Ofertas Especiales', '50% de descuento', 'Aprovecha nuestras ofertas por tiempo limitado en sistemas premium', 'banner2.jpg', 'Ver Ofertas', '/ofertas', 'home_slider', 1, 2, '2025-06-12 08:22:38', '2025-06-12 08:22:38'),
(3, 'Soporte 24/7', 'Estamos aquí para ayudarte', 'Nuestro equipo de soporte está disponible las 24 horas del día', 'banner3.jpg', 'Contactar', '/contacto', 'home_hero', 1, 1, '2025-06-12 08:22:38', '2025-06-12 08:22:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Sistemas PHP', 'sistemas-php', 'Sistemas completos desarrollados en PHP', NULL, 1, 0, '2025-06-12 07:09:05', '2025-06-12 07:09:05'),
(2, 'Zona de Código', 'zona-codigo', 'Componentes y códigos reutilizables', NULL, 1, 0, '2025-06-12 07:09:05', '2025-06-12 07:09:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `download_logs`
--

CREATE TABLE `download_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `version_id` int(11) NOT NULL,
  `license_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `download_type` enum('purchase','free','redownload') DEFAULT 'purchase',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `url` varchar(500) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `menu_location` enum('main','footer','sidebar','mobile','user') DEFAULT 'main',
  `icon` varchar(100) DEFAULT NULL,
  `target` enum('_self','_blank') DEFAULT '_self',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `menu_items`
--

INSERT INTO `menu_items` (`id`, `title`, `url`, `parent_id`, `menu_location`, `icon`, `target`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Inicio', '/', NULL, 'main', 'fas fa-home', '_self', 1, 1, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(2, 'Productos', '/productos', NULL, 'main', 'fas fa-box', '_self', 1, 2, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(3, 'Sistemas PHP', '/categoria/sistemas-php', 2, 'main', 'fab fa-php', '_self', 1, 1, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(4, 'Zona de Código', '/categoria/zona-codigo', 2, 'main', 'fas fa-code', '_self', 1, 2, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(5, 'Sobre Nosotros', '/sobre-nosotros', NULL, 'main', 'fas fa-info-circle', '_self', 1, 3, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(6, 'Contacto', '/contacto', NULL, 'main', 'fas fa-envelope', '_self', 1, 4, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(7, 'Términos y Condiciones', '/terminos-condiciones', NULL, 'footer', NULL, '_self', 1, 1, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(8, 'Política de Privacidad', '/politica-privacidad', NULL, 'footer', NULL, '_self', 1, 2, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(9, 'Soporte', '/contacto', NULL, 'footer', NULL, '_self', 1, 3, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(10, 'Mi Cuenta', '/dashboard', NULL, 'user', 'fas fa-user', '_self', 1, 1, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(11, 'Mis Compras', '/mis-compras', NULL, 'user', 'fas fa-shopping-bag', '_self', 1, 2, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(12, 'Configuración', '/configuracion', NULL, 'user', 'fas fa-cog', '_self', 1, 3, '2025-06-12 08:35:52', '2025-06-12 08:35:52'),
(13, 'Cerrar Sesión', '/logout', NULL, 'user', 'fas fa-sign-out-alt', '_self', 1, 4, '2025-06-12 08:35:52', '2025-06-12 08:35:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('stripe','paypal','mercadopago') NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_id` varchar(255) DEFAULT NULL,
  `payment_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_data`)),
  `customer_email` varchar(150) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `is_donation` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `content` longtext DEFAULT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pages`
--

INSERT INTO `pages` (`id`, `title`, `slug`, `content`, `meta_title`, `meta_description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Sobre Nosotros', 'sobre-nosotros', '<p>Información sobre la empresa...</p>', NULL, NULL, 1, '2025-06-12 07:09:05', '2025-06-12 07:09:05'),
(2, 'Términos y Condiciones', 'terminos-condiciones', '<p>Términos y condiciones...</p>', NULL, NULL, 1, '2025-06-12 07:09:05', '2025-06-12 07:09:05'),
(3, 'Política de Privacidad', 'poltica-de-privacidad', '<p>Política de privacidad...hola</p>', '', 'hola', 1, '2025-06-12 07:09:05', '2025-06-12 08:20:00'),
(4, 'Contacto', 'contacto', '<p>Información de contacto...</p>', '', 'hola', 1, '2025-06-12 07:09:05', '2025-06-12 08:19:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_free` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `demo_url` varchar(255) DEFAULT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  `download_limit` int(11) DEFAULT 5,
  `update_months` int(11) DEFAULT 12,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `show_name` tinyint(1) DEFAULT 1,
  `is_approved` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `product_versions`
--

CREATE TABLE `product_versions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `changelog` text DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','number','boolean','json') DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Mi sistema', 'text', '2025-06-12 07:09:04', '2025-06-12 08:18:06'),
(2, 'site_description', 'Plataforma de venta de software', 'textarea', '2025-06-12 07:09:04', '2025-06-12 08:18:06'),
(3, 'site_email', 'admin@misistema.com', 'text', '2025-06-12 07:09:04', '2025-06-12 08:18:06'),
(4, 'stripe_publishable_key', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(5, 'stripe_secret_key', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(6, 'paypal_client_id', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(7, 'paypal_client_secret', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(8, 'mercadopago_public_key', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(9, 'mercadopago_access_token', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(10, 'smtp_host', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(11, 'smtp_port', '587', 'number', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(12, 'smtp_username', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(13, 'smtp_password', '', 'text', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(14, 'google_analytics', '', 'textarea', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(15, 'custom_head_scripts', '', 'textarea', '2025-06-12 07:09:04', '2025-06-12 07:09:04'),
(19, 'contact_phone', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(20, 'contact_address', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(21, 'facebook_url', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(22, 'twitter_url', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(23, 'instagram_url', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(24, 'youtube_url', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(25, 'linkedin_url', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(26, 'site_keywords', '', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(27, 'maintenance_mode', '0', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(28, 'maintenance_message', 'Sitio en mantenimiento', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(29, 'timezone', 'America/Lima', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(30, 'currency', 'USD', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06'),
(31, 'currency_symbol', '$', 'text', '2025-06-12 08:17:16', '2025-06-12 08:18:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_licenses`
--

CREATE TABLE `user_licenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `downloads_used` int(11) DEFAULT 0,
  `downloads_limit` int(11) DEFAULT 5,
  `updates_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `version_id` (`version_id`),
  ADD KEY `license_id` (`license_id`);

--
-- Indices de la tabla `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indices de la tabla `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`);

--
-- Indices de la tabla `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product_review` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indices de la tabla `product_versions`
--
ALTER TABLE `product_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_version` (`product_id`,`version`);

--
-- Indices de la tabla `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `user_licenses`
--
ALTER TABLE `user_licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `product_versions`
--
ALTER TABLE `product_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_licenses`
--
ALTER TABLE `user_licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `download_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `download_logs_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `download_logs_ibfk_3` FOREIGN KEY (`version_id`) REFERENCES `product_versions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `download_logs_ibfk_4` FOREIGN KEY (`license_id`) REFERENCES `user_licenses` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `product_versions`
--
ALTER TABLE `product_versions`
  ADD CONSTRAINT `product_versions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_licenses`
--
ALTER TABLE `user_licenses`
  ADD CONSTRAINT `user_licenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_licenses_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_licenses_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
