<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();

$routesStmt = $pdo->query('
    SELECT
        id,
        name,
        zone,
        description,
        distance_km,
        elevation_m,
        difficulty,
        activity_type,
        base_points,
        cover_image,
        coordinates_json
    FROM routes
    WHERE submission_status = "approved"
    ORDER BY name ASC
');
$rawRoutes = $routesStmt->fetchAll();

$routesData = [];
$activityOptions = [];
$difficultyOptions = [];
$totalDistance = 0.0;
$surfRoutesCount = 0;

foreach ($rawRoutes as $route) {
    $mapPayload = route_map_payload($pdo, $route);
    $points = $mapPayload['points'];
    if (count($points) < 2) {
        continue;
    }

    $duration = route_estimated_duration_range($route);
    $activityOptions[(string) $route['activity_type']] = true;
    $difficultyOptions[(string) $route['difficulty']] = true;
    $totalDistance += (float) $route['distance_km'];
    $isSurfRoute = route_is_surf($route);
    $surfDetails = $isSurfRoute ? route_surf_details($route) : null;
    if ($isSurfRoute) {
        $surfRoutesCount++;
    }

    $routesData[] = [
        'id' => (int) $route['id'],
        'name' => (string) $route['name'],
        'zone' => (string) $route['zone'],
        'difficulty' => (string) $route['difficulty'],
        'activity_type' => (string) $route['activity_type'],
        'distance_km' => round((float) $route['distance_km'], 1),
        'elevation_m' => (int) $route['elevation_m'],
        'estimated_duration' => (string) $duration['label'],
        'summary' => meta_text((string) $route['description'], 130),
        'cover_image' => (string) ($route['cover_image'] ?? ''),
        'cover_image_src' => route_image_src((string) ($route['cover_image'] ?? ''), 640),
        'is_surf' => $isSurfRoute,
        'surf' => $surfDetails,
        'points' => $points,
        'url' => url('route.php?id=' . (int) $route['id']),
        'map_source' => route_map_source_label((string) $mapPayload['source']),
    ];
}

ksort($activityOptions);
ksort($difficultyOptions);

render_header('Mapa general', [
    'description' => 'Mapa general de rutas de Asturias con acceso rapido a cada ficha y trazados sobre Leaflet.',
    'canonical' => 'map.php',
    'image' => 'assets/img/share-cover.svg',
    'json_ld' => [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Mapa general de rutas',
        'description' => 'Mapa interactivo de las rutas de Reto Asturias Activa.',
        'url' => absolute_url('map.php'),
    ],
]);
?>
<section class="hero map-hero">
    <h1>Mapa general de rutas</h1>
    <p>Explora todas las rutas aprobadas de Asturias sobre un unico mapa. Puedes filtrar por actividad, dificultad y centrar la vista en la ruta que te interese.</p>
    <div class="stats hero-map-stats" style="margin-top: 12px;">
        <div class="stat"><small>Rutas visibles</small><strong><?= count($routesData) ?></strong></div>
        <div class="stat"><small>Distancia acumulada</small><strong><?= number_format($totalDistance, 1) ?> km</strong></div>
        <div class="stat"><small>Actividades</small><strong><?= count($activityOptions) ?></strong></div>
        <div class="stat"><small>Spots de surf</small><strong><?= $surfRoutesCount ?></strong></div>
    </div>
</section>

<section class="card">
    <div class="section-heading">
        <div>
            <h2 class="section-title" style="margin-bottom: 4px;">Explorar en mapa</h2>
            <p class="meta" style="margin-top: 0;">Selecciona una ruta de la lista o filtra el mapa para descubrir mejor cada zona.</p>
        </div>
        <p id="map-route-count" class="meta" style="margin: 0;"><?= count($routesData) ?> rutas disponibles</p>
    </div>

    <div class="filters map-filters" style="margin-bottom: 14px;">
        <div>
            <label for="map-search">Buscar en el mapa</label>
            <input id="map-search" type="search" placeholder="Nombre, zona o actividad">
        </div>
        <div>
            <label for="map-activity-filter">Actividad</label>
            <select id="map-activity-filter">
                <option value="">Todas</option>
                <?php foreach (array_keys($activityOptions) as $activity): ?>
                    <option value="<?= e((string) $activity) ?>"><?= e((string) $activity) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="map-difficulty-filter">Dificultad</label>
            <select id="map-difficulty-filter">
                <option value="">Todas</option>
                <?php foreach (array_keys($difficultyOptions) as $difficulty): ?>
                    <option value="<?= e((string) $difficulty) ?>"><?= e((string) $difficulty) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div
        id="routes-overview-map"
        class="routes-overview-map"
        data-routes='<?= e((string) json_encode($routesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
    ></div>
    <div id="map-empty-state" class="empty-state map-empty-state" hidden>
        <span class="card-kicker">Sin rutas visibles</span>
        <h3>No hay rutas que coincidan con el mapa filtrado</h3>
        <p class="meta">Prueba con otra actividad, una dificultad distinta o borra el texto de busqueda para recuperar rutas.</p>
        <button type="button" class="button secondary button-small js-map-clear-filters">Limpiar filtros</button>
    </div>
</section>

<section class="card" style="margin-top: 14px;">
    <h2 class="section-title">Seleccion rapida de rutas</h2>
    <div id="map-list-empty-state" class="empty-state map-list-empty-state" hidden>
        <span class="card-kicker">Sin resultados</span>
        <h3>No hay tarjetas para esos filtros</h3>
        <p class="meta">Cambia los filtros del mapa para volver a mostrar rutas en esta lista.</p>
        <button type="button" class="button secondary button-small js-map-clear-filters">Limpiar filtros</button>
    </div>
    <div class="map-route-grid">
        <?php foreach ($routesData as $route): ?>
            <article
                class="map-route-card <?= !empty($route['is_surf']) ? 'is-surf-route' : '' ?>"
                data-route-id="<?= (int) $route['id'] ?>"
                data-route-search="<?= e(mb_strtolower($route['name'] . ' ' . $route['zone'] . ' ' . $route['activity_type'])) ?>"
                data-route-activity="<?= e((string) $route['activity_type']) ?>"
                data-route-difficulty="<?= e((string) $route['difficulty']) ?>"
            >
                <?php if ($route['cover_image'] !== ''): ?>
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
                    <?php if (!empty($route['is_surf']) && is_array($route['surf'])): ?>
                        <span class="pill surf-pill"><?= e((string) $route['surf']['level']) ?></span>
                    <?php else: ?>
                        <span class="pill orange"><?= number_format((float) $route['distance_km'], 1) ?> km</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($route['is_surf']) && is_array($route['surf'])): ?>
                    <div class="surf-card-facts">
                        <span><small>Marea</small><strong><?= e((string) $route['surf']['tide']) ?></strong></span>
                        <span><small>Viento</small><strong><?= e((string) $route['surf']['wind']) ?></strong></span>
                        <span><small>Ola</small><strong><?= e((string) $route['surf']['wave']) ?></strong></span>
                    </div>
                    <p class="meta surf-card-warning"><?= e((string) $route['surf']['safety']) ?></p>
                <?php else: ?>
                    <p class="meta">Duracion orientativa: <?= e((string) $route['estimated_duration']) ?> - Desnivel: <?= (int) $route['elevation_m'] ?> m</p>
                <?php endif; ?>
                <p class="meta"><?= e((string) $route['summary']) ?></p>
                <div class="stack" style="margin-top: 10px;">
                    <button type="button" class="button secondary button-small js-map-focus-route" data-route-id="<?= (int) $route['id'] ?>">Ver en mapa</button>
                    <a class="button button-small" href="<?= e((string) $route['url']) ?>">Abrir ficha</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php render_footer(); ?>
