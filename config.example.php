<?php
declare(strict_types=1);

return [
    'db_host' => 'localhost',
    'db_port' => '3307',
    'db_name' => 'reto_asturias_activa',
    'db_user' => 'root',
    // Si tu root tiene contraseña en MySQL, escríbela aquí.
    'db_pass' => '',
    // `demo` para probar pagos en local. Cámbialo a `stripe` cuando añadas tus claves reales.
    'payment_mode' => 'demo',
    'premium_price_eur' => 4.00,
    'stripe_secret_key' => '',
    'stripe_publishable_key' => '',
    'stripe_webhook_secret' => '',
    // Tiempo mínimo para volver a registrar la misma ruta y puntuar otra vez.
    'route_recompletion_cooldown_hours' => 168,
    // No mostrar enlaces sensibles en pantalla; deben llegar siempre por correo.
    'show_debug_links' => false,
    'mail_from' => 'tu-correo@gmail.com',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'tu-correo@gmail.com',
    // Usa una contraseña de aplicación de Google, no la contraseña normal.
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_timeout' => 20,
    'smtp_verify_peer' => true,
];
