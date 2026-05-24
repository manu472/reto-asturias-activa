<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$posts = blog_posts_catalog();
$featured = $posts[0] ?? null;
$latestPosts = array_slice($posts, 1);

render_header('Blog', [
    'description' => 'Consejos, guias y contenido util sobre rutas, planificacion, seguridad y actividad al aire libre en Asturias.',
    'canonical' => 'blog.php',
    'image' => 'assets/img/share-cover.svg',
    'json_ld' => [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'Blog | ' . APP_NAME,
        'description' => 'Seccion de contenido y guias de Reto Asturias Activa.',
        'url' => absolute_url('blog.php'),
    ],
]);
?>
<section class="hero institutional-hero">
    <span class="card-kicker">Contenido</span>
    <h1>Blog y guias para salir mejor preparado por Asturias</h1>
    <p>Consejos practicos, guias y recomendaciones para planificar rutas, descubrir lugares y disfrutar Asturias con mas contexto antes de salir.</p>
</section>

<?php if ($featured): ?>
    <section class="card content-featured-card" style="margin-top: 14px;">
        <div>
            <div>
                <span class="card-kicker"><?= e((string) $featured['category']) ?></span>
                <h2 class="section-title"><?= e((string) $featured['title']) ?></h2>
                <p class="meta"><?= e((string) $featured['published_at']) ?> - <?= e((string) $featured['read_time']) ?></p>
                <p class="content-prose"><?= e((string) $featured['excerpt']) ?></p>
                <div class="stack" style="margin-top: 12px;">
                    <a class="button" href="<?= e(url('blog_post.php?slug=' . (string) $featured['slug'])) ?>">Leer articulo</a>
                    <a class="button secondary" href="<?= e(url('about.php')) ?>">Conocer el proyecto</a>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<section style="margin-top: 14px;">
    <div class="section-heading">
        <div>
            <h2 class="section-title" style="margin-bottom: 4px;">Ultimos articulos</h2>
            <p class="meta" style="margin-top: 0;">Ideas y consejos para preparar mejor cada salida por Asturias.</p>
        </div>
    </div>
    <div class="grid grid-2 blog-list-grid">
        <?php foreach ($posts as $post): ?>
            <article class="card blog-card">
                <span class="card-kicker"><?= e((string) $post['category']) ?></span>
                <h3><?= e((string) $post['title']) ?></h3>
                <p class="meta"><?= e((string) $post['published_at']) ?> - <?= e((string) $post['read_time']) ?></p>
                <p class="content-prose"><?= e((string) $post['excerpt']) ?></p>
                <div class="tag-row">
                    <?php foreach ((array) ($post['tags'] ?? []) as $tag): ?>
                        <span class="mini-badge"><?= e((string) $tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="stack" style="margin-top: 12px;">
                    <a class="button button-small" href="<?= e(url('blog_post.php?slug=' . (string) $post['slug'])) ?>">Leer</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php render_footer(); ?>
