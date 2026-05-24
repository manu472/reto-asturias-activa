<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('login.php');
    }

    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (too_many_login_attempts($email)) {
        set_flash('warning', 'Demasiados intentos fallidos. Espera 15 minutos antes de volver a intentarlo.');
        redirect('login.php');
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        register_login_attempt($email);
        set_flash('danger', 'Credenciales incorrectas.');
        redirect('login.php');
    }

    if ((int) $user['is_active'] !== 1) {
        set_flash('warning', 'Tu cuenta está desactivada. Contacta con administración.');
        redirect('login.php');
    }

    if (empty($user['email_verified_at'])) {
        $verificationToken = issue_email_verification_token(db(), (int) $user['id']);
        $emailSent = send_email_verification_link($user, $verificationToken);
        clear_login_attempts($email);

        $message = $emailSent
            ? 'Tu cuenta aún no está verificada. Te hemos enviado un correo de verificación.'
            : 'Tu cuenta aún no está verificada. No se pudo enviar el correo automáticamente en este entorno.';
        set_flash('warning', $message);
        redirect('login.php');
    }

    clear_login_attempts($email);
    login_user($user);
    set_flash('success', 'Bienvenido de nuevo, ' . $user['name'] . '.');
    redirect((int) $user['is_admin'] === 1 ? 'admin.php' : 'dashboard.php');
}

render_header('Iniciar sesión', [
    'description' => 'Accede a tu cuenta en Reto Asturias Activa.',
    'canonical' => 'login.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 480px; margin-inline: auto;">
    <h1 class="section-title">Iniciar sesión</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div style="margin-bottom: 10px;">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" required autocomplete="email">
        </div>
        <div style="margin-bottom: 10px;">
            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit">Entrar</button>
    </form>
    <p class="muted" style="margin-top: 12px;">¿No tienes cuenta? <a href="<?= e(url('register.php')) ?>">Regístrate aquí</a>.</p>
    <p class="muted"><a href="<?= e(url('forgot_password.php')) ?>">He olvidado mi contraseña</a></p>
    <p class="muted"><a href="<?= e(url('resend_verification.php')) ?>">Reenviar verificación de email</a></p>
    <p class="muted">Demo admin: <strong>admin@retoasturiasactiva.es</strong> / <strong>Admin1234!</strong></p>
</section>
<?php render_footer(); ?>
