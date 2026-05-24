<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();
$pdo = db();

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('profile.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $avatarUrl = trim((string) ($_POST['avatar_url'] ?? ''));
        if (mb_strlen($name) < 3) {
            set_flash('danger', 'El nombre debe tener al menos 3 caracteres.');
            redirect('profile.php');
        }
        if ($avatarUrl !== '' && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            set_flash('danger', 'La URL del avatar no es válida.');
            redirect('profile.php');
        }

        $update = $pdo->prepare('
            UPDATE users
            SET name = :name, avatar_url = :avatar_url, updated_at = NOW()
            WHERE id = :id
        ');
        $update->execute([
            ':name' => $name,
            ':avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
            ':id' => (int) $user['id'],
        ]);

        set_flash('success', 'Perfil actualizado correctamente.');
        redirect('profile.php');
    }

    if ($action === 'request_email_change') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newEmail = mb_strtolower(trim((string) ($_POST['new_email'] ?? '')));

        $userStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute([':id' => (int) $user['id']]);
        $passwordHash = (string) $userStmt->fetchColumn();

        if (!password_verify($currentPassword, $passwordHash)) {
            set_flash('danger', 'La contraseña actual es incorrecta.');
            redirect('profile.php');
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'El nuevo correo electrónico no es válido.');
            redirect('profile.php');
        }

        if ($newEmail === mb_strtolower((string) $user['email'])) {
            set_flash('warning', 'Ese correo ya es el correo actual de tu cuenta.');
            redirect('profile.php');
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $exists->execute([
            ':email' => $newEmail,
            ':id' => (int) $user['id'],
        ]);
        if ($exists->fetchColumn()) {
            set_flash('danger', 'Ya existe una cuenta con ese correo electrónico.');
            redirect('profile.php');
        }

        $token = issue_email_change_token($pdo, (int) $user['id'], $newEmail);
        $emailSent = send_email_change_confirmation_link($user, $newEmail, $token);
        set_flash(
            $emailSent ? 'success' : 'danger',
            $emailSent
                ? 'Te hemos enviado un correo para confirmar el cambio de email.'
                : 'No se pudo enviar el correo de confirmación automáticamente en este entorno.'
        );
        redirect('profile.php');
    }

    if ($action === 'request_password_change') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');

        $userStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute([':id' => (int) $user['id']]);
        $passwordHash = (string) $userStmt->fetchColumn();

        if (!password_verify($currentPassword, $passwordHash)) {
            set_flash('danger', 'La contraseña actual es incorrecta.');
            redirect('profile.php');
        }

        $token = issue_password_reset_token($pdo, (int) $user['id']);
        $emailSent = send_password_reset_link($user, $token);
        set_flash(
            $emailSent ? 'success' : 'danger',
            $emailSent
                ? 'Te hemos enviado un correo para cambiar la contraseña.'
                : 'No se pudo enviar el correo de cambio de contraseña automáticamente en este entorno.'
        );
        redirect('profile.php');
    }

    if ($action === 'resend_verification') {
        $emailCheck = $pdo->prepare('SELECT email_verified_at FROM users WHERE id = :id LIMIT 1');
        $emailCheck->execute([':id' => (int) $user['id']]);
        $emailVerifiedAt = $emailCheck->fetchColumn();

        if (!empty($emailVerifiedAt)) {
            set_flash('success', 'Tu correo ya está verificado.');
            redirect('profile.php');
        }

        $token = issue_email_verification_token($pdo, (int) $user['id']);
        $emailSent = send_email_verification_link($user, $token);
        $message = $emailSent
            ? 'Te hemos enviado un nuevo correo de verificación.'
            : 'No se pudo enviar el correo automáticamente en este entorno.';
        set_flash($emailSent ? 'success' : 'danger', $message);
        redirect('profile.php');
    }
}

$profileStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$profileStmt->execute([':id' => (int) $user['id']]);
$profile = $profileStmt->fetch() ?: $user;

$statsStmt = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM route_completions WHERE user_id = :user_id_routes) AS routes_completed,
        (SELECT COALESCE(SUM(r.distance_km), 0) FROM route_completions rc JOIN routes r ON r.id = rc.route_id WHERE rc.user_id = :user_id_km) AS total_km,
        (SELECT COUNT(*) FROM user_achievements WHERE user_id = :user_id_achievements) AS achievements_unlocked,
        (SELECT COUNT(*) FROM route_favorites WHERE user_id = :user_id_favorites) AS favorites_total
');
$statsStmt->execute([
    ':user_id_routes' => (int) $user['id'],
    ':user_id_km' => (int) $user['id'],
    ':user_id_achievements' => (int) $user['id'],
    ':user_id_favorites' => (int) $user['id'],
]);
$stats = $statsStmt->fetch() ?: ['routes_completed' => 0, 'total_km' => 0, 'achievements_unlocked' => 0, 'favorites_total' => 0];

$historyStmt = $pdo->prepare('
    SELECT rc.completed_at, rc.duration_min, rc.points_obtained, rc.notes, r.name AS route_name
    FROM route_completions rc
    JOIN routes r ON r.id = rc.route_id
    WHERE rc.user_id = :user_id
    ORDER BY rc.completed_at DESC
    LIMIT 50
');
$historyStmt->execute([':user_id' => (int) $user['id']]);
$history = $historyStmt->fetchAll();
$isPremium = user_has_active_premium($profile);
$premiumPrice = premium_monthly_price($profile);
$activityInsights = user_activity_insights($pdo, (int) $user['id'], (int) $profile['level']);
$premiumInsights = user_premium_insights($pdo, (int) $user['id'], 6);

render_header('Mi perfil', [
    'description' => 'Perfil privado del usuario.',
    'canonical' => 'profile.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card">
    <h1 class="section-title">Perfil personal</h1>
    <p class="meta">Gestiona tus datos, revisa estadísticas e historial de actividad.</p>
    <div class="stats">
        <div class="stat"><small>Puntos totales</small><strong><?= (int) $profile['total_points'] ?></strong></div>
        <div class="stat"><small>Nivel</small><strong><?= (int) $profile['level'] ?></strong></div>
        <div class="stat"><small>Rutas completadas</small><strong><?= (int) $stats['routes_completed'] ?></strong></div>
        <div class="stat"><small>Kilómetros</small><strong><?= number_format((float) $stats['total_km'], 1) ?> km</strong></div>
        <div class="stat"><small>Logros</small><strong><?= (int) $stats['achievements_unlocked'] ?></strong></div>
        <div class="stat"><small>Favoritas</small><strong><?= (int) $stats['favorites_total'] ?></strong></div>
        <div class="stat"><small>Suscripción</small><strong><?= $isPremium ? 'Premium' : 'Gratuita' ?></strong></div>
    </div>
</section>

<section class="card" style="margin-top: 14px;">
    <h2 class="section-title">Racha y constancia</h2>
    <div class="stats">
        <div class="stat"><small>Racha actual</small><strong><?= (int) $activityInsights['current_streak'] ?> días</strong></div>
        <div class="stat"><small>Mejor racha</small><strong><?= (int) $activityInsights['best_streak'] ?> días</strong></div>
        <div class="stat"><small>Rutas últimos 30 días</small><strong><?= (int) $activityInsights['routes_30d'] ?></strong></div>
        <div class="stat"><small>Km últimos 30 días</small><strong><?= number_format((float) $activityInsights['km_30d'], 1) ?> km</strong></div>
    </div>
    <div class="grid grid-2" style="margin-top: 14px;">
        <div class="card card-soft">
            <h3 style="margin-bottom: 8px;">Objetivo mensual de rutas</h3>
            <p class="meta" style="margin-top: 0;"><?= (int) $activityInsights['routes_month'] ?> / <?= (int) $activityInsights['goal_routes'] ?> rutas este mes.</p>
            <div class="progress progress-lg"><span style="width: <?= (int) $activityInsights['routes_month_percent'] ?>%;"></span></div>
        </div>
        <div class="card card-soft">
            <h3 style="margin-bottom: 8px;">Objetivo mensual de kilómetros</h3>
            <p class="meta" style="margin-top: 0;"><?= number_format((float) $activityInsights['km_month'], 1) ?> / <?= number_format((float) $activityInsights['goal_km'], 1) ?> km este mes.</p>
            <div class="progress progress-lg"><span style="width: <?= (int) $activityInsights['km_month_percent'] ?>%;"></span></div>
        </div>
    </div>
</section>

<?php if ($isPremium): ?>
    <section class="card" style="margin-top: 14px;">
        <div class="section-heading">
            <div>
                <h2 class="section-title" style="margin-bottom: 4px;">Resumen premium personal</h2>
                <p class="meta" style="margin-top: 0;">Vista ampliada de tu actividad en los últimos <?= e((string) $premiumInsights['window_label']) ?>.</p>
            </div>
            <a class="button secondary button-small" href="<?= e(url('export_history.php?format=json')) ?>">Exportar JSON premium</a>
        </div>
        <div class="stats">
            <div class="stat"><small>Km acumulados</small><strong><?= number_format((float) $premiumInsights['km_window'], 1) ?> km</strong></div>
            <div class="stat"><small>Desnivel acumulado</small><strong><?= number_format((float) $premiumInsights['elevation_window'], 0) ?> m</strong></div>
            <div class="stat"><small>Zona top</small><strong><?= $premiumInsights['top_zone'] !== '' ? e((string) $premiumInsights['top_zone']) : 'Sin datos' ?></strong></div>
            <div class="stat"><small>Actividad top</small><strong><?= $premiumInsights['top_activity'] !== '' ? e((string) $premiumInsights['top_activity']) : 'Sin datos' ?></strong></div>
            <div class="stat"><small>Duración media</small><strong><?= format_minutes_human((int) $premiumInsights['avg_duration']) ?></strong></div>
            <div class="stat"><small>Ruta exigente</small><strong><?= $premiumInsights['toughest_route'] !== '' ? e((string) $premiumInsights['toughest_route']) : 'Sin datos' ?></strong></div>
        </div>
    </section>
<?php else: ?>
    <section class="card card-soft" style="margin-top: 14px;">
        <h2 class="section-title">Premium: lo que te falta por desbloquear</h2>
        <div class="stats">
            <div class="stat"><small>Analítica</small><strong>6 meses</strong></div>
            <div class="stat"><small>Exportación extra</small><strong>JSON premium</strong></div>
            <div class="stat"><small>Top de zonas</small><strong>Incluido</strong></div>
            <div class="stat"><small>Bonus por ruta</small><strong>+15%</strong></div>
        </div>
        <p class="meta" style="margin-top: 12px;">El plan premium refuerza tu perfil con estadísticas avanzadas, exportación extra y una lectura mucho más útil de tu progreso.</p>
        <a class="button button-small" href="<?= e(url('premium.php')) ?>">Ver premium</a>
    </section>
<?php endif; ?>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Editar datos</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_profile">
            <div style="margin-bottom: 10px;">
                <label>Correo actual</label>
                <input value="<?= e((string) $profile['email']) ?>" disabled>
            </div>
            <div style="margin-bottom: 10px;">
                <label for="name">Nombre</label>
                <input id="name" name="name" required maxlength="100" value="<?= e((string) $profile['name']) ?>">
            </div>
            <div style="margin-bottom: 10px;">
                <label for="avatar_url">URL de avatar (opcional)</label>
                <input id="avatar_url" name="avatar_url" maxlength="255" value="<?= e((string) ($profile['avatar_url'] ?? '')) ?>">
            </div>
            <button type="submit">Guardar cambios</button>
        </form>
    </article>

    <article class="card">
        <h2 class="section-title">Seguridad de cuenta</h2>
        <p class="meta" style="margin-top: 0;">
            Estado email:
            <strong><?= empty($profile['email_verified_at']) ? 'Pendiente de verificación' : 'Verificado' ?></strong>
        </p>
        <?php if (!empty($profile['email_verified_at'])): ?>
            <p class="meta">Verificado el <?= e((string) $profile['email_verified_at']) ?></p>
        <?php else: ?>
            <form method="post" style="margin-bottom: 12px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="resend_verification">
                <button type="submit" class="button secondary">Reenviar verificación</button>
            </form>
        <?php endif; ?>

        <p class="meta">
            Suscripción actual:
            <strong><?= $isPremium ? 'Premium (' . number_format($premiumPrice, 2) . ' EUR/mes)' : 'Gratuita' ?></strong>
        </p>
        <a class="button button-small" href="<?= e(url('premium.php')) ?>" style="margin-bottom: 12px;">Gestionar premium</a>

        <form method="post" style="margin-bottom: 16px;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="request_email_change">
            <div style="margin-bottom: 10px;">
                <label for="new_email">Nuevo correo electrónico</label>
                <input id="new_email" type="email" name="new_email" required maxlength="150" autocomplete="email">
            </div>
            <div style="margin-bottom: 10px;">
                <label for="email_current_password">Contraseña actual</label>
                <input id="email_current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>
            <button type="submit">Enviar confirmación de email</button>
        </form>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="request_password_change">
            <div style="margin-bottom: 10px;">
                <label for="password_current_password">Contraseña actual</label>
                <input id="password_current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>
            <button type="submit">Enviar correo para cambiar contraseña</button>
        </form>
    </article>
</section>

<section class="card" style="margin-top: 14px;">
    <h2 class="section-title">Historial de rutas</h2>
    <div class="stack" style="margin-bottom: 10px;">
        <a class="button button-small" href="<?= e(url('export_history.php')) ?>">Exportar historial (CSV)</a>
        <?php if ($isPremium): ?>
            <a class="button secondary button-small" href="<?= e(url('export_history.php?format=json')) ?>">Exportar historial premium (JSON)</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Ruta</th>
                    <th>Tiempo</th>
                    <th>Puntos</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="5">Aún no hay rutas registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($history as $item): ?>
                    <tr>
                        <td><?= e((string) $item['completed_at']) ?></td>
                        <td><?= e((string) $item['route_name']) ?></td>
                        <td><?= (int) $item['duration_min'] ?> min</td>
                        <td><?= (int) $item['points_obtained'] ?></td>
                        <td class="muted"><?= e((string) ($item['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
