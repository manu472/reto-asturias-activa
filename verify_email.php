<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    set_flash('warning', 'El enlace de verificación no es válido.');
    redirect('login.php');
}

$pdo = db();
$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare('
    SELECT evt.id AS token_id, evt.user_id, u.email_verified_at
    FROM email_verification_tokens evt
    JOIN users u ON u.id = evt.user_id
    WHERE evt.token_hash = :token_hash
      AND evt.used_at IS NULL
      AND evt.expires_at >= NOW()
    LIMIT 1
');
$stmt->execute([':token_hash' => $tokenHash]);
$row = $stmt->fetch();

if (!$row) {
    set_flash('warning', 'El enlace de verificación ha expirado o no existe.');
    redirect('resend_verification.php');
}

$pdo->beginTransaction();
try {
    $verifyUser = $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = :user_id');
    $verifyUser->execute([':user_id' => (int) $row['user_id']]);

    $useToken = $pdo->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE id = :token_id');
    $useToken->execute([':token_id' => (int) $row['token_id']]);

    $cleanup = $pdo->prepare('DELETE FROM email_verification_tokens WHERE user_id = :user_id AND id <> :token_id');
    $cleanup->execute([
        ':user_id' => (int) $row['user_id'],
        ':token_id' => (int) $row['token_id'],
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$redirectTo = current_user() ? 'dashboard.php' : 'login.php';
set_flash('success', 'Email verificado correctamente.');
redirect($redirectTo);
