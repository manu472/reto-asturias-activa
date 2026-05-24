<?php
declare(strict_types=1);

return [
    // Cambia estos valores según tu MySQL de XAMPP.
    'db_host' => '127.0.0.1',
    'db_port' => '3307',
    'db_name' => 'reto_asturias_activa',
    'db_user' => 'root',
    'db_pass' => '',
    // `demo` para probar pagos en local y `stripe` cuando pegues tus claves reales.
    'payment_mode' => 'demo',
    'premium_price_eur' => 4.00,
    'stripe_secret_key' => '',
    'stripe_publishable_key' => '',
    'stripe_webhook_secret' => '',
    // Tiempo mínimo para volver a registrar la misma ruta y puntuar otra vez.
    'route_recompletion_cooldown_hours' => 168,
    // No mostrar enlaces sensibles en pantalla; deben llegar siempre por correo.
    'show_debug_links' => false,
    'mail_from' => 'manualvarezsuarez@gmail.com',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'manualvarezsuarez@gmail.com',
    // Pega aqui tu contraseña de aplicación de Google, no tu contraseña normal.
    'smtp_password' => getenv('SMTP_PASSWORD') ?: 'jmiq wmxg rhox vjed',
    'smtp_timeout' => 20,
    // En XAMPP local puede fallar la verificación CA aunque la conexión vaya cifrada.
    'smtp_verify_peer' => false,
];
