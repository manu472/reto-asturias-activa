<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();
$pdo = db();
$routeId = (int) ($_GET['route_id'] ?? $_POST['route_id'] ?? 0);

if ($routeId <= 0) {
    set_flash('warning', 'Ruta no válida.');
    redirect('map.php');
}

$routeStmt = $pdo->prepare('SELECT * FROM routes WHERE id = :id AND submission_status = "approved" LIMIT 1');
$routeStmt->execute([':id' => $routeId]);
$route = $routeStmt->fetch();

if (!$route) {
    set_flash('warning', 'La ruta seleccionada no existe o todavia no esta aprobada.');
    redirect('map.php');
}
$durationBounds = completion_duration_bounds((float) $route['distance_km'], (string) $route['activity_type']);
$isSurfRoute = route_is_surf($route);
$surfDetails = $isSurfRoute ? route_surf_details($route) : null;
$completionLabel = $isSurfRoute ? 'sesion' : 'ruta';
$completionTitle = $isSurfRoute ? 'Registrar sesion de surf' : 'Registrar ruta completada';
$completionDescription = $isSurfRoute ? 'Registro privado de sesiones de surf.' : 'Registro privado de rutas completadas.';

$completionWindow = user_route_completion_window($pdo, (int) $user['id'], $routeId);

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('complete_route.php?route_id=' . $routeId);
    }
    if (!(bool) $completionWindow['allowed']) {
        set_flash('warning', 'Aun no puedes volver a registrar esta ' . $completionLabel . '. Disponible de nuevo el ' . (string) $completionWindow['next_available_at'] . '.');
        redirect('complete_route.php?route_id=' . $routeId);
    }

    $durationMin = (int) ($_POST['duration_min'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($durationMin < (int) $durationBounds['min'] || $durationMin > (int) $durationBounds['max']) {
        set_flash('danger', 'Tiempo fuera de rango para esta ' . $completionLabel . '. Debe estar entre ' . (int) $durationBounds['min'] . ' y ' . (int) $durationBounds['max'] . ' minutos.');
        redirect('complete_route.php?route_id=' . $routeId);
    }
    if (mb_strlen($notes) > 1000) {
        set_flash('danger', 'Las notas no pueden superar 1000 caracteres.');
        redirect('complete_route.php?route_id=' . $routeId);
    }

    $trackFilename = null;
    if (isset($_FILES['track_file']) && (int) $_FILES['track_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ((int) $_FILES['track_file']['error'] !== UPLOAD_ERR_OK) {
            set_flash('danger', 'Hubo un problema al subir el archivo GPX/KML.');
            redirect('complete_route.php?route_id=' . $routeId);
        }
        if ((int) $_FILES['track_file']['size'] > 2 * 1024 * 1024) {
            set_flash('danger', 'El archivo supera 2MB.');
            redirect('complete_route.php?route_id=' . $routeId);
        }
        $ext = mb_strtolower(pathinfo((string) $_FILES['track_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['gpx', 'kml'], true)) {
            set_flash('danger', 'Solo se permiten archivos GPX o KML.');
            redirect('complete_route.php?route_id=' . $routeId);
        }

        $tmpPath = (string) $_FILES['track_file']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimeByExt = [
            'gpx' => ['application/gpx+xml', 'application/xml', 'text/xml', 'application/octet-stream'],
            'kml' => ['application/vnd.google-earth.kml+xml', 'application/xml', 'text/xml', 'application/octet-stream'],
        ];
        if (!in_array($detectedMime, $allowedMimeByExt[$ext], true)) {
            set_flash('danger', 'El tipo de archivo no es valido para GPX/KML.');
            redirect('complete_route.php?route_id=' . $routeId);
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($tmpPath, 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false) {
            set_flash('danger', 'El fichero GPX/KML no contiene XML valido.');
            redirect('complete_route.php?route_id=' . $routeId);
        }
        $rootName = mb_strtolower((string) $xml->getName());
        if (($ext === 'gpx' && $rootName !== 'gpx') || ($ext === 'kml' && $rootName !== 'kml')) {
            set_flash('danger', 'El contenido del archivo no coincide con su extension.');
            redirect('complete_route.php?route_id=' . $routeId);
        }

        $targetDir = __DIR__ . '/uploads/tracks';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $trackFilename = sprintf('%d_%d_%s.%s', (int) $user['id'], $routeId, bin2hex(random_bytes(5)), $ext);
        $targetPath = $targetDir . '/' . $trackFilename;
        if (!move_uploaded_file((string) $_FILES['track_file']['tmp_name'], $targetPath)) {
            set_flash('danger', 'No se pudo guardar el archivo en el servidor.');
            redirect('complete_route.php?route_id=' . $routeId);
        }
    }

    $basePoints = (int) $route['base_points'] > 0 ? (int) $route['base_points'] : difficulty_default_points((string) $route['difficulty']);
    $factor = difficulty_factor((string) $route['difficulty']);

    $joinedChallengesCountStmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM challenge_participants cp
        JOIN challenges c ON c.id = cp.challenge_id
        WHERE cp.user_id = :user_id
          AND c.is_active = 1
          AND CURDATE() BETWEEN c.start_date AND c.end_date
    ');
    $joinedChallengesCountStmt->execute([':user_id' => (int) $user['id']]);
    $joinedChallengesCount = (int) $joinedChallengesCountStmt->fetchColumn();
    $challengeModifier = $joinedChallengesCount > 0 ? 1.10 : 1.00;
    $baseEarnedPoints = (int) round($basePoints * $factor * $challengeModifier);
    $premiumModifier = user_has_active_premium($user) ? premium_points_modifier() : 1.00;
    $premiumChallengeModifier = user_has_active_premium($user) ? premium_challenge_progress_modifier() : 1.00;
    $earnedPoints = (int) round($baseEarnedPoints * $premiumModifier);
    $premiumBonusPoints = max(0, $earnedPoints - $baseEarnedPoints);
    $premiumChallengeBoostActive = false;
    $challengeRewardBonus = 0;
    $achievementBonus = 0;
    $completedChallengeTitles = [];
    $unlockedAchievements = [];

    try {
        $pdo->beginTransaction();

        $insertCompletion = $pdo->prepare('
            INSERT INTO route_completions (user_id, route_id, completed_at, duration_min, points_obtained, notes, gpx_filename)
            VALUES (:user_id, :route_id, NOW(), :duration_min, :points_obtained, :notes, :gpx_filename)
        ');
        $insertCompletion->execute([
            ':user_id' => (int) $user['id'],
            ':route_id' => $routeId,
            ':duration_min' => $durationMin,
            ':points_obtained' => $earnedPoints,
            ':notes' => $notes !== '' ? $notes : null,
            ':gpx_filename' => $trackFilename,
        ]);

        add_points($pdo, (int) $user['id'], $earnedPoints);

        $activeChallengesStmt = $pdo->prepare('
            SELECT
                cp.challenge_id,
                cp.progress_value,
                cp.completed_at,
                c.title,
                c.target_type,
                c.target_value,
                c.reward_points
            FROM challenge_participants cp
            JOIN challenges c ON c.id = cp.challenge_id
            WHERE cp.user_id = :user_id
              AND c.is_active = 1
              AND CURDATE() BETWEEN c.start_date AND c.end_date
            FOR UPDATE
        ');
        $activeChallengesStmt->execute([':user_id' => (int) $user['id']]);
        $activeChallenges = $activeChallengesStmt->fetchAll();

        $updateChallengeProgress = $pdo->prepare('
            UPDATE challenge_participants
            SET progress_value = :progress_value, completed_at = :completed_at
            WHERE challenge_id = :challenge_id AND user_id = :user_id
        ');

        foreach ($activeChallenges as $challenge) {
            $delta = 0.0;
            $targetType = (string) $challenge['target_type'];
            if ($targetType === 'distance_km') {
                $delta = (float) $route['distance_km'];
            } elseif ($targetType === 'routes_count') {
                $delta = 1.0;
            } elseif ($targetType === 'points') {
                $delta = (float) $earnedPoints;
            }
            $baseChallengeDelta = $delta;
            if ($baseChallengeDelta > 0 && $premiumChallengeModifier > 1.0) {
                $delta *= $premiumChallengeModifier;
                $premiumChallengeBoostActive = true;
            }

            $currentProgress = (float) $challenge['progress_value'];
            $newProgress = $currentProgress + $delta;
            $wasCompleted = !empty($challenge['completed_at']);
            $justCompleted = !$wasCompleted && $newProgress >= (float) $challenge['target_value'];

            $updateChallengeProgress->execute([
                ':progress_value' => $newProgress,
                ':completed_at' => $justCompleted ? date('Y-m-d H:i:s') : $challenge['completed_at'],
                ':challenge_id' => (int) $challenge['challenge_id'],
                ':user_id' => (int) $user['id'],
            ]);

            if ($justCompleted) {
                $challengeRewardBonus += (int) $challenge['reward_points'];
                $completedChallengeTitles[] = (string) $challenge['title'];
            }
        }

        if ($challengeRewardBonus > 0) {
            add_points($pdo, (int) $user['id'], $challengeRewardBonus);
        }

        $statsStmt = $pdo->prepare('
            SELECT
                (SELECT total_points FROM users WHERE id = :user_id_points) AS total_points,
                (SELECT COUNT(*) FROM route_completions WHERE user_id = :user_id_routes) AS total_routes
        ');
        $statsStmt->execute([
            ':user_id_points' => (int) $user['id'],
            ':user_id_routes' => (int) $user['id'],
        ]);
        $stats = $statsStmt->fetch();
        $totalPoints = (int) ($stats['total_points'] ?? 0);
        $totalRoutes = (int) ($stats['total_routes'] ?? 0);

        $achievementsStmt = $pdo->prepare('
            SELECT a.*
            FROM achievements a
            LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = :user_id
            WHERE a.is_active = 1 AND ua.user_id IS NULL
        ');
        $achievementsStmt->execute([':user_id' => (int) $user['id']]);
        $achievements = $achievementsStmt->fetchAll();

        $insertAchievement = $pdo->prepare('
            INSERT INTO user_achievements (user_id, achievement_id, awarded_at)
            VALUES (:user_id, :achievement_id, NOW())
        ');
        foreach ($achievements as $achievement) {
            $needsPoints = (int) $achievement['criteria_points'];
            $needsRoutes = (int) $achievement['criteria_routes'];

            $meetsPoints = $needsPoints <= 0 || $totalPoints >= $needsPoints;
            $meetsRoutes = $needsRoutes <= 0 || $totalRoutes >= $needsRoutes;
            if (!$meetsPoints || !$meetsRoutes) {
                continue;
            }

            $insertAchievement->execute([
                ':user_id' => (int) $user['id'],
                ':achievement_id' => (int) $achievement['id'],
            ]);
            $achievementBonus += (int) $achievement['bonus_points'];
            $unlockedAchievements[] = (string) $achievement['title'];
        }

        if ($achievementBonus > 0) {
            add_points($pdo, (int) $user['id'], $achievementBonus);
        }

        $pdo->commit();
        session_cache_forget('user.route_preferences.' . (int) $user['id']);
        session_cache_forget('home.top_users');
        session_cache_forget('home.active_challenges');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'No se pudo registrar la ' . $completionLabel . ': ' . $exception->getMessage());
        redirect('complete_route.php?route_id=' . $routeId);
    }

    $messages = [($isSurfRoute ? 'Sesion registrada correctamente' : 'Ruta registrada correctamente') . ': +' . $earnedPoints . ' puntos.'];
    if ($challengeRewardBonus > 0) {
        $messages[] = 'Bonus por reto: +' . $challengeRewardBonus . ' puntos (' . implode(', ', $completedChallengeTitles) . ').';
    }
    if ($premiumBonusPoints > 0) {
        $messages[] = 'Bonus premium: +' . $premiumBonusPoints . ' puntos.';
    }
    if ($premiumChallengeBoostActive) {
        $messages[] = 'Progreso premium en retos: +' . premium_challenge_bonus_percent() . '%.';
    }
    if ($achievementBonus > 0) {
        $messages[] = 'Logros desbloqueados: ' . implode(', ', $unlockedAchievements) . ' (+' . $achievementBonus . ' pts).';
    }

    create_notification(
        $pdo,
        (int) $user['id'],
        'route_completion',
        $isSurfRoute ? 'Sesion de surf registrada' : 'Ruta completada',
        ($isSurfRoute ? 'Has registrado una sesion en "' : 'Has completado "') . (string) $route['name'] . '" y has ganado ' . $earnedPoints . ' puntos.',
        'dashboard.php'
    );
    if ($challengeRewardBonus > 0) {
        create_notification(
            $pdo,
            (int) $user['id'],
            'challenge_bonus',
            'Bonus de reto',
            'Has recibido ' . $challengeRewardBonus . ' puntos extra por completar retos activos.',
            'challenges.php'
        );
    }
    if ($premiumBonusPoints > 0) {
        create_notification(
            $pdo,
            (int) $user['id'],
            'premium_bonus',
            'Bonus premium aplicado',
            'Has recibido ' . $premiumBonusPoints . ' puntos extra por tener premium activo.',
            'premium.php'
        );
    }
    if ($premiumChallengeBoostActive) {
        create_notification(
            $pdo,
            (int) $user['id'],
            'premium_challenge_boost',
            'Impulso premium en retos',
            'Tu progreso en retos activos se ha acelerado un ' . premium_challenge_bonus_percent() . '% por tener premium.',
            'premium.php'
        );
    }
    if ($achievementBonus > 0) {
        create_notification(
            $pdo,
            (int) $user['id'],
            'achievement_unlocked',
            'Nuevos logros desbloqueados',
            'Logros: ' . implode(', ', $unlockedAchievements) . '. Bonus total: +' . $achievementBonus . ' puntos.',
            'dashboard.php'
        );
    }

    set_flash('success', implode(' ', $messages));
    redirect('dashboard.php');
}

render_header($isSurfRoute ? 'Registrar sesion' : 'Registrar ruta', [
    'description' => $completionDescription,
    'canonical' => 'complete_route.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 760px; margin-inline: auto;">
    <h1 class="section-title"><?= e($completionTitle) ?></h1>
    <?php if ($isSurfRoute && is_array($surfDetails)): ?>
        <p class="meta"><?= e((string) $route['name']) ?> - Surf - <?= e((string) $route['difficulty']) ?> - <?= e((string) $surfDetails['level']) ?></p>
        <div class="alert alert-warning surf-safety-alert">
            <strong>Antes de entrar:</strong> <?= e((string) $surfDetails['safety']) ?>
        </div>
    <?php else: ?>
        <p class="meta"><?= e((string) $route['name']) ?> - <?= number_format((float) $route['distance_km'], 1) ?> km - <?= e((string) $route['difficulty']) ?></p>
    <?php endif; ?>

    <?php if (!(bool) $completionWindow['allowed']): ?>
        <div class="alert alert-warning">
            Registraste esta <?= e($completionLabel) ?> por ultima vez el <?= e((string) $completionWindow['last_completed_at']) ?>.
            Para evitar abusos y mantener el ranking equilibrado, <?= $isSurfRoute ? 'el mismo spot' : 'la misma ruta' ?> vuelve a puntuar tras <?= e(route_recompletion_notice()) ?>.
            Podras registrarla otra vez a partir del <?= e((string) $completionWindow['next_available_at']) ?>.
        </div>
        <a class="button" href="<?= e(url('dashboard.php')) ?>">Volver a mi panel</a>
    <?php else: ?>
        <?php if (!empty($completionWindow['last_completed_at'])): ?>
            <div class="alert alert-success">
                <?= $isSurfRoute ? 'Ya habias registrado una sesion en este spot' : 'Ya habias completado esta ruta' ?> anteriormente el <?= e((string) $completionWindow['last_completed_at']) ?>.
                Como ya paso el periodo de espera de <?= e(route_recompletion_notice()) ?>, puedes volver a registrarla y sumar puntos otra vez.
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="route_id" value="<?= (int) $route['id'] ?>">
            <div class="form-grid" style="margin-bottom: 10px;">
                <div>
                    <label for="duration_min">Tiempo empleado (minutos)</label>
                    <input id="duration_min" name="duration_min" type="number" min="<?= (int) $durationBounds['min'] ?>" max="<?= (int) $durationBounds['max'] ?>" required>
                    <small class="muted">Rango recomendado para esta <?= e($completionLabel) ?>: <?= (int) $durationBounds['min'] ?> - <?= (int) $durationBounds['max'] ?> min.</small>
                </div>
                <div>
                    <label for="track_file">Archivo GPX/KML (opcional)</label>
                    <input id="track_file" type="file" name="track_file" accept=".gpx,.kml">
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label for="notes">Notas personales (opcional)</label>
                <textarea id="notes" name="notes" maxlength="1000" placeholder="<?= $isSurfRoute ? 'Condiciones, marea, viento, tamano de ola, sensaciones...' : 'Dificultad real, estado del sendero, recomendaciones...' ?>"></textarea>
            </div>
            <button type="submit"><?= $isSurfRoute ? 'Confirmar sesion' : 'Confirmar completacion' ?></button>
            <a class="button secondary" href="<?= e(url('route.php?id=' . (int) $route['id'])) ?>">Cancelar</a>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
