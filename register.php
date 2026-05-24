<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('register.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $acceptedTerms = isset($_POST['terms']);

    $errors = [];
    if (mb_strlen($name) < 3) {
        $errors[] = 'El nombre debe tener al menos 3 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no es válido.';
    }
    if (!is_strong_password($password)) {
        $errors[] = 'La contraseña debe tener mínimo 8 caracteres, letras y números.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Las contraseñas no coinciden.';
    }
    if (!$acceptedTerms) {
        $errors[] = 'Debes aceptar los términos y la política de privacidad.';
    }

    if (empty($errors)) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute([':email' => $email]);
        if ($exists->fetchColumn()) {
            $errors[] = 'Ya existe una cuenta con ese email.';
        }
    }

    if (!empty($errors)) {
        set_flash('danger', implode(' ', $errors));
        redirect('register.php');
    }

    $insert = db()->prepare('
        INSERT INTO users (name, email, password, total_points, level, is_active, is_admin, created_at)
        VALUES (:name, :email, :password, 0, 1, 1, 0, NOW())
    ');
    $insert->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_BCRYPT),
    ]);

    $id = (int) db()->lastInsertId();
    $userStmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $userStmt->execute([':id' => $id]);
    $createdUser = $userStmt->fetch();

    $verificationToken = issue_email_verification_token(db(), $id);
    $emailSent = send_email_verification_link($createdUser ?: ['email' => $email, 'name' => $name], $verificationToken);

    $message = $emailSent
        ? 'Cuenta creada correctamente. Te hemos enviado un correo para verificar tu cuenta antes de iniciar sesión.'
        : 'Cuenta creada correctamente, pero no se pudo enviar el correo automáticamente en este entorno.';
    set_flash($emailSent ? 'success' : 'warning', $message);
    redirect('login.php');
}

render_header('Registro', [
    'description' => 'Crea una cuenta en Reto Asturias Activa.',
    'canonical' => 'register.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 560px; margin-inline: auto;">
    <h1 class="section-title">Crear cuenta</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div style="margin-bottom: 10px;">
            <label for="name">Nombre y apellidos</label>
            <input id="name" name="name" required maxlength="100" autocomplete="name">
        </div>
        <div style="margin-bottom: 10px;">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" required maxlength="150" autocomplete="email">
        </div>
        <div class="form-grid" style="margin-bottom: 10px;">
            <div>
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirm">Confirmar contraseña</label>
                <input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password">
            </div>
        </div>
        <div style="margin-bottom: 12px;">
            <label>
                <input type="checkbox" name="terms" value="1" style="width: auto;">
                Acepto la política de privacidad y el uso de datos para gamificación.
            </label>
        </div>
        <button type="submit">Registrarme</button>
    </form>
    <p class="muted" style="margin-top: 12px;">¿Ya tienes cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesión</a>.</p>
</section>
<?php render_footer(); ?>
