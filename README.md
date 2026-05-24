# Reto Asturias Activa (PHP + MySQL)

Aplicación web de gamificación para rutas de senderismo/turismo activo en Asturias.

## Stack
- PHP 8.2
- MySQL/MariaDB
- HTML + CSS + JavaScript
- Leaflet (mapa interactivo)

## Funcionalidades incluidas
- Registro, login, logout y gestión de perfil.
- Verificación de email para reforzar seguridad de acceso.
- Recuperación y cambio de contraseña con token temporal enviado por correo.
- Cambio de correo electrónico con confirmación desde el nuevo email.
- Catalogo de rutas precargadas con filtros.
- Filtros avanzados de catalogo: zona, actividad y orden por calidad/popularidad.
- Usuarios logeados pueden proponer nuevas rutas (flujo con moderación admin).
- Los usuarios pueden editar o retirar sus propuestas mientras estén pendientes.
- Guardado de rutas favoritas y acceso rápido desde panel.
- Detalle de ruta con mapa y comentarios moderados.
- Reporte de incidencias en rutas por parte de usuarios.
- Registro de rutas completadas con puntos, nivel, logros y validación anti-fraude de tiempo.
- Rankings global, semanal y mensual.
- Retos comunitarios con inscripción y progreso automático.
- Exportación de historial personal (CSV) desde panel/perfil.
- Centro de notificaciones internas (actividad, revisiones y avisos).
- Modelo freemium con suscripción premium mensual de 4 EUR y bonus de puntos.
- Panel admin:
  - Métricas globales.
  - Seguimiento de usuarios premium e ingreso mensual estimado.
  - CRUD de rutas.
  - Revisión y moderación de rutas propuestas por usuarios.
  - Gestión y resolución de incidencias reportadas.
  - Gestión de retos.
  - Gestión de usuarios (activar/bloquear, rol admin).
  - Moderación de comentarios.

## Instalación rápida en XAMPP
1. Copia el proyecto en `C:\xampp\htdocs\Proyecto`.
2. Revisa `config.php` y pon tus credenciales reales de MySQL.
3. Abre phpMyAdmin y ejecuta:
   - `sql/schema.sql`
   - `sql/seed.sql`
4. Para cargar los spots de surf con fotos reales, ejecuta también:
   - `sql/add_surf_spots_asturias.sql`
5. Si ya tenías datos previos en la BD (sin recrearla), ejecuta también:
   - `sql/upgrade_top3_features.sql`
6. El módulo premium queda incluido en esa misma migración.
7. Verifica que Apache y MySQL están levantados.
8. Abre en navegador:
   - `http://localhost/Proyecto/index.php`

## Credenciales de prueba
- Admin:
  - Email: `admin@retoasturiasactiva.es`
  - Password: `Admin1234!`
- Usuario:
  - Email: `usuario@retoasturiasactiva.es`
  - Password: `Usuario123!`

## Configuración de BD
Archivo local: `config.php`

```php
<?php
return [
  'db_host' => 'localhost',
  'db_port' => '3307',
  'db_name' => 'reto_asturias_activa',
  'db_user' => 'root',
  'db_pass' => '',
  // No mostrar enlaces sensibles en pantalla; deben llegar siempre por correo.
  'show_debug_links' => false,
  'mail_from' => 'tu-correo@gmail.com',
  'smtp_host' => 'smtp.gmail.com',
  'smtp_port' => 587,
  'smtp_secure' => 'tls',
  'smtp_username' => 'tu-correo@gmail.com',
  // Contraseña de aplicación de Google, no la contraseña normal.
  'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
  'smtp_verify_peer' => true,
];
```

También puedes sobrescribir con variables de entorno:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `SMTP_PASSWORD`
