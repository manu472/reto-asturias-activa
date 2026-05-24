<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$routeId = (int) ($_GET['id'] ?? 0);
if ($routeId <= 0) {
    set_flash('warning', 'Ruta no encontrada.');
    redirect('map.php');
}

$user = current_user();
$isPremium = $user !== null && user_has_active_premium($user);
$isAdmin = $user !== null && (int) $user['is_admin'] === 1;
$premiumCtaPath = $user ? 'premium.php' : 'login.php';
$params = [':id' => $routeId];
$favoriteJoin = '';
$favoriteSelect = '0 AS is_favorite,';
if ($user) {
    $favoriteJoin = 'LEFT JOIN route_favorites rf ON rf.route_id = r.id AND rf.user_id = :favorite_user_id';
    $favoriteSelect = 'CASE WHEN rf.user_id IS NULL THEN 0 ELSE 1 END AS is_favorite,';
    $params[':favorite_user_id'] = (int) $user['id'];
}

$visibilityWhere = 'r.id = :id AND r.submission_status = "approved"';
if ($isAdmin) {
    $visibilityWhere = 'r.id = :id';
} elseif ($user) {
    $visibilityWhere = 'r.id = :id AND (r.submission_status = "approved" OR r.created_by = :viewer_id)';
    $params[':viewer_id'] = (int) $user['id'];
}

$routeStmt = $pdo->prepare('
    SELECT
        r.*,
        ' . $favoriteSelect . '
        COALESCE(AVG(CASE WHEN c.status = "approved" THEN c.rating END), 0) AS avg_rating,
        SUM(CASE WHEN c.status = "approved" THEN 1 ELSE 0 END) AS total_comments,
        COUNT(DISTINCT rc.id) AS total_completions
    FROM routes r
    LEFT JOIN comments c ON c.route_id = r.id
    LEFT JOIN route_completions rc ON rc.route_id = r.id
    ' . $favoriteJoin . '
    WHERE ' . $visibilityWhere . '
    GROUP BY r.id
    LIMIT 1
');
foreach ($params as $key => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $routeStmt->bindValue($key, $value, $type);
}
$routeStmt->execute();
$route = $routeStmt->fetch();

if (!$route) {
    set_flash('warning', 'La ruta solicitada no existe.');
    redirect('map.php');
}

$mapPayload = route_map_payload($pdo, $route);
$coordinates = $mapPayload['points'];
$mapSourceKey = (string) $mapPayload['source'];
$mapSource = route_map_source_label($mapSourceKey);
$elevationProfile = route_elevation_profile($route, $coordinates);
$elevationProfileLabel = route_profile_source_label((string) $elevationProfile['source']);
$routeDetails = route_details_bundle($route, $coordinates, $mapSourceKey);
$isSurfRoute = route_is_surf($route);
$surfDetails = is_array($routeDetails['surf'] ?? null) ? $routeDetails['surf'] : null;
$surfSpotPoint = null;
if ($isSurfRoute && !empty($coordinates)) {
    $surfSpotPoint = $coordinates[(int) floor((count($coordinates) - 1) / 2)] ?? $coordinates[0];
}
$surfSpotLink = route_google_maps_link(is_array($surfSpotPoint) ? $surfSpotPoint : null);
$downloadFormats = route_download_formats();
$premiumCoach = $isPremium ? premium_route_coach($pdo, (int) $user['id'], $route) : [];

if (isset($_GET['download']) && is_string($_GET['download'])) {
    $downloadType = mb_strtolower(trim((string) $_GET['download']));
    if (!array_key_exists($downloadType, $downloadFormats)) {
        set_flash('warning', 'Formato de descarga no disponible para esta ruta.');
        redirect('route.php?id=' . $routeId);
    }
    $downloadFormat = $downloadFormats[$downloadType];
    if ((bool) ($downloadFormat['premium_only'] ?? false) && !$isPremium) {
        set_flash('warning', $user ? 'Esta descarga forma parte del pack premium. Activala para desbloquear formatos avanzados y el roadbook.' : 'Inicia sesion y activa premium para desbloquear esta descarga.');
        redirect($premiumCtaPath);
    }
    if (count($coordinates) < 2) {
        set_flash('warning', 'Esta ruta todavia no tiene un trazado suficiente para generar la descarga.');
        redirect('route.php?id=' . $routeId);
    }

    $downloadContent = '';
    if ($downloadType === 'gpx') {
        $downloadContent = route_points_to_gpx_xml($route, $coordinates, absolute_url('route.php?id=' . $routeId));
    } elseif ($downloadType === 'kml') {
        $downloadContent = route_points_to_kml_xml($route, $coordinates, absolute_url('route.php?id=' . $routeId));
    } elseif ($downloadType === 'geojson') {
        $downloadContent = route_points_to_geojson($route, $coordinates, absolute_url('route.php?id=' . $routeId));
    } elseif ($downloadType === 'roadbook') {
        $downloadContent = route_points_to_roadbook_html(
            $route,
            $coordinates,
            $routeDetails,
            $elevationProfile,
            absolute_url('route.php?id=' . $routeId),
            $premiumCoach
        );
    }

    header('Content-Type: ' . $downloadFormats[$downloadType]['mime']);
    header('Content-Disposition: attachment; filename="' . route_download_filename((string) $route['name'], (string) ($downloadFormat['extension'] ?? $downloadType)) . '"');
    echo $downloadContent;
    exit;
}

$reportReasons = [
    'senalizacion' => 'Senalizacion deficiente',
    'seguridad' => 'Riesgo de seguridad',
    'acceso' => 'Problemas de acceso',
    'estado_camino' => 'Mal estado del camino',
    'datos_erroneos' => 'Datos incorrectos en la ficha',
    'otro' => 'Otro',
];

if (is_post()) {
    if (!$user) {
        set_flash('warning', 'Inicia sesion para dejar comentarios.');
        redirect('login.php');
    }
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad invalido.');
        redirect('route.php?id=' . $routeId);
    }

    $action = (string) ($_POST['action'] ?? 'add_comment');
    if ($action === 'report_route') {
        if ((string) $route['submission_status'] !== 'approved') {
            set_flash('warning', 'Solo se pueden reportar incidencias en rutas publicadas.');
            redirect('route.php?id=' . $routeId);
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        if (!array_key_exists($reason, $reportReasons)) {
            set_flash('danger', 'Selecciona un motivo de incidencia valido.');
            redirect('route.php?id=' . $routeId);
        }
        if (mb_strlen($details) < 15 || mb_strlen($details) > 2000) {
            set_flash('danger', 'Describe la incidencia con un texto entre 15 y 2000 caracteres.');
            redirect('route.php?id=' . $routeId);
        }

        $existingReportStmt = $pdo->prepare('
            SELECT id
            FROM route_reports
            WHERE route_id = :route_id
              AND user_id = :user_id
              AND status = "pending"
            LIMIT 1
        ');
        $existingReportStmt->execute([
            ':route_id' => $routeId,
            ':user_id' => (int) $user['id'],
        ]);
        if ($existingReportStmt->fetchColumn()) {
            set_flash('warning', 'Ya tienes una incidencia pendiente para esta ruta.');
            redirect('route.php?id=' . $routeId);
        }

        $insertReport = $pdo->prepare('
            INSERT INTO route_reports (route_id, user_id, reason, details, status, created_at)
            VALUES (:route_id, :user_id, :reason, :details, "pending", NOW())
        ');
        $insertReport->execute([
            ':route_id' => $routeId,
            ':user_id' => (int) $user['id'],
            ':reason' => $reason,
            ':details' => $details,
        ]);

        notify_admins(
            $pdo,
            'route_report',
            'Nueva incidencia de ruta',
            'Se ha reportado una incidencia en la ruta "' . (string) $route['name'] . '".',
            'admin.php?tab=reports'
        );

        set_flash('success', 'Incidencia enviada. El equipo administrador la revisara.');
        redirect('route.php?id=' . $routeId);
    }

    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment_text'] ?? ''));
    if ($rating < 1 || $rating > 5 || mb_strlen($comment) < 10) {
        set_flash('danger', 'La valoracion debe estar entre 1 y 5 y el comentario tener al menos 10 caracteres.');
        redirect('route.php?id=' . $routeId);
    }

    $insertComment = $pdo->prepare('
        INSERT INTO comments (route_id, user_id, rating, comment_text, status, created_at)
        VALUES (:route_id, :user_id, :rating, :comment_text, "pending", NOW())
    ');
    $insertComment->execute([
        ':route_id' => $routeId,
        ':user_id' => (int) $user['id'],
        ':rating' => $rating,
        ':comment_text' => $comment,
    ]);

    set_flash('success', 'Comentario enviado. Queda pendiente de moderacion.');
    redirect('route.php?id=' . $routeId);
}

$commentsStmt = $pdo->prepare('
    SELECT c.*, u.name
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.route_id = :id AND c.status = "approved"
    ORDER BY c.created_at DESC
');
$commentsStmt->execute([':id' => $routeId]);
$comments = $commentsStmt->fetchAll();

$similarRoutes = similar_routes($pdo, $route, 3);
$shareUrl = absolute_url('route.php?id=' . $routeId);
$routeMetaDescription = meta_text(
    (string) $route['name'] . ' en ' . (string) $route['zone'] . '. ' .
    (string) $route['description'] . ' Distancia: ' . number_format((float) $route['distance_km'], 1) .
    ' km. Dificultad: ' . (string) $route['difficulty'] . '.'
);

$returnPath = 'route.php?id=' . $routeId;

render_header((string) $route['name'], [
    'description' => $routeMetaDescription,
    'canonical' => 'route.php?id=' . $routeId,
    'image' => route_image_src((string) ($route['cover_image'] ?? ''), 1600),
    'type' => 'article',
    'json_ld' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => (string) $route['name'],
        'description' => $routeMetaDescription,
        'url' => $shareUrl,
        'image' => meta_image_url(route_image_src((string) ($route['cover_image'] ?? ''), 1600)),
    ],
]);
?>
<section class="grid grid-2">
    <article class="card route-hero-card">
        <?php if (!empty($route['cover_image'])): ?>
            <img
                class="route-cover"
                src="<?= e(route_image_src((string) $route['cover_image'], 1800)) ?>"
                srcset="<?= e(route_image_srcset((string) $route['cover_image'], [960, 1280, 1600, 2200])) ?>"
                sizes="(max-width: 760px) calc(100vw - 56px), 560px"
                alt="Imagen de ruta"
                fetchpriority="high"
                decoding="async"
            >
        <?php endif; ?>
        <h1 class="section-title"><?= e((string) $route['name']) ?></h1>
        <?php if ((string) $route['submission_status'] !== 'approved'): ?>
            <p class="meta"><strong>Estado:</strong> <?= e((string) $route['submission_status']) ?><?= !empty($route['review_note']) ? ' - ' . e((string) $route['review_note']) : '' ?></p>
        <?php endif; ?>
        <p class="meta"><?= e((string) $route['zone']) ?> - <?= e((string) $route['activity_type']) ?></p>
        <p><?= nl2br(e((string) $route['description'])) ?></p>
        <p>
            <span class="pill"><?= e((string) $route['difficulty']) ?></span>
            <span class="pill orange"><?= number_format((float) $route['distance_km'], 1) ?> km</span>
        </p>
        <?php if ($isSurfRoute && is_array($surfDetails)): ?>
            <div class="alert alert-warning surf-safety-alert">
                <strong>Aviso de seguridad:</strong> <?= e((string) $surfDetails['safety']) ?>
            </div>
        <?php endif; ?>
        <div class="stats">
            <?php if ($isSurfRoute && is_array($surfDetails)): ?>
                <div class="stat"><small>Marea clave</small><strong><?= e((string) $surfDetails['tide']) ?></strong></div>
                <div class="stat"><small>Viento favorable</small><strong><?= e((string) $surfDetails['wind']) ?></strong></div>
                <div class="stat"><small>Mar recomendado</small><strong><?= e((string) $surfDetails['swell']) ?></strong></div>
                <div class="stat"><small>Nivel</small><strong><?= e((string) $surfDetails['level']) ?></strong></div>
            <?php else: ?>
                <div class="stat"><small>Desnivel</small><strong><?= (int) $route['elevation_m'] ?> m</strong></div>
                <div class="stat"><small>Puntos base</small><strong><?= (int) $route['base_points'] ?></strong></div>
                <div class="stat"><small>Completada</small><strong><?= (int) $route['total_completions'] ?> veces</strong></div>
                <div class="stat"><small>Valoracion media</small><strong><?= number_format((float) $route['avg_rating'], 1) ?>/5</strong></div>
            <?php endif; ?>
        </div>
        <div class="stack" style="margin-top: 12px;">
            <?php if ($user): ?>
                <a class="button" href="<?= e(url('complete_route.php?route_id=' . (int) $route['id'])) ?>"><?= $isSurfRoute ? 'Registrar sesion' : 'Marcar ruta completada' ?></a>
                <form method="post" action="<?= e(url('toggle_favorite.php')) ?>" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="route_id" value="<?= (int) $route['id'] ?>">
                    <input type="hidden" name="action" value="<?= (int) $route['is_favorite'] === 1 ? 'remove' : 'add' ?>">
                    <input type="hidden" name="return_to" value="<?= e($returnPath) ?>">
                    <button type="submit" class="button secondary">
                        <?= (int) $route['is_favorite'] === 1 ? 'Quitar de favoritas' : 'Guardar en favoritas' ?>
                    </button>
                </form>
            <?php else: ?>
                <a class="button" href="<?= e(url('login.php')) ?>"><?= $isSurfRoute ? 'Inicia sesion para registrar sesion' : 'Inicia sesion para completar' ?></a>
            <?php endif; ?>
            <button type="button" class="button secondary js-copy-route-link" data-copy-url="<?= e($shareUrl) ?>">Compartir ruta</button>
            <a class="button secondary" href="<?= e(url($premiumCtaPath)) ?>"><?= $isPremium ? 'Ventajas premium' : 'Desbloquear premium' ?></a>
            <a class="button secondary" href="<?= e(url('route.php?id=' . (int) $route['id'] . '&download=gpx')) ?>">Descargar GPX</a>
            <a class="button secondary" href="<?= e(url('search.php')) ?>">Volver a busqueda</a>
        </div>
    </article>

    <article class="card">
        <h2 class="section-title"><?= $isSurfRoute ? 'Mapa del spot' : 'Trazado en mapa' ?></h2>
        <div id="route-map" class="route-map <?= $isSurfRoute ? 'is-surf-map' : '' ?>" data-activity="<?= e((string) $route['activity_type']) ?>" data-route='<?= e((string) json_encode($coordinates, JSON_UNESCAPED_UNICODE)) ?>'></div>
        <div class="stack route-map-actions" style="margin-top: 12px;">
            <?php foreach ($downloadFormats as $formatKey => $format): ?>
                <?php $isLockedFormat = (bool) ($format['premium_only'] ?? false) && !$isPremium; ?>
                <a class="button secondary <?= $isLockedFormat ? 'button-locked' : '' ?>" href="<?= e(url($isLockedFormat ? $premiumCtaPath : 'route.php?id=' . (int) $route['id'] . '&download=' . $formatKey)) ?>">
                    <?= $isLockedFormat ? 'Premium: ' : 'Descargar ' ?><?= e((string) $format['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="meta" style="margin-top: 10px;">
            <?= $isSurfRoute ? 'Mapa interactivo con zoom y desplazamiento para ubicar la linea de playa y sus accesos.' : 'Mapa interactivo con zoom y desplazamiento para revisar el recorrido.' ?>
            Fuente del trazado: <strong><?= e($mapSource) ?></strong>.
            <?= count($coordinates) >= 2 ? 'Puntos cargados: ' . count($coordinates) . '.' : 'Aun faltan puntos de trazado.' ?>
        </p>
        <p class="meta" style="margin-top: 6px;"><?= $isSurfRoute ? 'GPX guarda una referencia del frente de playa. El pack offline premium mantiene KML, GeoJSON y roadbook.' : 'GPX sigue abierto para todos. El pack offline premium desbloquea KML, GeoJSON y un roadbook imprimible listo para guardar como PDF.' ?></p>
        <?php if ($isSurfRoute && is_array($surfDetails)): ?>
            <div class="surf-map-note">
                <strong>Lectura rapida:</strong>
                <?= e((string) $surfDetails['wave']) ?>. Fondo: <?= e((string) $surfDetails['bottom']) ?>.
            </div>
        <?php else: ?>
            <div class="elevation-panel">
                <div class="elevation-panel-head">
                    <div>
                        <h3 class="section-title" style="margin-bottom: 4px;">Perfil de altitud</h3>
                        <p class="meta" style="margin-top: 0;">
                            <?= e($elevationProfileLabel) ?>.
                            <?php if ($elevationProfile['min'] !== null && $elevationProfile['max'] !== null): ?>
                                Cota min: <?= number_format((float) $elevationProfile['min'], 0) ?> m.
                                Cota max: <?= number_format((float) $elevationProfile['max'], 0) ?> m.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="elevation-panel-stats">
                        <span class="pill orange"><?= number_format((float) ($elevationProfile['distance_km'] ?? (float) $route['distance_km']), 1) ?> km</span>
                        <span class="pill"><?= (int) $route['elevation_m'] ?> m+</span>
                    </div>
                </div>
                <div
                    id="route-elevation-profile"
                    class="elevation-profile"
                    data-profile='<?= e((string) json_encode($elevationProfile['points'] ?? [], JSON_UNESCAPED_UNICODE)) ?>'
                    data-profile-source="<?= e((string) $elevationProfile['source']) ?>"
                ></div>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php if ($isSurfRoute && is_array($surfDetails)): ?>
    <section class="card surf-details-card" style="margin-top: 14px;">
        <span class="card-kicker">Datos de surf</span>
        <h2 class="section-title">Lectura del spot</h2>
        <div class="stats surf-stats">
            <div class="stat"><small>Tipo de ola</small><strong><?= e((string) $surfDetails['wave']) ?></strong></div>
            <div class="stat"><small>Fondo</small><strong><?= e((string) $surfDetails['bottom']) ?></strong></div>
            <div class="stat"><small>Marea</small><strong><?= e((string) $surfDetails['tide']) ?></strong></div>
            <div class="stat"><small>Viento</small><strong><?= e((string) $surfDetails['wind']) ?></strong></div>
            <div class="stat"><small>Swell</small><strong><?= e((string) $surfDetails['swell']) ?></strong></div>
            <div class="stat"><small>Temporada</small><strong><?= e((string) $surfDetails['season']) ?></strong></div>
        </div>
        <div class="alert alert-warning surf-safety-alert" style="margin-top: 14px;">
            <strong>Antes de entrar:</strong> <?= e((string) $surfDetails['safety']) ?>
        </div>
    </section>
<?php endif; ?>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Ficha ampliada</h2>
        <p class="meta"><?= $isSurfRoute ? 'Datos orientativos adaptados al spot, sus condiciones habituales y la referencia de playa cargada actualmente.' : 'Datos orientativos calculados a partir de la ficha, del desnivel y del trazado cargado actualmente.' ?></p>
        <div class="stats">
            <div class="stat"><small>Tipo de recorrido</small><strong><?= e((string) $routeDetails['shape']) ?></strong></div>
            <div class="stat"><small>Duracion orientativa</small><strong><?= e((string) $routeDetails['duration']['label']) ?></strong></div>
            <div class="stat"><small>Orientacion</small><strong><?= e((string) $routeDetails['orientation']) ?></strong></div>
            <div class="stat"><small>Mejor epoca</small><strong><?= e((string) $routeDetails['best_season']) ?></strong></div>
            <div class="stat"><small>Terreno</small><strong><?= e((string) $routeDetails['terrain']) ?></strong></div>
            <div class="stat"><small>Perfil recomendado</small><strong><?= e((string) $routeDetails['audience']) ?></strong></div>
        </div>

        <?php if ($isSurfRoute): ?>
            <div class="route-details-grid" style="margin-top: 14px;">
                <div class="card card-soft">
                    <h3 style="margin-bottom: 8px;">Punto de la playa</h3>
                    <?php if (is_array($surfSpotPoint)): ?>
                        <p class="meta" style="margin-top: 0;">
                            <?= number_format((float) $surfSpotPoint['lat'], 5) ?>,
                            <?= number_format((float) $surfSpotPoint['lng'], 5) ?>
                        </p>
                        <?php if ($surfSpotLink !== ''): ?>
                            <a class="button secondary button-small" href="<?= e($surfSpotLink) ?>" target="_blank" rel="noopener noreferrer">Abrir playa en Google Maps</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted">Aun no hay coordenadas fiables del spot.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-2 route-details-grid" style="margin-top: 14px;">
                <div class="card card-soft">
                    <h3 style="margin-bottom: 8px;">Punto de inicio</h3>
                    <?php if (is_array($routeDetails['start_point'])): ?>
                        <p class="meta" style="margin-top: 0;">
                            <?= number_format((float) $routeDetails['start_point']['lat'], 5) ?>,
                            <?= number_format((float) $routeDetails['start_point']['lng'], 5) ?>
                        </p>
                        <?php if ((string) $routeDetails['start_link'] !== ''): ?>
                            <a class="button secondary button-small" href="<?= e((string) $routeDetails['start_link']) ?>" target="_blank" rel="noopener noreferrer">Abrir inicio en Google Maps</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted">Aun no hay coordenadas de inicio fiables.</p>
                    <?php endif; ?>
                </div>
                <div class="card card-soft">
                    <h3 style="margin-bottom: 8px;">Punto de final</h3>
                    <?php if (is_array($routeDetails['end_point'])): ?>
                        <p class="meta" style="margin-top: 0;">
                            <?= number_format((float) $routeDetails['end_point']['lat'], 5) ?>,
                            <?= number_format((float) $routeDetails['end_point']['lng'], 5) ?>
                        </p>
                        <?php if ((string) $routeDetails['end_link'] !== ''): ?>
                            <a class="button secondary button-small" href="<?= e((string) $routeDetails['end_link']) ?>" target="_blank" rel="noopener noreferrer">Abrir final en Google Maps</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted">Aun no hay coordenadas finales fiables.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <h2 class="section-title">Descarga y navegacion</h2>
        <p class="meta"><?= $isPremium ? 'Tienes desbloqueado el pack offline premium con formatos avanzados y roadbook imprimible.' : 'GPX sigue abierto para todos, pero los formatos avanzados y el roadbook forman parte del pack premium.' ?></p>
        <?php foreach ($routeDetails['download_formats'] as $formatKey => $format): ?>
            <?php $isLockedFormat = (bool) ($format['premium_only'] ?? false) && !$isPremium; ?>
            <div class="download-format-row">
                <div>
                    <strong><?= e((string) $format['label']) ?><?php if ((bool) ($format['premium_only'] ?? false)): ?> <span class="mini-badge">Premium</span><?php endif; ?></strong>
                    <p class="meta" style="margin: 4px 0 0;"><?= e((string) $format['description']) ?></p>
                </div>
                <a class="button secondary button-small <?= $isLockedFormat ? 'button-locked' : '' ?>" href="<?= e(url($isLockedFormat ? $premiumCtaPath : 'route.php?id=' . (int) $route['id'] . '&download=' . $formatKey)) ?>">
                    <?= $isLockedFormat ? 'Desbloquear' : 'Descargar' ?>
                </a>
            </div>
        <?php endforeach; ?>

        <hr style="margin: 16px 0; border: 0; border-top: 1px solid #dfe7df;">
        <h3 style="margin-bottom: 8px;">Consejos practicos</h3>
        <ul class="route-tips">
            <?php foreach ($routeDetails['tips'] as $tip): ?>
                <li><?= e((string) $tip) ?></li>
            <?php endforeach; ?>
        </ul>
    </article>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card premium-surface">
        <?php if ($isPremium): ?>
            <span class="card-kicker premium-kicker">Asistente premium</span>
            <h2 class="section-title">Lectura personal de esta ruta</h2>
            <p class="meta">Esta estimacion usa tu historial real para decirte si la ruta te encaja y cuanto tiempo te puede llevar de verdad.</p>
            <div class="stats">
                <div class="stat"><small>Tiempo estimado para ti</small><strong><?= e((string) $premiumCoach['expected_duration_label']) ?></strong></div>
                <div class="stat"><small>Encaje</small><strong><?= e((string) $premiumCoach['fit_label']) ?></strong></div>
                <div class="stat"><small>Esfuerzo relativo</small><strong><?= ((int) $premiumCoach['ratio_percent'] >= 0 ? '+' : '') . (int) $premiumCoach['ratio_percent'] ?>%</strong></div>
                <div class="stat"><small>Tu media actual</small><strong><?= e((string) $premiumCoach['baseline_label']) ?></strong></div>
            </div>
            <div class="alert alert-<?= e((string) $premiumCoach['fit_tone']) ?>" style="margin-top: 12px;">
                <?= e((string) $premiumCoach['fit_summary']) ?>
            </div>
            <ul class="route-tips" style="margin-top: 12px;">
                <?php foreach ((array) ($premiumCoach['tips'] ?? []) as $tip): ?>
                    <li><?= e((string) $tip) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <span class="card-kicker premium-kicker">Solo premium</span>
            <h2 class="section-title">Prepara esta ruta con mas contexto</h2>
            <p class="meta">Premium te ayuda a saber si esta salida encaja contigo, cuanto podria llevarte segun tu historial y que archivos puedes guardar para consultarlos sin conexion.</p>
            <div class="stats">
                <div class="stat"><small>Tiempo personal</small><strong>Segun tu ritmo</strong></div>
                <div class="stat"><small>Encaje real</small><strong>Ideal / reto / exigente</strong></div>
                <div class="stat"><small>Roadbook</small><strong>HTML + PDF</strong></div>
                <div class="stat"><small>Formatos pro</small><strong>KML + GeoJSON</strong></div>
            </div>
            <p class="meta" style="margin-top: 12px;">Tambien puedes proponer rutas nuevas para que el equipo las revise y las publique en la comunidad.</p>
            <a class="button" href="<?= e(url($premiumCtaPath)) ?>">Ver Premium</a>
        <?php endif; ?>
    </article>

    <article class="card premium-surface">
        <span class="card-kicker premium-kicker">Pack offline</span>
        <h2 class="section-title">Herramientas para salir mejor preparado</h2>
        <ul class="route-tips">
            <li>Descargas profesionales: KML, GeoJSON y roadbook imprimible listo para guardar como PDF.</li>
            <li>Estimacion personal de tiempo y esfuerzo basada en tu propio historial.</li>
            <li>Bonus fuerte de progreso: +<?= premium_points_bonus_percent() ?>% de puntos por ruta y +<?= premium_challenge_bonus_percent() ?>% en retos activos.</li>
            <li>Planificacion semanal premium desde tu panel con rutas base, progresion y reto.</li>
            <li>Propuesta de rutas nuevas incluida para usuarios premium.</li>
        </ul>
        <p class="meta" style="margin-top: 12px;">Premium concentra las funciones pensadas para usuarios que consultan rutas a menudo, guardan informacion offline y quieren aportar al catalogo.</p>
    </article>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Comentarios de la comunidad</h2>
        <?php if (empty($comments)): ?>
            <p class="muted">Todavia no hay comentarios aprobados para esta ruta.</p>
        <?php endif; ?>
        <?php foreach ($comments as $comment): ?>
            <div style="border-bottom: 1px solid #e0e7e0; padding: 10px 0;">
                <p style="margin: 0 0 5px;"><strong><?= e((string) $comment['name']) ?></strong> - <?= str_repeat('&#9733;', (int) $comment['rating']) ?><span class="muted"><?= str_repeat('&#9734;', 5 - (int) $comment['rating']) ?></span></p>
                <p style="margin: 0;"><?= nl2br(e((string) $comment['comment_text'])) ?></p>
                <small class="muted"><?= e((string) $comment['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
    </article>

    <article class="card">
        <h2 class="section-title">Anadir valoracion</h2>
        <?php if (!$user): ?>
            <p>Necesitas iniciar sesion para comentar.</p>
            <a class="button" href="<?= e(url('login.php')) ?>">Iniciar sesion</a>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_comment">
                <div style="margin-bottom: 10px;">
                    <label for="rating">Valoracion</label>
                    <select id="rating" name="rating" required>
                        <option value="">Selecciona</option>
                        <option value="5">5 - Excelente</option>
                        <option value="4">4 - Muy buena</option>
                        <option value="3">3 - Correcta</option>
                        <option value="2">2 - Mejorable</option>
                        <option value="1">1 - Dificil de recomendar</option>
                    </select>
                </div>
                <div style="margin-bottom: 10px;">
                    <label for="comment_text">Comentario</label>
                    <textarea id="comment_text" name="comment_text" minlength="10" required placeholder="Comparte consejos, estado del camino o recomendaciones"></textarea>
                </div>
                <button type="submit">Enviar para moderacion</button>
            </form>

            <?php if ((string) $route['submission_status'] === 'approved'): ?>
                <hr style="margin: 16px 0; border: 0; border-top: 1px solid #dfe7df;">
                <h3 style="margin-top: 0;">Reportar incidencia</h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="report_route">
                    <div style="margin-bottom: 10px;">
                        <label for="reason">Motivo</label>
                        <select id="reason" name="reason" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($reportReasons as $key => $label): ?>
                                <option value="<?= e((string) $key) ?>"><?= e((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label for="details">Detalle de la incidencia</label>
                        <textarea id="details" name="details" minlength="15" maxlength="2000" required placeholder="Describe el problema con detalle para que podamos revisarlo."></textarea>
                    </div>
                    <button type="submit" class="button secondary">Enviar incidencia</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </article>
</section>

<?php if (!empty($similarRoutes)): ?>
    <section style="margin-top: 14px;">
        <div class="section-heading">
            <div>
                <h2 class="section-title" style="margin-bottom: 4px;">Rutas similares</h2>
                <p class="meta" style="margin-top: 0;">Mas opciones parecidas por zona, actividad o dificultad para que sigas explorando Asturias.</p>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($similarRoutes as $similar): ?>
                <?php
                    $similarIsSurf = route_is_surf($similar);
                    $similarSurf = $similarIsSurf ? route_surf_details($similar) : null;
                ?>
                <article class="card recommendation-card <?= $similarIsSurf ? 'surf-route-card' : '' ?>">
                    <?php if (!empty($similar['cover_image'])): ?>
                        <img
                            class="route-cover"
                            src="<?= e(route_image_src((string) $similar['cover_image'], 1200)) ?>"
                            srcset="<?= e(route_image_srcset((string) $similar['cover_image'], [640, 960, 1280, 1600])) ?>"
                            sizes="(max-width: 760px) calc(100vw - 56px), (max-width: 1180px) 31vw, 360px"
                            alt="Imagen de <?= e((string) $similar['name']) ?>"
                            loading="lazy"
                            decoding="async"
                        >
                    <?php endif; ?>
                    <div class="card-kicker"><?= e(route_similarity_reason($similar, $route)) ?></div>
                    <h3><?= e((string) $similar['name']) ?></h3>
                    <p class="meta"><?= e((string) $similar['zone']) ?> - <?= e((string) $similar['activity_type']) ?></p>
                    <p>
                        <span class="pill"><?= e((string) $similar['difficulty']) ?></span>
                        <?php if ($similarIsSurf && is_array($similarSurf)): ?>
                            <span class="pill surf-pill"><?= e((string) $similarSurf['level']) ?></span>
                        <?php else: ?>
                            <span class="pill orange"><?= number_format((float) $similar['distance_km'], 1) ?> km</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($similarIsSurf && is_array($similarSurf)): ?>
                        <div class="surf-card-facts">
                            <span><small>Marea</small><strong><?= e((string) $similarSurf['tide']) ?></strong></span>
                            <span><small>Viento</small><strong><?= e((string) $similarSurf['wind']) ?></strong></span>
                            <span><small>Ola</small><strong><?= e((string) $similarSurf['wave']) ?></strong></span>
                        </div>
                    <?php else: ?>
                        <div class="stats compact-stats">
                            <div class="stat"><small>Valoracion</small><strong><?= number_format((float) $similar['avg_rating'], 1) ?>/5</strong></div>
                            <div class="stat"><small>Completada</small><strong><?= (int) $similar['total_completions'] ?> veces</strong></div>
                        </div>
                    <?php endif; ?>
                    <div class="stack" style="margin-top: 12px;">
                        <a class="button button-small" href="<?= e(url('route.php?id=' . (int) $similar['id'])) ?>"><?= $similarIsSurf ? 'Abrir spot' : 'Abrir ruta' ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
