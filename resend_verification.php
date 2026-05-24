<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('resend_verification.php');
    }

    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $message = 'Si la cuenta existe y está pendiente, hemos reenviado la verificación.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT id, name, email, is_active, email_verified_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && (int) $user['is_active'] === 1 && empty($user['email_verified_at'])) {
            $token = issue_email_verification_token(db(), (int) $user['id']);
            $emailSent = send_email_verification_link($user, $token);
            if (!$emailSent) {
                $message = 'No se pudo enviar el correo automáticamente en este entorno.';
            }
        }
    }

    set_flash('success', $message);
    redirect('resend_verification.php');
}

render_header('Reenviar verificación', [
    'description' => 'Reenvío privado del correo de verificación.',
    'canonical' => 'resend_verification.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 520px; margin-inline: auto;">
    <h1 class="section-title">Reenviar verificación de email</h1>
    <p class="meta">Si tu cuenta sigue pendiente, te enviaremos un nuevo correo de verificación.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div style="margin-bottom: 10px;">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" required autocomplete="email">
        </div>
        <button type="submit">Reenviar</button>
    </form>
    <p class="muted" style="margin-top: 12px;"><a href="<?= e(url('login.php')) ?>">Volver a iniciar sesión</a></p>
</section>
<?php render_footer(); ?>
