<?php
// config/email.php - Sistema de email integrado con configuraciones del admin

/**
 * Clase para manejar el envío de emails usando las configuraciones del admin
 */
class EmailSystem
{

    /**
     * Enviar email usando configuraciones SMTP del admin
     */
    public static function sendEmail($to, $subject, $body, $isHTML = true)
    {
        // Obtener configuraciones SMTP
        $smtpEnabled = Settings::get('smtp_enabled', '0');

        if ($smtpEnabled != '1') {
            // Si SMTP no está habilitado, usar mail() de PHP
            return self::sendWithPHPMail($to, $subject, $body, $isHTML);
        }

        // Usar PHPMailer con configuraciones SMTP
        return self::sendWithSMTP($to, $subject, $body, $isHTML);
    }

    /**
     * Enviar email usando plantilla predefinida
     */
    public static function sendTemplateEmail($to, $templateType, $variables = [])
    {
        $templates = [
            'welcome' => [
                'subject' => Settings::get('welcome_email_subject', 'Bienvenido a {SITE_NAME}'),
                'body' => Settings::get('welcome_email_template', 'Hola {USER_NAME},\n\nBienvenido a nuestra plataforma.\n\nSaludos,\nEl equipo de {SITE_NAME}')
            ],
            'verification' => [
                'subject' => Settings::get('verification_email_subject', 'Verificar tu cuenta - Código: {VERIFICATION_CODE}'),
                'body' => Settings::get('verification_email_template', 'Hola {USER_NAME},\n\nTu código de verificación es: {VERIFICATION_CODE}\n\nEste código expira en 30 minutos.\n\nSaludos,\nEl equipo de {SITE_NAME}')
            ],
            'purchase' => [
                'subject' => Settings::get('purchase_email_subject', 'Compra confirmada - Orden #{ORDER_NUMBER}'),
                'body' => Settings::get('purchase_email_template', 'Hola {USER_NAME},\n\nTu compra ha sido confirmada.\n\nOrden: {ORDER_NUMBER}\nTotal: {ORDER_TOTAL}\n\nEnlaces de descarga:\n{DOWNLOAD_LINKS}\n\nGracias por tu compra,\nEl equipo de {SITE_NAME}')
            ],
            'donation' => [
                'subject' => Settings::get('donation_email_subject', 'Gracias por tu donación'),
                'body' => Settings::get('donation_email_template', 'Hola {USER_NAME},\n\n¡Muchas gracias por tu donación de {DONATION_AMOUNT}!\n\nTu apoyo nos ayuda a seguir creando software de calidad.\n\nCon gratitud,\nEl equipo de {SITE_NAME}')
            ]
        ];

        if (!isset($templates[$templateType])) {
            logError("Plantilla de email no encontrada: $templateType");
            return false;
        }

        $template = $templates[$templateType];

        // Reemplazar variables en subject y body
        $subject = self::replaceVariables($template['subject'], $variables);
        $body = self::replaceVariables($template['body'], $variables);

        // Agregar footer si está configurado
        $footer = Settings::get('email_footer', '');
        if (!empty($footer)) {
            $footer = self::replaceVariables($footer, $variables);
            $body .= "\n\n" . $footer;
        }

        // Convertir a HTML si el body contiene saltos de línea
        $isHTML = strpos($body, '<') !== false;
        if (!$isHTML) {
            $body = nl2br(htmlspecialchars($body));
            $body = "<html><body>$body</body></html>";
            $isHTML = true;
        }

        return self::sendEmail($to, $subject, $body, $isHTML);
    }

    /**
     * Reemplazar variables en plantillas
     */
    private static function replaceVariables($text, $variables)
    {
        // Variables del sitio (siempre disponibles)
        $siteVariables = [
            '{SITE_NAME}' => Settings::get('site_name', 'MiSistema'),
            '{SITE_URL}' => SITE_URL,
            '{CONTACT_EMAIL}' => Settings::get('site_email', ''),
            '{CURRENT_YEAR}' => date('Y'),
            '{CURRENT_DATE}' => date('d/m/Y'),
            '{UNSUBSCRIBE_LINK}' => SITE_URL . '/unsubscribe' // Implementar más adelante
        ];

        // Combinar variables del sitio con variables personalizadas
        $allVariables = array_merge($siteVariables, $variables);

        // Reemplazar variables
        return str_replace(array_keys($allVariables), array_values($allVariables), $text);
    }

    /**
     * Enviar con SMTP usando PHPMailer
     */
    private static function sendWithSMTP($to, $subject, $body, $isHTML)
    {
        try {
            // Por ahora, vamos a implementar una versión simplificada
            // Más adelante integraremos PHPMailer

            $smtpHost = Settings::get('smtp_host');
            $smtpPort = Settings::get('smtp_port', '587');
            $smtpUsername = Settings::get('smtp_username');
            $smtpPassword = Settings::get('smtp_password');
            $fromEmail = Settings::get('from_email', $smtpUsername);
            $fromName = Settings::get('from_name', Settings::get('site_name', 'MiSistema'));

            // Headers para email HTML
            $headers = [];
            $headers[] = "From: $fromName <$fromEmail>";
            $headers[] = "Reply-To: " . Settings::get('reply_to_email', $fromEmail);
            $headers[] = "MIME-Version: 1.0";

            if ($isHTML) {
                $headers[] = "Content-Type: text/html; charset=UTF-8";
            } else {
                $headers[] = "Content-Type: text/plain; charset=UTF-8";
            }

            $headers[] = "X-Mailer: MiSistema";

            // Por ahora usar mail() de PHP
            // TODO: Implementar PHPMailer real
            $success = mail($to, $subject, $body, implode("\r\n", $headers));

            if ($success) {
                logError("Email enviado exitosamente a: $to - Asunto: $subject", 'email.log');
            } else {
                logError("Error enviando email a: $to - Asunto: $subject", 'email_errors.log');
            }

            return $success;
        } catch (Exception $e) {
            logError("Error SMTP: " . $e->getMessage(), 'email_errors.log');
            return false;
        }
    }

    /**
     * Enviar con mail() de PHP
     */
    private static function sendWithPHPMail($to, $subject, $body, $isHTML)
    {
        $fromEmail = Settings::get('from_email', Settings::get('site_email', FROM_EMAIL));
        $fromName = Settings::get('from_name', Settings::get('site_name', FROM_NAME));

        $headers = [];
        $headers[] = "From: $fromName <$fromEmail>";
        $headers[] = "MIME-Version: 1.0";

        if ($isHTML) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Funciones específicas para cada tipo de email
     */

    public static function sendWelcomeEmail($userEmail, $userName)
    {
        return self::sendTemplateEmail($userEmail, 'welcome', [
            '{USER_NAME}' => $userName,
            '{USER_EMAIL}' => $userEmail
        ]);
    }

    public static function sendVerificationEmail($userEmail, $userName, $verificationCode)
    {
        return self::sendTemplateEmail($userEmail, 'verification', [
            '{USER_NAME}' => $userName,
            '{USER_EMAIL}' => $userEmail,
            '{VERIFICATION_CODE}' => $verificationCode
        ]);
    }

    public static function sendPurchaseEmail($userEmail, $userName, $orderNumber, $orderTotal, $downloadLinks)
    {
        return self::sendTemplateEmail($userEmail, 'purchase', [
            '{USER_NAME}' => $userName,
            '{USER_EMAIL}' => $userEmail,
            '{ORDER_NUMBER}' => $orderNumber,
            '{ORDER_TOTAL}' => formatPrice($orderTotal),
            '{DOWNLOAD_LINKS}' => $downloadLinks
        ]);
    }

    public static function sendDonationEmail($userEmail, $userName, $donationAmount)
    {
        return self::sendTemplateEmail($userEmail, 'donation', [
            '{USER_NAME}' => $userName,
            '{USER_EMAIL}' => $userEmail,
            '{DONATION_AMOUNT}' => formatPrice($donationAmount)
        ]);
    }

    public static function sendPasswordResetEmail($userEmail, $userName, $resetToken)
    {
        $siteName = Settings::get('site_name', 'MiSistema');
        $subject = "Recuperar contraseña - $siteName";
        $resetUrl = SITE_URL . "/pages/reset-password.php?token=" . $resetToken;

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h2 style='margin: 0; font-size: 28px;'>🔐 Recuperar Contraseña</h2>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>$siteName</p>
            </div>
            
            <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                <h3 style='color: #333; margin-bottom: 20px;'>Hola $userName,</h3>
                
                <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                    Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.
                </p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetUrl' style='
                        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                        color: white;
                        padding: 15px 30px;
                        text-decoration: none;
                        border-radius: 25px;
                        font-weight: bold;
                        display: inline-block;
                        box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
                    '>🔑 Restablecer Contraseña</a>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <p style='margin: 0; color: #856404; font-size: 14px;'>
                        <strong>⚠️ Importante:</strong>
                    </p>
                    <ul style='color: #856404; font-size: 14px; margin: 5px 0 0 0; padding-left: 20px;'>
                        <li>Este enlace expira en <strong>1 hora</strong></li>
                        <li>Solo funcionará una vez</li>
                        <li>Si no solicitaste este cambio, ignora este email</li>
                    </ul>
                </div>
                
                <p style='color: #666; line-height: 1.6; font-size: 14px; margin-top: 30px;'>
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    <a href='$resetUrl' style='color: #007bff; word-break: break-all;'>$resetUrl</a>
                </p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <div style='text-align: center; color: #999; font-size: 12px;'>
                    <p>Este email fue enviado desde $siteName</p>
                    <p>Si tienes problemas, contacta nuestro soporte</p>
                </div>
            </div>
        </div>
        ";

        return self::sendEmail($userEmail, $subject, $body, true);
    }

    /**
     * Enviar notificaciones al admin
     */
    public static function notifyAdmin($subject, $body, $type = 'general')
    {
        $notificationsEnabled = Settings::get('email_notifications_enabled', '1');
        if ($notificationsEnabled != '1') {
            return true; // No enviar si están deshabilitadas
        }

        $adminEmail = Settings::get('admin_notification_email');
        if (empty($adminEmail)) {
            return false; // No hay email de admin configurado
        }

        // Agregar prefijo al asunto
        $siteName = Settings::get('site_name', 'MiSistema');
        $prefixedSubject = "[$siteName] $subject";

        return self::sendEmail($adminEmail, $prefixedSubject, $body, true);
    }

    public static function notifyNewOrder($orderData)
    {
        if (Settings::get('notify_new_orders', '1') != '1') {
            return true;
        }

        $subject = "Nueva orden recibida - #{$orderData['order_number']}";
        $body = "
        <h2>Nueva Orden Recibida</h2>
        <p><strong>Orden:</strong> #{$orderData['order_number']}</p>
        <p><strong>Cliente:</strong> {$orderData['customer_name']} ({$orderData['customer_email']})</p>
        <p><strong>Total:</strong> " . formatPrice($orderData['total_amount']) . "</p>
        <p><strong>Método de pago:</strong> {$orderData['payment_method']}</p>
        <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
        ";

        return self::notifyAdmin($subject, $body, 'order');
    }

    public static function notifyNewUser($userData)
    {
        if (Settings::get('notify_new_users', '1') != '1') {
            return true;
        }

        $subject = "Nuevo usuario registrado";
        $body = "
        <h2>Nuevo Usuario Registrado</h2>
        <p><strong>Nombre:</strong> {$userData['first_name']} {$userData['last_name']}</p>
        <p><strong>Email:</strong> {$userData['email']}</p>
        <p><strong>País:</strong> {$userData['country']}</p>
        <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
        ";

        return self::notifyAdmin($subject, $body, 'user');
    }
}

// Funciones helper para mantener compatibilidad
function sendEmail($to, $subject, $body, $isHTML = true)
{
    return EmailSystem::sendEmail($to, $subject, $body, $isHTML);
}

function sendWelcomeEmail($userEmail, $userName)
{
    return EmailSystem::sendWelcomeEmail($userEmail, $userName);
}

function sendVerificationEmail($userEmail, $userName, $verificationCode)
{
    return EmailSystem::sendVerificationEmail($userEmail, $userName, $verificationCode);
}
