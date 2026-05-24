<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$redirectTo = current_user() ? 'profile.php' : 'login.php';
$token = trim((string) ($_GET['token'] ?? ''));

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    set_flash('warning', 'El enlace de cambio de correo no es válido.');
    redirect($redirectTo);
}

$pdo = db();
ensure_email_change_tokens_table($pdo);

$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare('
    SELECT ect.id AS token_id, ect.user_id, ect.new_email, u.email AS current_email
    FROM email_change_tokens ect
    JOIN users u ON u.id = ect.user_id
    WHERE ect.token_hash = :token_hash
      AND ect.used_at IS NULL
      AND ect.expires_at >= NOW()
    LIMIT 1
');
$stmt->execute([':token_hash' => $tokenHash]);
$row = $stmt->fetch();

if (!$row) {
    set_flash('warning', 'El enlace de cambio de correo ha expirado o ya se usó.');
    redirect($redirectTo);
}

$existing = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :user_id LIMIT 1');
$existing->execute([
    ':email' => (string) $row['new_email'],
    ':user_id' => (int) $row['user_id'],
]);

if ($existing->fetchColumn()) {
    $useToken = $pdo->prepare('UPDATE email_change_tokens SET used_at = NOW() WHERE id = :token_id');
    $useToken->execute([':token_id' => (int) $row['token_id']]);
    set_flash('danger', 'Ese correo electrónico ya está en uso por otra cuenta.');
    redirect($redirectTo);
}

$pdo->beginTransaction();
try {
    $updateUser = $pdo->prepare('
        UPDATE users
        SET email = :new_email,
            email_verified_at = NOW(),
            updated_at = NOW()
        WHERE id = :user_id
    ');
    $updateUser->execute([
        ':new_email' => (string) $row['new_email'],
        ':user_id' => (int) $row['user_id'],
    ]);

    $useToken = $pdo->prepare('UPDATE email_change_tokens SET used_at = NOW() WHERE id = :token_id');
    $useToken->execute([':token_id' => (int) $row['token_id']]);

    $cleanup = $pdo->prepare('DELETE FROM email_change_tokens WHERE user_id = :user_id AND id <> :token_id');
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

clear_login_attempts((string) $row['current_email']);
clear_login_attempts((string) $row['new_email']);

set_flash('success', 'Correo electrónico actualizado y verificado correctamente.');
redirect($redirectTo);
