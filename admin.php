<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$admin = require_admin();
$pdo = db();

$allowedTabs = ['dashboard', 'routes', 'challenges', 'users', 'comments', 'reports'];
$tab = (string) ($_GET['tab'] ?? 'dashboard');
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'dashboard';
}

function admin_redirect(string $tab): void
{
    redirect('admin.php?tab=' . $tab);
}

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        admin_redirect($tab);
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_route') {
            $routeId = (int) ($_POST['route_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $zone = trim((string) ($_POST['zone'] ?? ''));
            $difficulty = (string) ($_POST['difficulty'] ?? 'Media');
            $distanceKm = (float) ($_POST['distance_km'] ?? 0);
            $elevationM = (int) ($_POST['elevation_m'] ?? 0);
            $basePoints = (int) ($_POST['base_points'] ?? 0);
            $description = trim((string) ($_POST['description'] ?? ''));
            $coordsRaw = trim((string) ($_POST['coordinates_json'] ?? ''));
            $activityType = trim((string) ($_POST['activity_type'] ?? 'Senderismo'));
            $coverImage = trim((string) ($_POST['cover_image'] ?? ''));
            $isPreloaded = isset($_POST['is_preloaded']) ? 1 : 0;

            if (isset($_FILES['track_file']) && (int) ($_FILES['track_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $coordsRaw = (string) json_encode(
                    route_points_from_form_input($coordsRaw, $_FILES['track_file']),
                    JSON_UNESCAPED_UNICODE
                );
            }

            if ($name === '' || $zone === '' || $description === '' || $distanceKm <= 0 || $basePoints <= 0) {
                throw new RuntimeException('Faltan datos obligatorios para la ruta.');
            }
            if (!in_array($difficulty, ['Baja', 'Media', 'Alta', 'Muy Alta'], true)) {
                throw new RuntimeException('Dificultad no válida.');
            }
            if ($coverImage !== '' && !filter_var($coverImage, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('URL de portada no válida.');
            }
            $coords = json_decode($coordsRaw, true);
            if (!is_array($coords) || count($coords) < 2) {
                throw new RuntimeException('Coordenadas JSON inválidas.');
            }
            $coordsJson = (string) json_encode($coords, JSON_UNESCAPED_UNICODE);

            if ($routeId > 0) {
                $stmt = $pdo->prepare('
                    UPDATE routes
                    SET name = :name, zone = :zone, description = :description, distance_km = :distance_km,
                        elevation_m = :elevation_m, difficulty = :difficulty, activity_type = :activity_type,
                        base_points = :base_points, cover_image = :cover_image, coordinates_json = :coordinates_json,
                        is_preloaded = :is_preloaded, updated_at = NOW()
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':name' => $name,
                    ':zone' => $zone,
                    ':description' => $description,
                    ':distance_km' => $distanceKm,
                    ':elevation_m' => $elevationM,
                    ':difficulty' => $difficulty,
                    ':activity_type' => $activityType,
                    ':base_points' => $basePoints,
                    ':cover_image' => $coverImage !== '' ? $coverImage : null,
                    ':coordinates_json' => $coordsJson,
                    ':is_preloaded' => $isPreloaded,
                    ':id' => $routeId,
                ]);
                set_flash('success', 'Ruta actualizada.');
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO routes
                        (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, created_by, created_at)
                    VALUES
                        (:name, :zone, :description, :distance_km, :elevation_m, :difficulty, :activity_type, :base_points, :cover_image, :coordinates_json, :is_preloaded, :created_by, NOW())
                ');
                $stmt->execute([
                    ':name' => $name,
                    ':zone' => $zone,
                    ':description' => $description,
                    ':distance_km' => $distanceKm,
                    ':elevation_m' => $elevationM,
                    ':difficulty' => $difficulty,
                    ':activity_type' => $activityType,
                    ':base_points' => $basePoints,
                    ':cover_image' => $coverImage !== '' ? $coverImage : null,
                    ':coordinates_json' => $coordsJson,
                    ':is_preloaded' => $isPreloaded,
                    ':created_by' => (int) $admin['id'],
                ]);
                set_flash('success', 'Ruta creada.');
            }
            admin_redirect('routes');
        }

        if ($action === 'delete_route') {
            $routeId = (int) ($_POST['route_id'] ?? 0);
            $pdo->prepare('DELETE FROM routes WHERE id = :id')->execute([':id' => $routeId]);
            set_flash('success', 'Ruta eliminada.');
            admin_redirect('routes');
        }

        if ($action === 'moderate_route_submission') {
            $routeId = (int) ($_POST['route_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new RuntimeException('Estado de revision de ruta no valido.');
            }
            if (mb_strlen($reviewNote) > 255) {
                throw new RuntimeException('La nota de revision no puede superar 255 caracteres.');
            }

            $routeMetaStmt = $pdo->prepare('SELECT id, name, created_by FROM routes WHERE id = :id LIMIT 1');
            $routeMetaStmt->execute([':id' => $routeId]);
            $routeMeta = $routeMetaStmt->fetch();
            if (!$routeMeta) {
                throw new RuntimeException('La ruta indicada no existe.');
            }

            $moderateStmt = $pdo->prepare('
                UPDATE routes
                SET submission_status = :status,
                    review_note = :review_note,
                    reviewed_at = NOW(),
                    reviewed_by = :reviewed_by,
                    updated_at = NOW()
                WHERE id = :id AND submission_status = "pending"
            ');
            $moderateStmt->execute([
                ':status' => $status,
                ':review_note' => $reviewNote !== '' ? $reviewNote : null,
                ':reviewed_by' => (int) $admin['id'],
                ':id' => $routeId,
            ]);
            if ($moderateStmt->rowCount() === 0) {
                throw new RuntimeException('La ruta no existe o ya no esta pendiente.');
            }

            $notificationTitle = $status === 'approved' ? 'Tu ruta fue aprobada' : 'Tu ruta fue rechazada';
            $notificationMessage = $status === 'approved'
                ? 'La ruta "' . (string) $routeMeta['name'] . '" ya esta publicada.'
                : 'La ruta "' . (string) $routeMeta['name'] . '" fue rechazada.';
            if ($reviewNote !== '') {
                $notificationMessage .= ' Nota: ' . $reviewNote;
            }
            create_notification(
                $pdo,
                (int) $routeMeta['created_by'],
                'route_submission_review',
                $notificationTitle,
                $notificationMessage,
                'route.php?id=' . (int) $routeMeta['id']
            );

            set_flash('success', $status === 'approved' ? 'Ruta aprobada y publicada.' : 'Ruta rechazada.');
            admin_redirect('routes');
        }

        if ($action === 'save_challenge') {
            $challengeId = (int) ($_POST['challenge_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $targetType = (string) ($_POST['target_type'] ?? '');
            $targetValue = (float) ($_POST['target_value'] ?? 0);
            $rewardPoints = (int) ($_POST['reward_points'] ?? 0);
            $startDate = (string) ($_POST['start_date'] ?? '');
            $endDate = (string) ($_POST['end_date'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '' || $description === '' || $startDate === '' || $endDate === '' || $targetValue <= 0) {
                throw new RuntimeException('Faltan datos para el reto.');
            }
            if (!in_array($targetType, ['distance_km', 'routes_count', 'points'], true)) {
                throw new RuntimeException('Tipo de objetivo no válido.');
            }
            if ($startDate > $endDate) {
                throw new RuntimeException('Rango de fechas inválido.');
            }

            if ($challengeId > 0) {
                $stmt = $pdo->prepare('
                    UPDATE challenges
                    SET title = :title, description = :description, target_type = :target_type,
                        target_value = :target_value, reward_points = :reward_points, start_date = :start_date,
                        end_date = :end_date, is_active = :is_active, updated_at = NOW()
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':target_type' => $targetType,
                    ':target_value' => $targetValue,
                    ':reward_points' => $rewardPoints,
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                    ':is_active' => $isActive,
                    ':id' => $challengeId,
                ]);
                set_flash('success', 'Reto actualizado.');
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO challenges
                        (title, description, target_type, target_value, reward_points, start_date, end_date, is_active, created_by, created_at)
                    VALUES
                        (:title, :description, :target_type, :target_value, :reward_points, :start_date, :end_date, :is_active, :created_by, NOW())
                ');
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':target_type' => $targetType,
                    ':target_value' => $targetValue,
                    ':reward_points' => $rewardPoints,
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                    ':is_active' => $isActive,
                    ':created_by' => (int) $admin['id'],
                ]);
                set_flash('success', 'Reto creado.');
            }
            admin_redirect('challenges');
        }

        if ($action === 'toggle_user_status') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) $admin['id']) {
                throw new RuntimeException('No puedes desactivar tu propia cuenta.');
            }
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $userId]);
            set_flash('success', 'Estado de usuario actualizado.');
            admin_redirect('users');
        }

        if ($action === 'toggle_user_admin') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) $admin['id']) {
                throw new RuntimeException('No puedes cambiar tu propio rol administrador.');
            }
            $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = :id')->execute([':id' => $userId]);
            set_flash('success', 'Rol de usuario actualizado.');
            admin_redirect('users');
        }

        if ($action === 'moderate_comment') {
            $commentId = (int) ($_POST['comment_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new RuntimeException('Estado de moderación inválido.');
            }
            $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
            $stmt = $pdo->prepare('
                UPDATE comments
                SET status = :status, admin_note = :admin_note, moderated_at = NOW(), moderated_by = :moderator
                WHERE id = :id
            ');
            $stmt->execute([
                ':status' => $status,
                ':admin_note' => $adminNote !== '' ? $adminNote : null,
                ':moderator' => (int) $admin['id'],
                ':id' => $commentId,
            ]);
            set_flash('success', 'Comentario moderado.');
            admin_redirect('comments');
        }

        if ($action === 'moderate_route_report') {
            $reportId = (int) ($_POST['report_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
            if (!in_array($status, ['resolved', 'rejected'], true)) {
                throw new RuntimeException('Estado de incidencia no valido.');
            }
            if (mb_strlen($adminNote) > 255) {
                throw new RuntimeException('La nota de revision no puede superar 255 caracteres.');
            }

            $reportMetaStmt = $pdo->prepare('
                SELECT rr.id, rr.user_id, rr.route_id, r.name AS route_name
                FROM route_reports rr
                JOIN routes r ON r.id = rr.route_id
                WHERE rr.id = :id
                LIMIT 1
            ');
            $reportMetaStmt->execute([':id' => $reportId]);
            $reportMeta = $reportMetaStmt->fetch();
            if (!$reportMeta) {
                throw new RuntimeException('La incidencia no existe.');
            }

            $updateReport = $pdo->prepare('
                UPDATE route_reports
                SET status = :status,
                    admin_note = :admin_note,
                    reviewed_at = NOW(),
                    reviewed_by = :reviewed_by
                WHERE id = :id AND status = "pending"
            ');
            $updateReport->execute([
                ':status' => $status,
                ':admin_note' => $adminNote !== '' ? $adminNote : null,
                ':reviewed_by' => (int) $admin['id'],
                ':id' => $reportId,
            ]);
            if ($updateReport->rowCount() === 0) {
                throw new RuntimeException('La incidencia ya fue revisada.');
            }

            $notificationTitle = $status === 'resolved' ? 'Incidencia resuelta' : 'Incidencia revisada';
            $notificationMessage = $status === 'resolved'
                ? 'Tu incidencia en la ruta "' . (string) $reportMeta['route_name'] . '" se marco como resuelta.'
                : 'Tu incidencia en la ruta "' . (string) $reportMeta['route_name'] . '" fue rechazada.';
            if ($adminNote !== '') {
                $notificationMessage .= ' Nota: ' . $adminNote;
            }
            create_notification(
                $pdo,
                (int) $reportMeta['user_id'],
                'route_report_review',
                $notificationTitle,
                $notificationMessage,
                'route.php?id=' . (int) $reportMeta['route_id']
            );

            set_flash('success', 'Incidencia revisada correctamente.');
            admin_redirect('reports');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        admin_redirect($tab);
    }
}

$metrics = $pdo->query('
    SELECT
        (SELECT COUNT(*) FROM users) AS users_total,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) AS users_active,
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS users_new_30d,
        (SELECT COUNT(*) FROM users WHERE is_active = 1 AND is_premium = 1 AND (premium_expires_at IS NULL OR premium_expires_at >= NOW())) AS premium_users,
        (SELECT COALESCE(SUM(premium_price_month), 0) FROM users WHERE is_active = 1 AND is_premium = 1 AND (premium_expires_at IS NULL OR premium_expires_at >= NOW())) AS premium_income_month,
        (SELECT COUNT(*) FROM routes) AS routes_total,
        (SELECT COUNT(*) FROM routes WHERE submission_status = "pending") AS routes_pending,
        (SELECT COUNT(*) FROM route_completions WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS routes_last_month,
        (SELECT COUNT(DISTINCT user_id) FROM route_completions WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS active_users_last_month,
        (SELECT COUNT(*) FROM comments WHERE status = "pending") AS pending_comments,
        (SELECT COUNT(*) FROM route_reports WHERE status = "pending") AS pending_reports,
        (SELECT COUNT(*) FROM challenges WHERE is_active = 1 AND CURDATE() BETWEEN start_date AND end_date) AS active_challenges,
        (SELECT COUNT(*) FROM premium_payments WHERE status = "paid" AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS premium_payments_30d,
        (SELECT COALESCE(SUM(amount_eur), 0) FROM premium_payments WHERE status = "paid" AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS premium_revenue_30d,
        (SELECT COUNT(*) FROM routes WHERE submission_status = "approved" AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS routes_approved_30d
')->fetch();

$activeUsersBase = max(1, (int) ($metrics['users_active'] ?? 0));
$premiumConversionRate = round(((int) ($metrics['premium_users'] ?? 0) / $activeUsersBase) * 100, 1);
$engagementRate = round(((int) ($metrics['active_users_last_month'] ?? 0) / $activeUsersBase) * 100, 1);
$moderationBacklog = (int) ($metrics['routes_pending'] ?? 0) + (int) ($metrics['pending_comments'] ?? 0) + (int) ($metrics['pending_reports'] ?? 0);

$topRoutes = $pdo->query('
    SELECT r.name, COUNT(rc.id) AS total
    FROM routes r
    LEFT JOIN route_completions rc ON rc.route_id = r.id
    WHERE r.submission_status = "approved"
    GROUP BY r.id
    ORDER BY total DESC, r.name ASC
    LIMIT 8
')->fetchAll();

$recentPremiumPayments = [];
$recentRegistrations = [];
if ($tab === 'dashboard') {
    $recentPremiumPayments = $pdo->query('
        SELECT
            pp.amount_eur,
            pp.status,
            COALESCE(pp.paid_at, pp.created_at) AS payment_date,
            u.name AS user_name
        FROM premium_payments pp
        JOIN users u ON u.id = pp.user_id
        ORDER BY COALESCE(pp.paid_at, pp.created_at) DESC
        LIMIT 8
    ')->fetchAll();

    $recentRegistrations = $pdo->query('
        SELECT name, email, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 8
    ')->fetchAll();
}

$routes = [];
$editRoute = null;
$pendingRouteSubmissions = [];
if ($tab === 'routes') {
    $pendingRouteSubmissions = $pdo->query('
        SELECT r.*, u.name AS creator_name
        FROM routes r
        JOIN users u ON u.id = r.created_by
        WHERE r.submission_status = "pending"
        ORDER BY r.created_at ASC
    ')->fetchAll();

    $routes = $pdo->query('
        SELECT
            r.*,
            u.name AS creator_name,
            reviewer.name AS reviewer_name
        FROM routes r
        JOIN users u ON u.id = r.created_by
        LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
        ORDER BY r.created_at DESC
    ')->fetchAll();
    $editId = (int) ($_GET['edit_route_id'] ?? 0);
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM routes WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $editRoute = $stmt->fetch();
    }
}

$challenges = [];
$editChallenge = null;
if ($tab === 'challenges') {
    $challenges = $pdo->query('
        SELECT c.*, COUNT(cp.user_id) AS participants
        FROM challenges c
        LEFT JOIN challenge_participants cp ON cp.challenge_id = c.id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ')->fetchAll();
    $editId = (int) ($_GET['edit_challenge_id'] ?? 0);
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM challenges WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $editChallenge = $stmt->fetch();
    }
}

$users = [];
if ($tab === 'users') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $sql = 'SELECT id, name, email, total_points, level, is_admin, is_active, is_premium, premium_plan, premium_price_month, premium_expires_at, created_at FROM users WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (name LIKE :q OR email LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
}

$pendingComments = [];
$recentModerated = [];
if ($tab === 'comments') {
    $pendingComments = $pdo->query('
        SELECT c.*, u.name AS user_name, r.name AS route_name
        FROM comments c
        JOIN users u ON u.id = c.user_id
        JOIN routes r ON r.id = c.route_id
        WHERE c.status = "pending"
        ORDER BY c.created_at ASC
    ')->fetchAll();
    $recentModerated = $pdo->query('
        SELECT c.*, u.name AS user_name, r.name AS route_name
        FROM comments c
        JOIN users u ON u.id = c.user_id
        JOIN routes r ON r.id = c.route_id
        WHERE c.status IN ("approved", "rejected")
        ORDER BY c.moderated_at DESC
        LIMIT 20
    ')->fetchAll();
}

$pendingReports = [];
$recentReviewedReports = [];
if ($tab === 'reports') {
    $pendingReports = $pdo->query('
        SELECT
            rr.*,
            u.name AS user_name,
            r.name AS route_name
        FROM route_reports rr
        JOIN users u ON u.id = rr.user_id
        JOIN routes r ON r.id = rr.route_id
        WHERE rr.status = "pending"
        ORDER BY rr.created_at ASC
    ')->fetchAll();

    $recentReviewedReports = $pdo->query('
        SELECT
            rr.*,
            u.name AS user_name,
            r.name AS route_name,
            reviewer.name AS reviewer_name
        FROM route_reports rr
        JOIN users u ON u.id = rr.user_id
        JOIN routes r ON r.id = rr.route_id
        LEFT JOIN users reviewer ON reviewer.id = rr.reviewed_by
        WHERE rr.status IN ("resolved", "rejected")
        ORDER BY rr.reviewed_at DESC
        LIMIT 30
    ')->fetchAll();
}

render_header('Administración', [
    'description' => 'Panel interno de administracion del proyecto.',
    'canonical' => 'admin.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card">
    <h1 class="section-title">Panel de Administración</h1>
    <div class="tabs">
        <a href="<?= e(url('admin.php?tab=dashboard')) ?>" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="<?= e(url('admin.php?tab=routes')) ?>" class="<?= $tab === 'routes' ? 'active' : '' ?>">Rutas</a>
        <a href="<?= e(url('admin.php?tab=challenges')) ?>" class="<?= $tab === 'challenges' ? 'active' : '' ?>">Retos</a>
        <a href="<?= e(url('admin.php?tab=users')) ?>" class="<?= $tab === 'users' ? 'active' : '' ?>">Usuarios</a>
        <a href="<?= e(url('admin.php?tab=comments')) ?>" class="<?= $tab === 'comments' ? 'active' : '' ?>">Moderación</a>
        <a href="<?= e(url('admin.php?tab=reports')) ?>" class="<?= $tab === 'reports' ? 'active' : '' ?>">Incidencias</a>
    </div>
</section>

<?php if ($tab === 'dashboard'): ?>
    <section class="card">
        <div class="stats">
            <div class="stat"><small>Usuarios totales</small><strong><?= (int) $metrics['users_total'] ?></strong></div>
            <div class="stat"><small>Usuarios activos</small><strong><?= (int) $metrics['users_active'] ?></strong></div>
            <div class="stat"><small>Nuevos usuarios (30 dias)</small><strong><?= (int) $metrics['users_new_30d'] ?></strong></div>
            <div class="stat"><small>Premium activos</small><strong><?= (int) $metrics['premium_users'] ?></strong></div>
            <div class="stat"><small>Ingreso premium estimado</small><strong><?= number_format((float) $metrics['premium_income_month'], 2) ?> EUR</strong></div>
            <div class="stat"><small>Rutas publicadas</small><strong><?= (int) $metrics['routes_total'] ?></strong></div>
            <div class="stat"><small>Rutas pendientes</small><strong><?= (int) $metrics['routes_pending'] ?></strong></div>
            <div class="stat"><small>Completaciones (30 días)</small><strong><?= (int) $metrics['routes_last_month'] ?></strong></div>
            <div class="stat"><small>Comentarios pendientes</small><strong><?= (int) $metrics['pending_comments'] ?></strong></div>
            <div class="stat"><small>Incidencias pendientes</small><strong><?= (int) $metrics['pending_reports'] ?></strong></div>
            <div class="stat"><small>Retos activos</small><strong><?= (int) $metrics['active_challenges'] ?></strong></div>
        </div>
    </section>
    <section class="grid grid-2" style="margin-top: 14px;">
        <article class="card card-soft">
            <h2 class="section-title">Pulso del proyecto</h2>
            <div class="stats compact-stats">
                <div class="stat"><small>Conversion premium</small><strong><?= number_format($premiumConversionRate, 1) ?>%</strong></div>
                <div class="stat"><small>Usuarios activos (30 dias)</small><strong><?= number_format($engagementRate, 1) ?>%</strong></div>
                <div class="stat"><small>Pagos premium (30 dias)</small><strong><?= (int) $metrics['premium_payments_30d'] ?></strong></div>
                <div class="stat"><small>Ingresos cobrados (30 dias)</small><strong><?= number_format((float) $metrics['premium_revenue_30d'], 2) ?> EUR</strong></div>
                <div class="stat"><small>Rutas aprobadas (30 dias)</small><strong><?= (int) $metrics['routes_approved_30d'] ?></strong></div>
                <div class="stat"><small>Backlog moderacion</small><strong><?= $moderationBacklog ?></strong></div>
            </div>
        </article>
        <article class="card card-soft">
            <h2 class="section-title">Acciones rapidas</h2>
            <p class="meta" style="margin-top: 0;">Accesos directos a las tareas prioritarias del panel.</p>
            <div class="stack">
                <a class="button button-small" href="<?= e(url('admin.php?tab=routes')) ?>">Revisar rutas pendientes (<?= (int) $metrics['routes_pending'] ?>)</a>
                <a class="button secondary button-small" href="<?= e(url('admin.php?tab=comments')) ?>">Moderar comentarios (<?= (int) $metrics['pending_comments'] ?>)</a>
                <a class="button secondary button-small" href="<?= e(url('admin.php?tab=reports')) ?>">Resolver incidencias (<?= (int) $metrics['pending_reports'] ?>)</a>
                <a class="button secondary button-small" href="<?= e(url('admin.php?tab=users')) ?>">Gestionar usuarios</a>
            </div>
        </article>
    </section>
    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title">Rutas más completadas</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Ruta</th><th>Total completaciones</th></tr></thead>
                <tbody>
                    <?php foreach ($topRoutes as $row): ?>
                        <tr><td><?= e((string) $row['name']) ?></td><td><?= (int) $row['total'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="grid grid-2" style="margin-top: 14px;">
        <article class="card">
            <h2 class="section-title">Ultimos pagos premium</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Usuario</th><th>Importe</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentPremiumPayments)): ?>
                            <tr><td colspan="4">Todavia no hay pagos premium registrados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentPremiumPayments as $payment): ?>
                            <tr>
                                <td><?= e((string) $payment['user_name']) ?></td>
                                <td><?= number_format((float) $payment['amount_eur'], 2) ?> EUR</td>
                                <td><?= e((string) $payment['status']) ?></td>
                                <td><?= e((string) $payment['payment_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
        <article class="card">
            <h2 class="section-title">Ultimos registros</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Usuario</th><th>Email</th><th>Alta</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentRegistrations)): ?>
                            <tr><td colspan="3">No hay altas recientes.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentRegistrations as $registration): ?>
                            <tr>
                                <td><?= e((string) $registration['name']) ?></td>
                                <td><?= e((string) $registration['email']) ?></td>
                                <td><?= e((string) $registration['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($tab === 'routes'): ?>
    <?php $rf = $editRoute ?: ['id' => 0, 'name' => '', 'zone' => '', 'difficulty' => 'Media', 'distance_km' => '', 'elevation_m' => '', 'base_points' => '', 'description' => '', 'coordinates_json' => '[{"lat":43.3614,"lng":-5.8494},{"lat":43.37,"lng":-5.84}]', 'activity_type' => 'Senderismo', 'cover_image' => '', 'is_preloaded' => 1]; ?>
    <section class="card">
        <h2 class="section-title">Propuestas pendientes</h2>
        <?php if (empty($pendingRouteSubmissions)): ?>
            <p class="muted">No hay propuestas pendientes ahora mismo.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Ruta</th><th>Autor</th><th>Zona</th><th>Distancia</th><th>Fecha</th><th>Revision</th></tr></thead>
                    <tbody>
                        <?php foreach ($pendingRouteSubmissions as $pending): ?>
                            <tr>
                                <td><?= (int) $pending['id'] ?></td>
                                <td><a href="<?= e(url('route.php?id=' . (int) $pending['id'])) ?>"><?= e((string) $pending['name']) ?></a></td>
                                <td><?= e((string) $pending['creator_name']) ?></td>
                                <td><?= e((string) $pending['zone']) ?></td>
                                <td><?= number_format((float) $pending['distance_km'], 1) ?> km</td>
                                <td><?= e((string) $pending['created_at']) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="moderate_route_submission">
                                        <input type="hidden" name="route_id" value="<?= (int) $pending['id'] ?>">
                                        <input type="text" name="review_note" maxlength="255" placeholder="Nota opcional de revision">
                                        <div class="stack" style="margin-top: 6px;">
                                            <button class="button button-small" name="status" value="approved">Aprobar</button>
                                            <button class="button danger button-small" name="status" value="rejected">Rechazar</button>
                                            <a class="button secondary button-small" href="<?= e(url('admin.php?tab=routes&edit_route_id=' . (int) $pending['id'])) ?>">Editar</a>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title"><?= $editRoute ? 'Editar ruta' : 'Nueva ruta' ?></h2>
        <p class="meta">Las rutas creadas desde este formulario se publican de forma inmediata.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_route">
            <input type="hidden" name="route_id" value="<?= (int) $rf['id'] ?>">
            <div class="form-grid">
                <div><label>Nombre</label><input name="name" value="<?= e((string) $rf['name']) ?>" required></div>
                <div><label>Zona</label><input name="zone" value="<?= e((string) $rf['zone']) ?>" required></div>
                <div><label>Dificultad</label><select name="difficulty"><?php foreach (['Baja', 'Media', 'Alta', 'Muy Alta'] as $d): ?><option <?= $rf['difficulty'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
                <div><label>Actividad</label><input name="activity_type" value="<?= e((string) $rf['activity_type']) ?>"></div>
                <div><label>Distancia km</label><input type="number" step="0.1" name="distance_km" value="<?= e((string) $rf['distance_km']) ?>" required></div>
                <div><label>Desnivel m</label><input type="number" name="elevation_m" value="<?= e((string) $rf['elevation_m']) ?>" required></div>
                <div><label>Puntos base</label><input type="number" name="base_points" value="<?= e((string) $rf['base_points']) ?>" required></div>
                <div><label>URL portada</label><input name="cover_image" value="<?= e((string) $rf['cover_image']) ?>"></div>
            </div>
            <div style="margin-top: 10px;"><label>Descripcion</label><textarea name="description" required><?= e((string) $rf['description']) ?></textarea></div>
            <div style="margin-top: 10px;"><label>Coordenadas JSON</label><textarea name="coordinates_json" placeholder='[{"lat":43.3614,"lng":-5.8494},{"lat":43.37,"lng":-5.84}]'><?= e((string) $rf['coordinates_json']) ?></textarea></div>
            <div style="margin-top: 10px;">
                <label>Track GPX/KML</label>
                <input type="file" name="track_file" accept=".gpx,.kml,application/gpx+xml,application/vnd.google-earth.kml+xml,application/xml,text/xml">
                <small class="muted">Si subes un track, sustituira el JSON manual para guardar el trazado real de la ruta.</small>
            </div>
            <label style="margin-top: 10px;"><input type="checkbox" name="is_preloaded" value="1" <?= (int) $rf['is_preloaded'] === 1 ? 'checked' : '' ?> style="width:auto;"> Ruta precargada</label>
            <div class="stack" style="margin-top: 10px;">
                <button type="submit"><?= $editRoute ? 'Actualizar' : 'Crear' ?></button>
                <?php if ($editRoute): ?><a class="button secondary" href="<?= e(url('admin.php?tab=routes')) ?>">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card" style="margin-top: 14px;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Ruta</th><th>Autor</th><th>Estado</th><th>Dificultad</th><th>Puntos</th><th>Revision</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($routes as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><a href="<?= e(url('route.php?id=' . (int) $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                            <td><?= e((string) $row['creator_name']) ?></td>
                            <td><?= e((string) $row['submission_status']) ?></td>
                            <td><?= e((string) $row['difficulty']) ?></td>
                            <td><?= (int) $row['base_points'] ?></td>
                            <td><?= !empty($row['review_note']) ? e((string) $row['review_note']) : '-' ?></td>
                            <td>
                                <a class="button secondary button-small" href="<?= e(url('admin.php?tab=routes&edit_route_id=' . (int) $row['id'])) ?>">Editar</a>
                                <form method="post" class="inline" onsubmit="return confirm('Eliminar ruta?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_route">
                                    <input type="hidden" name="route_id" value="<?= (int) $row['id'] ?>">
                                    <button class="button danger button-small">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($tab === 'challenges'): ?>
    <?php $cf = $editChallenge ?: ['id' => 0, 'title' => '', 'description' => '', 'target_type' => 'distance_km', 'target_value' => '', 'reward_points' => '', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 day')), 'is_active' => 1]; ?>
    <section class="card" id="challenge-form">
        <h2 class="section-title"><?= $editChallenge ? 'Editar reto' : 'Nuevo reto' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_challenge">
            <input type="hidden" name="challenge_id" value="<?= (int) $cf['id'] ?>">
            <div class="form-grid">
                <div><label>Título</label><input name="title" value="<?= e((string) $cf['title']) ?>" required></div>
                <div><label>Tipo</label><select name="target_type"><?php foreach (['distance_km', 'routes_count', 'points'] as $t): ?><option value="<?= e($t) ?>" <?= $cf['target_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
                <div><label>Objetivo</label><input type="number" step="0.1" name="target_value" value="<?= e((string) $cf['target_value']) ?>" required></div>
                <div><label>Recompensa pts</label><input type="number" name="reward_points" value="<?= e((string) $cf['reward_points']) ?>" required></div>
                <div><label>Inicio</label><input type="date" name="start_date" value="<?= e((string) $cf['start_date']) ?>" required></div>
                <div><label>Fin</label><input type="date" name="end_date" value="<?= e((string) $cf['end_date']) ?>" required></div>
            </div>
            <div style="margin-top: 10px;"><label>Descripción</label><textarea name="description" required><?= e((string) $cf['description']) ?></textarea></div>
            <label style="margin-top: 10px;"><input type="checkbox" name="is_active" value="1" <?= (int) $cf['is_active'] === 1 ? 'checked' : '' ?> style="width:auto;"> Activo</label>
            <div class="stack" style="margin-top: 10px;">
                <button type="submit"><?= $editChallenge ? 'Actualizar' : 'Crear' ?></button>
                <?php if ($editChallenge): ?><a class="button secondary" href="<?= e(url('admin.php?tab=challenges')) ?>">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="card" style="margin-top: 14px;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Reto</th><th>Objetivo</th><th>Participantes</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($challenges as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= e((string) $row['title']) ?></td>
                            <td><?= number_format((float) $row['target_value'], 1) ?> <?= e((string) $row['target_type']) ?></td>
                            <td><?= (int) $row['participants'] ?></td>
                            <td><?= (int) $row['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                            <td><a class="button secondary button-small" href="<?= e(url('admin.php?tab=challenges&edit_challenge_id=' . (int) $row['id'])) ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($tab === 'users'): ?>
    <section class="card">
        <form method="get" class="filters">
            <input type="hidden" name="tab" value="users">
            <div><label>Buscar</label><input name="search" value="<?= e((string) ($_GET['search'] ?? '')) ?>"></div>
            <div><button type="submit">Aplicar</button></div>
        </form>
    </section>
    <section class="card" style="margin-top: 14px;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Puntos</th><th>Nivel</th><th>Plan</th><th>Precio</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= e((string) $row['name']) ?></td>
                            <td><?= e((string) $row['email']) ?></td>
                            <td><?= (int) $row['total_points'] ?></td>
                            <td><?= (int) $row['level'] ?></td>
                            <td>
                                <?= (int) $row['is_premium'] === 1 ? 'Premium' : 'Free' ?>
                                <?php if ((int) $row['is_premium'] === 1 && !empty($row['premium_expires_at'])): ?>
                                    <br><small class="muted">Hasta <?= e((string) $row['premium_expires_at']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $row['is_premium'] === 1 ? number_format((float) $row['premium_price_month'], 2) . ' EUR/mes' : '-' ?></td>
                            <td><?= (int) $row['is_admin'] ? 'Admin' : 'Usuario' ?></td>
                            <td><?= (int) $row['is_active'] ? 'Activo' : 'Bloqueado' ?></td>
                            <td>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_user_status">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <button class="button secondary button-small"><?= (int) $row['is_active'] ? 'Bloquear' : 'Activar' ?></button>
                                </form>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_user_admin">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <button class="button button-small"><?= (int) $row['is_admin'] ? 'Quitar admin' : 'Dar admin' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($tab === 'comments'): ?>
    <section class="card">
        <h2 class="section-title">Pendientes</h2>
        <?php if (empty($pendingComments)): ?><p class="muted">Sin pendientes.</p><?php endif; ?>
        <?php foreach ($pendingComments as $comment): ?>
            <div style="border-bottom: 1px solid #e0e7e0; padding: 10px 0;">
                <p style="margin: 0;"><strong><?= e((string) $comment['user_name']) ?></strong> en <strong><?= e((string) $comment['route_name']) ?></strong> - <?= (int) $comment['rating'] ?>/5</p>
                <p style="margin: 6px 0;"><?= nl2br(e((string) $comment['comment_text'])) ?></p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="moderate_comment">
                    <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                    <input type="text" name="admin_note" placeholder="Nota opcional">
                    <div class="stack" style="margin-top: 6px;">
                        <button class="button button-small" name="status" value="approved">Aprobar</button>
                        <button class="button danger button-small" name="status" value="rejected">Rechazar</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title">Moderados recientes</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fecha</th><th>Ruta</th><th>Usuario</th><th>Estado</th><th>Nota</th></tr></thead>
                <tbody>
                    <?php foreach ($recentModerated as $row): ?>
                        <tr>
                            <td><?= e((string) ($row['moderated_at'] ?? $row['created_at'])) ?></td>
                            <td><?= e((string) $row['route_name']) ?></td>
                            <td><?= e((string) $row['user_name']) ?></td>
                            <td><?= e((string) $row['status']) ?></td>
                            <td><?= e((string) ($row['admin_note'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($tab === 'reports'): ?>
    <section class="card">
        <h2 class="section-title">Incidencias pendientes</h2>
        <?php if (empty($pendingReports)): ?><p class="muted">Sin incidencias pendientes.</p><?php endif; ?>
        <?php foreach ($pendingReports as $report): ?>
            <div style="border-bottom: 1px solid #e0e7e0; padding: 10px 0;">
                <p style="margin: 0;">
                    <strong><?= e((string) $report['user_name']) ?></strong>
                    reporta en <strong><?= e((string) $report['route_name']) ?></strong>
                    - Motivo: <strong><?= e((string) $report['reason']) ?></strong>
                </p>
                <p style="margin: 6px 0;"><?= nl2br(e((string) $report['details'])) ?></p>
                <small class="muted">Creada: <?= e((string) $report['created_at']) ?></small>
                <form method="post" style="margin-top: 8px;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="moderate_route_report">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <input type="text" name="admin_note" maxlength="255" placeholder="Nota opcional de revision">
                    <div class="stack" style="margin-top: 6px;">
                        <button class="button button-small" name="status" value="resolved">Marcar resuelta</button>
                        <button class="button danger button-small" name="status" value="rejected">Rechazar reporte</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title">Incidencias revisadas</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fecha</th><th>Ruta</th><th>Usuario</th><th>Motivo</th><th>Estado</th><th>Nota</th></tr></thead>
                <tbody>
                    <?php if (empty($recentReviewedReports)): ?>
                        <tr><td colspan="6">No hay incidencias revisadas todavia.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentReviewedReports as $row): ?>
                        <tr>
                            <td><?= e((string) ($row['reviewed_at'] ?? $row['created_at'])) ?></td>
                            <td><?= e((string) $row['route_name']) ?></td>
                            <td><?= e((string) $row['user_name']) ?></td>
                            <td><?= e((string) $row['reason']) ?></td>
                            <td><?= e((string) $row['status']) ?></td>
                            <td><?= e((string) ($row['admin_note'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php render_footer(); ?>
