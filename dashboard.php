<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();
$pdo = db();

$refreshStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$refreshStmt->execute([':id' => (int) $user['id']]);
$user = $refreshStmt->fetch() ?: $user;

$statsStmt = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM route_completions WHERE user_id = :user_id_routes) AS total_routes,
        (SELECT COUNT(*) FROM user_achievements WHERE user_id = :user_id_achievements) AS total_achievements,
        (SELECT COUNT(*) FROM route_favorites WHERE user_id = :user_id_favorites) AS total_favorites,
        (SELECT COUNT(*) + 1 FROM users WHERE total_points > (SELECT total_points FROM users WHERE id = :user_id_rank) AND is_active = 1) AS ranking_position
');
$statsStmt->execute([
    ':user_id_routes' => (int) $user['id'],
    ':user_id_achievements' => (int) $user['id'],
    ':user_id_favorites' => (int) $user['id'],
    ':user_id_rank' => (int) $user['id'],
]);
$stats = $statsStmt->fetch() ?: ['total_routes' => 0, 'total_achievements' => 0, 'total_favorites' => 0, 'ranking_position' => 1];

$recentCompletionsStmt = $pdo->prepare('
    SELECT rc.completed_at, rc.duration_min, rc.points_obtained, r.name AS route_name, r.distance_km
    FROM route_completions rc
    JOIN routes r ON r.id = rc.route_id
    WHERE rc.user_id = :user_id
    ORDER BY rc.completed_at DESC
    LIMIT 10
');
$recentCompletionsStmt->execute([':user_id' => (int) $user['id']]);
$recentCompletions = $recentCompletionsStmt->fetchAll();

$favoriteRoutesStmt = $pdo->prepare('
    SELECT r.id, r.name, r.zone, r.distance_km, r.difficulty, rf.created_at AS favorited_at
    FROM route_favorites rf
    JOIN routes r ON r.id = rf.route_id
    WHERE rf.user_id = :user_id
    ORDER BY rf.created_at DESC
    LIMIT 8
');
$favoriteRoutesStmt->execute([':user_id' => (int) $user['id']]);
$favoriteRoutes = $favoriteRoutesStmt->fetchAll();

$allAchievementsStmt = $pdo->prepare('
    SELECT
        a.*,
        ua.awarded_at
    FROM achievements a
    LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = :user_id
    WHERE a.is_active = 1
    ORDER BY a.criteria_points ASC, a.criteria_routes ASC, a.id ASC
');
$allAchievementsStmt->execute([':user_id' => (int) $user['id']]);
$allAchievements = $allAchievementsStmt->fetchAll();

$joinedChallengesStmt = $pdo->prepare('
    SELECT
        c.*,
        cp.progress_value,
        cp.completed_at
    FROM challenge_participants cp
    JOIN challenges c ON c.id = cp.challenge_id
    WHERE cp.user_id = :user_id
    ORDER BY c.end_date ASC
');
$joinedChallengesStmt->execute([':user_id' => (int) $user['id']]);
$joinedChallenges = $joinedChallengesStmt->fetchAll();

$totalRoutes = (int) $stats['total_routes'];
$totalPoints = (int) $user['total_points'];
$isPremium = user_has_active_premium($user);
$premiumPrice = premium_monthly_price($user);
$premiumMonthly = ['routes_30d' => 0, 'km_30d' => 0.0, 'points_30d' => 0];
$activityInsights = user_activity_insights($pdo, (int) $user['id'], (int) $user['level']);
$premiumInsights = user_premium_insights($pdo, (int) $user['id'], 6);
$premiumWeeklyPlan = premium_weekly_plan($pdo, $user, 3);
if ($isPremium) {
    $premiumMonthlyStmt = $pdo->prepare('
        SELECT
            COUNT(*) AS routes_30d,
            COALESCE(SUM(r.distance_km), 0) AS km_30d,
            COALESCE(SUM(rc.points_obtained), 0) AS points_30d
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
          AND rc.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ');
    $premiumMonthlyStmt->execute([':user_id' => (int) $user['id']]);
    $premiumMonthly = $premiumMonthlyStmt->fetch() ?: $premiumMonthly;
}

render_header('Mi panel', [
    'description' => 'Panel privado del usuario.',
    'canonical' => 'dashboard.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card">
    <h1 class="section-title">Mi Panel</h1>
    <p class="meta">Resumen en tiempo real de tu actividad y progreso en gamificacion.</p>
    <div class="stack" style="margin-bottom: 12px;">
        <a class="button button-small" href="<?= e(url('export_history.php')) ?>">Descargar mi historial (CSV)</a>
        <?php if ($isPremium): ?>
            <a class="button secondary button-small" href="<?= e(url('export_history.php?format=json')) ?>">Exportar analitica premium (JSON)</a>
        <?php endif; ?>
    </div>
    <div class="stats">
        <div class="stat">
            <small>Puntos totales</small>
            <strong><?= (int) $user['total_points'] ?></strong>
        </div>
        <div class="stat">
            <small>Nivel actual</small>
            <strong><?= (int) $user['level'] ?></strong>
        </div>
        <div class="stat">
            <small>Rutas completadas</small>
            <strong><?= (int) $stats['total_routes'] ?></strong>
        </div>
        <div class="stat">
            <small>Logros desbloqueados</small>
            <strong><?= (int) $stats['total_achievements'] ?></strong>
        </div>
        <div class="stat">
            <small>Rutas favoritas</small>
            <strong><?= (int) $stats['total_favorites'] ?></strong>
        </div>
        <div class="stat">
            <small>Posicion ranking</small>
            <strong>#<?= (int) $stats['ranking_position'] ?></strong>
        </div>
        <div class="stat">
            <small>Suscripcion</small>
            <strong><?= $isPremium ? 'Premium' : 'Gratuita' ?></strong>
        </div>
    </div>
</section>

<?php if (empty($user['email_verified_at'])): ?>
    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title">Verifica tu email</h2>
        <p class="meta">Tu cuenta sigue pendiente de verificación. Es importante para poder recuperar acceso y mejorar seguridad.</p>
        <a class="button button-small" href="<?= e(url('resend_verification.php')) ?>">Reenviar correo de verificación</a>
    </section>
<?php endif; ?>

<?php if (!$isPremium): ?>
    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title">Activa Premium</h2>
        <p class="meta">Sube al plan premium por <?= number_format($premiumPrice, 2) ?> EUR/mes y consigue herramientas que si cambian la experiencia: roadbook premium, plan semanal y bonus real de progreso.</p>
        <a class="button button-small" href="<?= e(url('premium.php')) ?>">Quiero premium</a>
    </section>
    <section class="card card-soft" style="margin-top: 14px;">
        <h2 class="section-title">Lo que desbloqueas con premium</h2>
        <div class="stats">
            <div class="stat"><small>Bonus por ruta</small><strong>+<?= premium_points_bonus_percent() ?>%</strong></div>
            <div class="stat"><small>Bonus en retos</small><strong>+<?= premium_challenge_bonus_percent() ?>%</strong></div>
            <div class="stat"><small>Roadbook offline</small><strong>HTML / PDF</strong></div>
            <div class="stat"><small>Plan semanal</small><strong>Base + reto</strong></div>
        </div>
        <p class="meta" style="margin-top: 12px;">Premium no solo suma puntos: tambien te abre un panel que te ayuda a planificar, bajar rutas mejor preparadas y avanzar mas rapido en retos y ranking.</p>
    </section>
    <section class="card premium-surface" style="margin-top: 14px;">
        <span class="card-kicker premium-kicker">Vista previa</span>
        <h2 class="section-title">Plan semanal premium</h2>
        <p class="meta">Los usuarios premium reciben un plan con rutas recomendadas para combinar una salida base, una de progresion y otra de reto.</p>
        <div class="stats">
            <div class="stat"><small>Estructura</small><strong>3 rutas guiadas</strong></div>
            <div class="stat"><small>Uso real</small><strong>Planificar mejor</strong></div>
            <div class="stat"><small>Descargas pro</small><strong>KML + GeoJSON</strong></div>
            <div class="stat"><small>Roadbook</small><strong>Incluido</strong></div>
        </div>
        <a class="button" style="margin-top: 12px;" href="<?= e(url('premium.php')) ?>">Ver premium completo</a>
    </section>
<?php else: ?>
    <section class="card" style="margin-top: 14px;">
        <h2 class="section-title">Panel premium (ultimos 30 dias)</h2>
        <div class="stats">
            <div class="stat"><small>Rutas (30d)</small><strong><?= (int) $premiumMonthly['routes_30d'] ?></strong></div>
            <div class="stat"><small>Km (30d)</small><strong><?= number_format((float) $premiumMonthly['km_30d'], 1) ?> km</strong></div>
            <div class="stat"><small>Puntos (30d)</small><strong><?= (int) $premiumMonthly['points_30d'] ?></strong></div>
            <div class="stat"><small>Plan</small><strong><?= number_format($premiumPrice, 2) ?> EUR/mes</strong></div>
        </div>
    </section>
    <section class="card" style="margin-top: 14px;">
        <div class="section-heading">
            <div>
                <h2 class="section-title" style="margin-bottom: 4px;">Analitica premium</h2>
                <p class="meta" style="margin-top: 0;">Lectura avanzada de tu actividad acumulada en los ultimos <?= e((string) $premiumInsights['window_label']) ?>.</p>
            </div>
            <a class="button secondary button-small" href="<?= e(url('export_history.php?format=json')) ?>">Exportar JSON premium</a>
        </div>
        <div class="stats">
            <div class="stat"><small>Km acumulados</small><strong><?= number_format((float) $premiumInsights['km_window'], 1) ?> km</strong></div>
            <div class="stat"><small>Desnivel acumulado</small><strong><?= number_format((float) $premiumInsights['elevation_window'], 0) ?> m</strong></div>
            <div class="stat"><small>Duracion media</small><strong><?= format_minutes_human((int) $premiumInsights['avg_duration']) ?></strong></div>
            <div class="stat"><small>Puntos medios</small><strong><?= (int) $premiumInsights['avg_points'] ?></strong></div>
            <div class="stat"><small>Zona principal</small><strong><?= $premiumInsights['top_zone'] !== '' ? e((string) $premiumInsights['top_zone']) : 'Sin datos' ?></strong></div>
            <div class="stat"><small>Actividad principal</small><strong><?= $premiumInsights['top_activity'] !== '' ? e((string) $premiumInsights['top_activity']) : 'Sin datos' ?></strong></div>
        </div>
        <div class="grid grid-2" style="margin-top: 14px;">
            <div class="card card-soft">
                <h3 style="margin-bottom: 8px;">Ruta mas exigente del periodo</h3>
                <p class="meta" style="margin-top: 0;"><?= $premiumInsights['toughest_route'] !== '' ? e((string) $premiumInsights['toughest_route']) : 'Aun no hay suficiente historial reciente.' ?></p>
            </div>
            <div class="card card-soft">
                <h3 style="margin-bottom: 8px;">Cobertura de zonas</h3>
                <p class="meta" style="margin-top: 0;"><?= (int) $premiumInsights['zones_window'] ?> zonas distintas recorridas en los ultimos <?= e((string) $premiumInsights['window_label']) ?>.</p>
            </div>
        </div>
        <div class="premium-trend-grid" style="margin-top: 14px;">
            <?php foreach ($premiumInsights['series'] as $month): ?>
                <?php
                $routesPercent = (int) round(((int) $month['routes'] / max(1, (int) $premiumInsights['series_max_routes'])) * 100);
                $kmPercent = (int) round(((float) $month['km'] / max(0.1, (float) $premiumInsights['series_max_km'])) * 100);
                ?>
                <div class="premium-bar">
                    <div class="premium-bar-track">
                        <div class="premium-bar-fill" style="height: <?= max(8, $routesPercent) ?>%;"></div>
                    </div>
                    <strong><?= e((string) $month['month_label']) ?></strong>
                    <small><?= (int) $month['routes'] ?> rutas</small>
                    <small><?= number_format((float) $month['km'], 1) ?> km</small>
                    <small><?= (int) $kmPercent ?>% del mejor mes</small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card premium-surface" style="margin-top: 14px;">
        <div class="section-heading">
            <div>
                <span class="card-kicker premium-kicker">Plan semanal</span>
                <h2 class="section-title" style="margin-bottom: 4px;">Planificador premium de la semana</h2>
                <p class="meta" style="margin-top: 0;"><?= e((string) $premiumWeeklyPlan['summary']) ?></p>
            </div>
            <div class="stats compact-stats" style="min-width: min(100%, 340px);">
                <div class="stat"><small>Total km</small><strong><?= number_format((float) $premiumWeeklyPlan['total_distance_km'], 1) ?></strong></div>
                <div class="stat"><small>Total desnivel</small><strong><?= number_format((float) $premiumWeeklyPlan['total_elevation_m'], 0) ?> m</strong></div>
            </div>
        </div>
        <?php if (empty($premiumWeeklyPlan['cards'])): ?>
            <p class="muted">Aun no hay suficientes rutas sugeridas para crear tu semana premium.</p>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($premiumWeeklyPlan['cards'] as $planRoute): ?>
                    <article class="card card-soft">
                        <div class="card-kicker premium-kicker"><?= e((string) $planRoute['slot_label']) ?></div>
                        <h3 style="margin-bottom: 6px;"><?= e((string) $planRoute['name']) ?></h3>
                        <p class="meta" style="margin-top: 0;"><?= e((string) $planRoute['slot_summary']) ?></p>
                        <p class="meta"><?= e((string) $planRoute['zone']) ?> - <?= e((string) $planRoute['activity_type']) ?></p>
                        <div class="stats compact-stats">
                            <div class="stat"><small>Distancia</small><strong><?= number_format((float) $planRoute['distance_km'], 1) ?> km</strong></div>
                            <div class="stat"><small>Desnivel</small><strong><?= number_format((float) $planRoute['elevation_m'], 0) ?> m</strong></div>
                        </div>
                        <div class="stack" style="margin-top: 12px;">
                            <a class="button button-small" href="<?= e(url('route.php?id=' . (int) $planRoute['id'])) ?>">Abrir ruta</a>
                            <a class="button secondary button-small" href="<?= e(url('route.php?id=' . (int) $planRoute['id'] . '&download=roadbook')) ?>">Roadbook</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card" style="margin-top: 14px;">
    <h2 class="section-title">Ritmo y objetivo mensual</h2>
    <div class="stats">
        <div class="stat"><small>Racha actual</small><strong><?= (int) $activityInsights['current_streak'] ?> dias</strong></div>
        <div class="stat"><small>Mejor racha</small><strong><?= (int) $activityInsights['best_streak'] ?> dias</strong></div>
        <div class="stat"><small>Rutas ultimos 7 dias</small><strong><?= (int) $activityInsights['routes_7d'] ?></strong></div>
        <div class="stat"><small>Km ultimos 7 dias</small><strong><?= number_format((float) $activityInsights['km_7d'], 1) ?> km</strong></div>
    </div>
    <div class="grid grid-2" style="margin-top: 14px;">
        <div class="card card-soft">
            <h3 style="margin-bottom: 8px;">Objetivo del mes: rutas</h3>
            <p class="meta" style="margin-top: 0;"><?= (int) $activityInsights['routes_month'] ?> / <?= (int) $activityInsights['goal_routes'] ?> rutas registradas este mes.</p>
            <div class="progress progress-lg"><span style="width: <?= (int) $activityInsights['routes_month_percent'] ?>%;"></span></div>
        </div>
        <div class="card card-soft">
            <h3 style="margin-bottom: 8px;">Objetivo del mes: kilometros</h3>
            <p class="meta" style="margin-top: 0;"><?= number_format((float) $activityInsights['km_month'], 1) ?> / <?= number_format((float) $activityInsights['goal_km'], 1) ?> km este mes.</p>
            <div class="progress progress-lg"><span style="width: <?= (int) $activityInsights['km_month_percent'] ?>%;"></span></div>
        </div>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Ultimas rutas registradas</h2>
        <?php if (empty($recentCompletions)): ?>
            <p class="muted">Aun no has completado rutas. Empieza desde la busqueda.</p>
            <a class="button button-small" href="<?= e(url('search.php')) ?>">Ver rutas</a>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ruta</th>
                            <th>Fecha</th>
                            <th>Tiempo</th>
                            <th>Puntos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCompletions as $item): ?>
                            <tr>
                                <td><?= e((string) $item['route_name']) ?> <span class="muted">(<?= number_format((float) $item['distance_km'], 1) ?> km)</span></td>
                                <td><?= e((string) $item['completed_at']) ?></td>
                                <td><?= (int) $item['duration_min'] ?> min</td>
                                <td><?= (int) $item['points_obtained'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <h2 class="section-title">Mis favoritas recientes</h2>
        <?php if (empty($favoriteRoutes)): ?>
            <p class="muted">No tienes rutas favoritas todavia.</p>
            <a class="button button-small" href="<?= e(url('search.php')) ?>">Explorar rutas</a>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ruta</th>
                            <th>Zona</th>
                            <th>Dificultad</th>
                            <th>Distancia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($favoriteRoutes as $fav): ?>
                            <tr>
                                <td><a href="<?= e(url('route.php?id=' . (int) $fav['id'])) ?>"><?= e((string) $fav['name']) ?></a></td>
                                <td><?= e((string) $fav['zone']) ?></td>
                                <td><?= e((string) $fav['difficulty']) ?></td>
                                <td><?= number_format((float) $fav['distance_km'], 1) ?> km</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Mis retos</h2>
        <?php if (empty($joinedChallenges)): ?>
            <p class="muted">Todavia no participas en retos.</p>
            <a class="button button-small" href="<?= e(url('challenges.php')) ?>">Unirme a retos</a>
        <?php endif; ?>
        <?php foreach ($joinedChallenges as $challenge): ?>
            <?php
            $target = max(1.0, (float) $challenge['target_value']);
            $progress = (float) $challenge['progress_value'];
            $percent = min(100, (int) round(($progress / $target) * 100));
            $completed = !empty($challenge['completed_at']);
            ?>
            <div style="margin-bottom: 12px;">
                <strong><?= e((string) $challenge['title']) ?></strong>
                <p class="meta" style="margin: 4px 0 8px;">
                    <?= e((string) $challenge['target_type']) ?> - objetivo <?= number_format($target, 1) ?> - recompensa <?= (int) $challenge['reward_points'] ?> pts
                </p>
                <div class="progress"><span style="width: <?= $percent ?>%;"></span></div>
                <p class="meta" style="margin: 6px 0 0;">
                    <?= number_format($progress, 1) ?> / <?= number_format($target, 1) ?> -
                    <?= $completed ? 'Completado' : 'En progreso' ?> -
                    Fin: <?= e((string) $challenge['end_date']) ?>
                </p>
            </div>
        <?php endforeach; ?>
    </article>

    <article class="card">
        <h2 class="section-title">Galeria de logros</h2>
        <div class="grid grid-2">
            <?php foreach ($allAchievements as $achievement): ?>
                <?php
                $unlocked = !empty($achievement['awarded_at']);
                $progressPoints = (int) $achievement['criteria_points'] > 0
                    ? min(100, (int) round(($totalPoints / (int) $achievement['criteria_points']) * 100))
                    : 100;
                $progressRoutes = (int) $achievement['criteria_routes'] > 0
                    ? min(100, (int) round(($totalRoutes / (int) $achievement['criteria_routes']) * 100))
                    : 100;
                $progress = min($progressPoints, $progressRoutes);
                if ($unlocked) {
                    $progress = 100;
                }
                ?>
                <div class="card" style="padding: 14px;">
                    <p style="margin: 0 0 6px; font-size: 1.2rem;"><strong><?= e((string) $achievement['title']) ?></strong></p>
                    <p class="meta" style="margin: 0 0 8px;"><?= e((string) $achievement['description']) ?></p>
                    <div class="progress"><span style="width: <?= $progress ?>%;"></span></div>
                    <p class="meta" style="margin: 6px 0 0;">
                        <?= $unlocked ? 'Desbloqueado el ' . e((string) $achievement['awarded_at']) : 'Progreso ' . $progress . '%' ?>
                        - Bonus <?= (int) $achievement['bonus_points'] ?> pts
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>
<?php render_footer(); ?>
