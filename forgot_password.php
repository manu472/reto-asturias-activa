<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('forgot_password.php');
    }

    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $message = 'Si el email existe, te hemos enviado un correo para restablecer la contraseña.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT id, name, email, is_active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && (int) $user['is_active'] === 1) {
            $token = issue_password_reset_token(db(), (int) $user['id']);
            send_password_reset_link($user, $token);
        }
    }

    set_flash('success', $message);
    redirect('forgot_password.php');
}

render_header('Recuperar contraseña', [
    'description' => 'Recuperación privada de contraseña.',
    'canonical' => 'forgot_password.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 520px; margin-inline: auto;">
    <h1 class="section-title">Recuperar contraseña</h1>
    <p class="meta">Introduce tu email y te enviaremos instrucciones para recuperar el acceso.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div style="margin-bottom: 10px;">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" required autocomplete="email">
        </div>
        <button type="submit">Enviar enlace</button>
    </form>
    <p class="muted" style="margin-top: 12px;"><a href="<?= e(url('login.php')) ?>">Volver a iniciar sesión</a></p>
</section>
<?php render_footer(); ?>
