<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$stats = [
    'approved_routes' => 0,
    'active_users' => 0,
    'completions' => 0,
    'proposed_routes' => 0,
];

try {
    $pdo = db();
    $statsStmt = $pdo->query('
        SELECT
            (SELECT COUNT(*) FROM routes WHERE submission_status = "approved") AS approved_routes,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) AS active_users,
            (SELECT COUNT(*) FROM route_completions) AS completions,
            (SELECT COUNT(*) FROM routes WHERE submission_status IN ("pending", "approved")) AS proposed_routes
    ');
    $stats = $statsStmt->fetch() ?: $stats;
} catch (Throwable) {
    // Fallback silencioso para no romper la pagina institucional.
}

render_header('Sobre nosotros', [
    'description' => 'Conoce la propuesta de valor, la vision y el enfoque de Reto Asturias Activa como proyecto digital centrado en rutas y actividad al aire libre en Asturias.',
    'canonical' => 'about.php',
    'image' => 'assets/img/share-cover.svg',
    'json_ld' => [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'Sobre nosotros | ' . APP_NAME,
        'description' => 'Presentacion del proyecto, su propuesta de valor y su enfoque sobre rutas y comunidad en Asturias.',
        'url' => absolute_url('about.php'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => APP_NAME,
            'url' => absolute_url('index.php'),
        ],
    ],
]);
?>
<section class="hero institutional-hero">
    <span class="card-kicker">Proyecto</span>
    <h1>Una plataforma para descubrir Asturias con mas criterio, mas comunidad y mas herramientas utiles</h1>
    <p>Reto Asturias Activa nace con una idea clara: que una web de rutas no solo inspire, sino que ayude de verdad a planificar, completar, comparar y compartir actividad al aire libre dentro de Asturias.</p>
    <div class="stats hero-map-stats" style="margin-top: 18px;">
        <div class="stat"><small>Rutas publicadas</small><strong><?= number_format((int) $stats['approved_routes']) ?></strong></div>
        <div class="stat"><small>Usuarios activos</small><strong><?= number_format((int) $stats['active_users']) ?></strong></div>
        <div class="stat"><small>Rutas completadas</small><strong><?= number_format((int) $stats['completions']) ?></strong></div>
        <div class="stat"><small>Rutas propuestas</small><strong><?= number_format((int) $stats['proposed_routes']) ?></strong></div>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <span class="card-kicker">Mision</span>
        <h2 class="section-title">Convertir el catalogo de rutas en una experiencia completa</h2>
        <p class="content-prose">La web no se queda en mostrar una lista de lugares. El objetivo es combinar informacion practica, mapa, comunidad, gamificacion y herramientas de planificacion para que el usuario se mueva por Asturias con mas confianza y mas motivacion.</p>
        <p class="content-prose">Eso significa que cada ruta debe ser util tanto para quien busca una idea de fin de semana como para quien quiere seguir progresando, medir su actividad o preparar la salida con mas detalle.</p>
    </article>

    <article class="card">
        <span class="card-kicker">Vision</span>
        <h2 class="section-title">Un proyecto local, digital y escalable</h2>
        <p class="content-prose">Reto Asturias Activa esta pensado como una plataforma centrada en Asturias, con identidad territorial clara y posibilidades reales de crecimiento: comunidad, contenido, premium, colaboraciones y una capa de datos que haga mas valiosa cada visita.</p>
        <p class="content-prose">La propuesta mezcla utilidad practica para la persona usuaria y un modelo de negocio razonable para sostener el proyecto a medio plazo.</p>
    </article>
</section>

<section class="grid grid-3" style="margin-top: 14px;">
    <article class="card card-soft">
        <h2 class="section-title">Que aporta al usuario</h2>
        <ul class="feature-list">
            <li>Busqueda y filtrado de rutas centrados en Asturias.</li>
            <li>Mapa general y fichas mas completas para decidir mejor.</li>
            <li>Gamificacion, retos y ranking para mantener el enganche.</li>
            <li>Ventajas premium enfocadas a planificacion y uso real.</li>
        </ul>
    </article>
    <article class="card card-soft">
        <h2 class="section-title">Que aporta al territorio</h2>
        <ul class="feature-list">
            <li>Visibilidad para rutas y zonas concretas de Asturias.</li>
            <li>Participacion de la comunidad proponiendo nuevas rutas.</li>
            <li>Mayor cultura de preparacion y seguridad en la salida.</li>
            <li>Base para futuras colaboraciones con negocios locales.</li>
        </ul>
    </article>
    <article class="card card-soft">
        <h2 class="section-title">Que aporta al proyecto</h2>
        <ul class="feature-list">
            <li>Un discurso mas fuerte y profesional para presentar la web.</li>
            <li>Mayor credibilidad institucional y comercial.</li>
            <li>Mejor base para SEO, contenidos y monetizacion.</li>
            <li>Una identidad menos generica y mas defendible.</li>
        </ul>
    </article>
</section>

<section class="card" style="margin-top: 14px;">
    <div class="section-heading">
        <div>
            <span class="card-kicker">Propuesta de valor</span>
            <h2 class="section-title" style="margin-bottom: 4px;">Por que no es solo otra web de rutas</h2>
            <p class="meta" style="margin-top: 0;">La diferencia esta en juntar catalogo, comunidad, planificacion y progresion dentro de una misma experiencia.</p>
        </div>
    </div>
    <div class="timeline-list">
        <div class="timeline-item">
            <strong>Descubrir</strong>
            <p>El usuario entra por rutas reales de Asturias, por el mapa o por contenidos del blog.</p>
        </div>
        <div class="timeline-item">
            <strong>Decidir mejor</strong>
            <p>La ficha amplia, el perfil de altitud, el trazado y la descarga ayudan a elegir con mas criterio.</p>
        </div>
        <div class="timeline-item">
            <strong>Volver</strong>
            <p>Retos, ranking, favoritas, premium y recomendaciones hacen que la plataforma tenga continuidad.</p>
        </div>
        <div class="timeline-item">
            <strong>Crecer</strong>
            <p>El contenido, la comunidad y el modelo premium convierten la web en un proyecto sostenible.</p>
        </div>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card premium-surface">
        <span class="card-kicker premium-kicker">Modelo</span>
        <h2 class="section-title">Como se sostiene el proyecto</h2>
        <p class="content-prose">La idea de negocio combina una capa gratuita fuerte, un plan premium con ventajas claras y la posibilidad de futuras colaboraciones con actores del territorio. El premium tiene sentido cuando aporta herramientas reales, no solo extras cosméticos.</p>
        <ul class="feature-list">
            <li>Suscripcion premium con valor practico.</li>
            <li>Contenido y posicionamiento organico via blog.</li>
            <li>Potenciales alianzas con negocios y servicios locales.</li>
        </ul>
    </article>

    <article class="card">
        <span class="card-kicker">Hoja de ruta</span>
        <h2 class="section-title">Siguientes lineas de evolucion</h2>
        <ul class="feature-list">
            <li>Mas rutas verificadas y mejores trazados reales.</li>
            <li>Reportes de estado del camino por parte de usuarios.</li>
            <li>Mayor peso del contenido y del SEO local.</li>
            <li>Panel admin mas potente y control de colaboraciones.</li>
        </ul>
    </article>
</section>

<section class="card" style="margin-top: 14px;">
    <span class="card-kicker">Contexto</span>
    <h2 class="section-title">Un proyecto academico con aspiracion realista</h2>
    <p class="content-prose">La web se presenta como proyecto DAW, pero esta planteada con criterios de producto digital real: narrativa de marca, estructura institucional, monetizacion, contenido y una experiencia que intenta aportar valor antes, durante y despues de la ruta.</p>
    <div class="stack" style="margin-top: 12px;">
        <a class="button button-small" href="<?= e(url('blog.php')) ?>">Ir al blog</a>
        <a class="button secondary button-small" href="<?= e(url('premium.php')) ?>">Ver premium</a>
        <a class="button secondary button-small" href="<?= e(url('legal.php')) ?>">Centro legal</a>
    </div>
</section>
<?php render_footer(); ?>
