<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$token = trim((string) ($_POST['token'] ?? ($_GET['token'] ?? '')));

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    set_flash('warning', 'El enlace de recuperación no es válido.');
    redirect('forgot_password.php');
}

$tokenHash = hash('sha256', $token);
$tokenStmt = $pdo->prepare('
    SELECT prt.id AS token_id, prt.user_id, u.email
    FROM password_reset_tokens prt
    JOIN users u ON u.id = prt.user_id
    WHERE prt.token_hash = :token_hash
      AND prt.used_at IS NULL
      AND prt.expires_at >= NOW()
    LIMIT 1
');
$tokenStmt->execute([':token_hash' => $tokenHash]);
$tokenRow = $tokenStmt->fetch();

if (!$tokenRow) {
    set_flash('warning', 'El enlace de recuperación ha expirado o ya se usó.');
    redirect('forgot_password.php');
}

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    $newPassword = (string) ($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!is_strong_password($newPassword)) {
        set_flash('danger', 'La nueva contraseña debe tener mínimo 8 caracteres, letras y números.');
        redirect('reset_password.php?token=' . urlencode($token));
    }
    if ($newPassword !== $newPasswordConfirm) {
        set_flash('danger', 'La confirmación de contraseña no coincide.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    $pdo->beginTransaction();
    try {
        $updatePassword = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id');
        $updatePassword->execute([
            ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':user_id' => (int) $tokenRow['user_id'],
        ]);

        $useToken = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :token_id');
        $useToken->execute([':token_id' => (int) $tokenRow['token_id']]);

        $cleanupTokens = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND id <> :token_id');
        $cleanupTokens->execute([
            ':user_id' => (int) $tokenRow['user_id'],
            ':token_id' => (int) $tokenRow['token_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    clear_login_attempts((string) $tokenRow['email']);
    set_flash('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    redirect('login.php');
}

render_header('Restablecer contraseña', [
    'description' => 'Cambio privado de contraseña.',
    'canonical' => 'reset_password.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 520px; margin-inline: auto;">
    <h1 class="section-title">Nueva contraseña</h1>
    <p class="meta">El enlace es válido. Define tu nueva contraseña.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div style="margin-bottom: 10px;">
            <label for="new_password">Nueva contraseña</label>
            <input id="new_password" type="password" name="new_password" required autocomplete="new-password">
        </div>
        <div style="margin-bottom: 10px;">
            <label for="new_password_confirm">Confirmar nueva contraseña</label>
            <input id="new_password_confirm" type="password" name="new_password_confirm" required autocomplete="new-password">
        </div>
        <button type="submit">Guardar contraseña</button>
    </form>
</section>
<?php render_footer(); ?>
