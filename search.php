<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$user = current_user();

$search = trim((string) ($_GET['q'] ?? ''));
$difficulty = trim((string) ($_GET['difficulty'] ?? ''));
$zone = trim((string) ($_GET['zone'] ?? ''));
$activityType = trim((string) ($_GET['activity_type'] ?? ''));
$minDistance = ($_GET['min_distance'] ?? '') !== '' ? (float) $_GET['min_distance'] : null;
$maxDistance = ($_GET['max_distance'] ?? '') !== '' ? (float) $_GET['max_distance'] : null;
$onlyFavorites = $user !== null && (string) ($_GET['only_favorites'] ?? '') === '1';
$sort = trim((string) ($_GET['sort'] ?? 'newest'));

$allowedSorts = [
    'newest' => 'r.created_at DESC',
    'rating' => 'avg_rating DESC, total_comments DESC, r.created_at DESC',
    'popular' => 'total_completions DESC, r.created_at DESC',
    'distance_asc' => 'r.distance_km ASC, r.created_at DESC',
    'distance_desc' => 'r.distance_km DESC, r.created_at DESC',
    'points_desc' => 'r.base_points DESC, r.created_at DESC',
];
if (!array_key_exists($sort, $allowedSorts)) {
    $sort = 'newest';
}

$zones = session_cache_get('routes.filters.zones.v2', 600);
if (!is_array($zones)) {
    $zones = $pdo->query('SELECT DISTINCT zone FROM routes WHERE submission_status = "approved" ORDER BY zone ASC')->fetchAll(PDO::FETCH_COLUMN);
    session_cache_put('routes.filters.zones.v2', $zones);
}
$activityTypes = session_cache_get('routes.filters.activity_types.v2', 600);
if (!is_array($activityTypes)) {
    $activityTypes = $pdo->query('SELECT DISTINCT activity_type FROM routes WHERE submission_status = "approved" ORDER BY activity_type ASC')->fetchAll(PDO::FETCH_COLUMN);
    session_cache_put('routes.filters.activity_types.v2', $activityTypes);
}
if ($zone !== '' && !in_array($zone, $zones, true)) {
    $zone = '';
}
if ($activityType !== '' && !in_array($activityType, $activityTypes, true)) {
    $activityType = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$where = ['r.submission_status = "approved"'];
$params = [];

if ($search !== '') {
    $where[] = '(r.name LIKE :q OR r.zone LIKE :q OR r.activity_type LIKE :q OR r.description LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($difficulty !== '' && in_array($difficulty, ['Baja', 'Media', 'Alta', 'Muy Alta'], true)) {
    $where[] = 'r.difficulty = :difficulty';
    $params[':difficulty'] = $difficulty;
}
if ($zone !== '') {
    $where[] = 'r.zone = :zone';
    $params[':zone'] = $zone;
}
if ($activityType !== '') {
    $where[] = 'r.activity_type = :activity_type';
    $params[':activity_type'] = $activityType;
}
if ($minDistance !== null) {
    $where[] = 'r.distance_km >= :min_distance';
    $params[':min_distance'] = $minDistance;
}
if ($maxDistance !== null && $maxDistance > 0) {
    $where[] = 'r.distance_km <= :max_distance';
    $params[':max_distance'] = $maxDistance;
}

$favoriteJoin = '';
$favoriteSelect = '0 AS is_favorite,';
if ($user) {
    $favoriteJoin = 'LEFT JOIN route_favorites rf ON rf.route_id = r.id AND rf.user_id = :favorite_user_id';
    $favoriteSelect = 'CASE WHEN rf.user_id IS NULL THEN 0 ELSE 1 END AS is_favorite,';
    $params[':favorite_user_id'] = (int) $user['id'];
    if ($onlyFavorites) {
        $where[] = 'rf.user_id IS NOT NULL';
    }
}

$baseSql = '
    FROM routes r
    LEFT JOIN (
        SELECT route_id, AVG(rating) AS avg_rating, COUNT(*) AS total_comments
        FROM comments
        WHERE status = "approved"
        GROUP BY route_id
    ) cstats ON cstats.route_id = r.id
    LEFT JOIN (
        SELECT route_id, COUNT(*) AS total_completions
        FROM route_completions
        GROUP BY route_id
    ) rcstats ON rcstats.route_id = r.id
    ' . $favoriteJoin . '
    WHERE ' . implode(' AND ', $where);

$countWhere = $where;
$countParams = $params;
$countJoin = '';
unset($countParams[':favorite_user_id']);
if ($onlyFavorites && $user) {
    $countWhere = array_values(array_filter($countWhere, static fn (string $clause): bool => $clause !== 'rf.user_id IS NOT NULL'));
    $countJoin = ' INNER JOIN route_favorites rf_count ON rf_count.route_id = r.id AND rf_count.user_id = :count_favorite_user_id';
    $countParams[':count_favorite_user_id'] = (int) $user['id'];
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM routes r' . $countJoin . ' WHERE ' . implode(' AND ', $countWhere));
foreach ($countParams as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalRoutes = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRoutes / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = '
    SELECT
        r.*,
        ' . $favoriteSelect . '
        COALESCE(cstats.avg_rating, 0) AS avg_rating,
        COALESCE(cstats.total_comments, 0) AS total_comments,
        COALESCE(rcstats.total_completions, 0) AS total_completions
    ' . $baseSql . '
    ORDER BY ' . $allowedSorts[$sort] . '
    LIMIT :limit OFFSET :offset
';
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $listStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$routes = $listStmt->fetchAll();

$currentReturn = 'search.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $currentReturn .= '?' . (string) $_SERVER['QUERY_STRING'];
}

$activeFilterLabels = [];
if ($search !== '') {
    $activeFilterLabels[] = 'Busqueda: ' . $search;
}
if ($difficulty !== '') {
    $activeFilterLabels[] = 'Dificultad: ' . $difficulty;
}
if ($zone !== '') {
    $activeFilterLabels[] = 'Zona: ' . $zone;
}
if ($activityType !== '') {
    $activeFilterLabels[] = 'Actividad: ' . $activityType;
}
if ($minDistance !== null) {
    $activeFilterLabels[] = 'Minimo: ' . number_format($minDistance, 1) . ' km';
}
if ($maxDistance !== null && $maxDistance > 0) {
    $activeFilterLabels[] = 'Maximo: ' . number_format($maxDistance, 1) . ' km';
}
if ($onlyFavorites) {
    $activeFilterLabels[] = 'Solo favoritas';
}

render_header('Busqueda', [
    'description' => 'Busca y filtra rutas, spots y actividades de Asturias.',
    'canonical' => 'search.php',
    'image' => 'assets/img/share-cover.svg',
]);
?>
<section class="card" style="margin-bottom: 14px;">
    <h1 class="section-title">Busqueda</h1>
    <form method="get" class="filters">
        <div>
            <label for="q">Buscar</label>
            <input id="q" name="q" value="<?= e($search) ?>" placeholder="Nombre, zona o actividad">
        </div>
        <div>
            <label for="difficulty">Dificultad</label>
            <select id="difficulty" name="difficulty">
                <option value="">Todas</option>
                <?php foreach (['Baja', 'Media', 'Alta', 'Muy Alta'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $difficulty === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="zone">Zona</label>
            <select id="zone" name="zone">
                <option value="">Todas</option>
                <?php foreach ($zones as $option): ?>
                    <option value="<?= e((string) $option) ?>" <?= $zone === (string) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="activity_type">Actividad</label>
            <select id="activity_type" name="activity_type">
                <option value="">Todas</option>
                <?php foreach ($activityTypes as $option): ?>
                    <option value="<?= e((string) $option) ?>" <?= $activityType === (string) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="min_distance">Distancia minima (km)</label>
            <input id="min_distance" type="number" step="0.1" min="0" name="min_distance" value="<?= e((string) ($_GET['min_distance'] ?? '')) ?>">
        </div>
        <div>
            <label for="max_distance">Distancia maxima (km)</label>
            <input id="max_distance" type="number" step="0.1" min="0" name="max_distance" value="<?= e((string) ($_GET['max_distance'] ?? '')) ?>">
        </div>
        <div>
            <label for="sort">Ordenar por</label>
            <select id="sort" name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mas recientes</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Mejor valoradas</option>
                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Mas completadas</option>
                <option value="points_desc" <?= $sort === 'points_desc' ? 'selected' : '' ?>>Mas puntos base</option>
                <option value="distance_asc" <?= $sort === 'distance_asc' ? 'selected' : '' ?>>Distancia ascendente</option>
                <option value="distance_desc" <?= $sort === 'distance_desc' ? 'selected' : '' ?>>Distancia descendente</option>
            </select>
        </div>
        <?php if ($user): ?>
            <div>
                <label for="only_favorites">Mostrar</label>
                <select id="only_favorites" name="only_favorites">
                    <option value="" <?= !$onlyFavorites ? 'selected' : '' ?>>Todas las rutas</option>
                    <option value="1" <?= $onlyFavorites ? 'selected' : '' ?>>Solo favoritas</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="stack">
            <button type="submit">Aplicar filtros</button>
            <a class="button secondary" href="<?= e(url('search.php')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<?php if (empty($routes)): ?>
    <section class="card empty-state route-empty-state">
        <span class="card-kicker">Sin resultados</span>
        <h2 class="section-title"><?= $onlyFavorites ? 'Todavia no tienes rutas favoritas' : 'No encontramos rutas con esos filtros' ?></h2>
        <?php if (!empty($activeFilterLabels)): ?>
            <div class="empty-filter-list" aria-label="Filtros activos">
                <?php foreach ($activeFilterLabels as $label): ?>
                    <span><?= e($label) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="stack empty-state-actions">
            <a class="button button-small" href="<?= e(url('search.php')) ?>">Ver todas</a>
            <a class="button secondary button-small" href="<?= e(url('map.php')) ?>">Ir al mapa</a>
        </div>
    </section>
<?php else: ?>
    <section class="grid grid-3">
        <?php foreach ($routes as $route): ?>
            <?php
                $routeIsSurf = route_is_surf($route);
                $routeSurf = $routeIsSurf ? route_surf_details($route) : null;
            ?>
            <article class="card <?= $routeIsSurf ? 'surf-route-card' : '' ?>">
                <?php if (!empty($route['cover_image'])): ?>
                    <img
                        class="route-cover"
                        src="<?= e(route_image_src((string) $route['cover_image'], 1200)) ?>"
                        srcset="<?= e(route_image_srcset((string) $route['cover_image'], [640, 960, 1280, 1600])) ?>"
                        sizes="(max-width: 760px) calc(100vw - 56px), (max-width: 1180px) 31vw, 360px"
                        alt="Imagen de <?= e((string) $route['name']) ?>"
                        loading="lazy"
                        decoding="async"
                    >
                <?php endif; ?>
                <h3><?= e((string) $route['name']) ?></h3>
                <p class="meta"><?= e((string) $route['zone']) ?> - <?= e((string) $route['activity_type']) ?></p>
                <p>
                    <span class="pill"><?= e((string) $route['difficulty']) ?></span>
                    <?php if ($routeIsSurf && is_array($routeSurf)): ?>
                        <span class="pill surf-pill"><?= e((string) $routeSurf['level']) ?></span>
                    <?php else: ?>
                        <span class="pill orange"><?= number_format((float) $route['distance_km'], 1) ?> km</span>
                    <?php endif; ?>
                </p>
                <?php if ($routeIsSurf && is_array($routeSurf)): ?>
                    <div class="surf-card-facts">
                        <span><small>Marea</small><strong><?= e((string) $routeSurf['tide']) ?></strong></span>
                        <span><small>Viento</small><strong><?= e((string) $routeSurf['wind']) ?></strong></span>
                        <span><small>Ola</small><strong><?= e((string) $routeSurf['wave']) ?></strong></span>
                    </div>
                <?php else: ?>
                    <p class="meta">Desnivel: <?= (int) $route['elevation_m'] ?> m - Puntos base: <?= (int) $route['base_points'] ?></p>
                <?php endif; ?>
                <p class="meta">
                    Valoracion: <?= number_format((float) $route['avg_rating'], 1) ?>/5 (<?= (int) $route['total_comments'] ?>) -
                    <?= $routeIsSurf ? 'Sesiones registradas: ' . (int) $route['total_completions'] : 'Completada ' . (int) $route['total_completions'] . ' veces' ?>
                </p>
                <div class="stack">
                    <a class="button button-small" href="<?= e(url('route.php?id=' . (int) $route['id'])) ?>"><?= $routeIsSurf ? 'Ver spot' : 'Ver detalle' ?></a>
                    <?php if ($user): ?>
                        <a class="button secondary button-small" href="<?= e(url('complete_route.php?route_id=' . (int) $route['id'])) ?>"><?= $routeIsSurf ? 'Registrar sesion' : 'Marcar completada' ?></a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
    <section class="card" style="margin-top: 14px;">
        <div class="stack">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                    $query = $_GET;
                    $query['page'] = $p;
                ?>
                <a class="button <?= $p === $page ? '' : 'secondary' ?> button-small" href="<?= e(url('search.php?' . http_build_query($query))) ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
