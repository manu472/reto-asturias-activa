<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();

$summary = [
    'routes' => 0,
    'activities' => 0,
    'surf_spots' => 0,
    'distance_km' => 0.0,
];

try {
    $summaryStmt = $pdo->query('
        SELECT
            COUNT(*) AS routes,
            COUNT(DISTINCT activity_type) AS activities,
            COALESCE(SUM(CASE WHEN activity_type = "Surf" THEN 1 ELSE 0 END), 0) AS surf_spots,
            COALESCE(SUM(distance_km), 0) AS distance_km
        FROM routes
        WHERE submission_status = "approved"
    ');
    $summary = array_merge($summary, $summaryStmt->fetch() ?: []);
} catch (Throwable) {
    $summary = $summary;
}

render_header('Inicio', [
    'description' => 'Presentacion de Reto Asturias Activa, una web para descubrir rutas, spots de surf, retos y ranking en Asturias.',
    'canonical' => 'index.php',
    'image' => 'assets/img/share-cover.svg',
    'json_ld' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => APP_NAME,
        'url' => absolute_url('index.php'),
        'description' => site_default_description(),
    ],
]);
?>
<section class="hero home-hero">
    <h1>Reto Asturias Activa</h1>
    <p>Una plataforma para descubrir Asturias a traves de rutas, spots de surf, retos, favoritos y un ranking de progreso.</p>
    <div class="stack" style="margin-top: 14px;">
        <a class="button" href="<?= e(url('map.php')) ?>">Abrir mapa general</a>
        <a class="button secondary" href="<?= e(url('search.php')) ?>">Buscar rutas</a>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <span class="card-kicker">Proyecto</span>
        <h2 class="section-title">Que puedes hacer aqui</h2>
        <p class="meta">La web centraliza actividades al aire libre en Asturias para que puedas explorar el mapa, filtrar por actividad o dificultad, guardar favoritas y registrar tus salidas.</p>
        <div class="stats" style="margin-top: 12px;">
            <div class="stat"><small>Rutas y spots</small><strong><?= (int) $summary['routes'] ?></strong></div>
            <div class="stat"><small>Actividades</small><strong><?= (int) $summary['activities'] ?></strong></div>
            <div class="stat"><small>Spots de surf</small><strong><?= (int) $summary['surf_spots'] ?></strong></div>
            <div class="stat"><small>Km catalogados</small><strong><?= number_format((float) $summary['distance_km'], 1) ?></strong></div>
        </div>
    </article>

    <article class="card">
        <span class="card-kicker">Resumen</span>
        <h2 class="section-title">Como esta organizada</h2>
        <div class="feature-list">
            <div>
                <strong>Mapa</strong>
                <p class="meta">Vista general para localizar rutas y spots sobre Asturias.</p>
            </div>
            <div>
                <strong>Busqueda</strong>
                <p class="meta">Pantalla dedicada a buscar y filtrar por actividad, zona, dificultad o distancia.</p>
            </div>
            <div>
                <strong>Retos y ranking</strong>
                <p class="meta">Gamificacion para sumar puntos, completar objetivos y comparar progreso.</p>
            </div>
        </div>
    </article>
</section>

<section class="card" style="margin-top: 14px;">
    <span class="card-kicker">Asturias activa</span>
    <h2 class="section-title">Rutas, costa y comunidad</h2>
    <p class="meta">El objetivo es que cualquier usuario pueda encontrar planes reales en Asturias: senderismo, ciclismo, trail y tambien surf, con fichas adaptadas a cada actividad.</p>
    <div class="stack" style="margin-top: 12px;">
        <a class="button button-small" href="<?= e(url('map.php')) ?>">Empezar por el mapa</a>
        <a class="button secondary button-small" href="<?= e(url('about.php')) ?>">Conocer el proyecto</a>
    </div>
</section>

<?php render_footer(); ?>
