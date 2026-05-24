<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$post = $slug !== '' ? blog_post_by_slug($slug) : null;
if ($post === null) {
    set_flash('warning', 'El articulo solicitado no existe.');
    redirect('blog.php');
}

$otherPosts = array_values(array_filter(
    blog_posts_catalog(),
    static fn (array $candidate): bool => (string) ($candidate['slug'] ?? '') !== (string) $post['slug']
));
$otherPosts = array_slice($otherPosts, 0, 3);

render_header((string) $post['title'], [
    'description' => (string) $post['description'],
    'canonical' => 'blog_post.php?slug=' . (string) $post['slug'],
    'image' => 'assets/img/share-cover.svg',
    'type' => 'article',
    'json_ld' => [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => (string) $post['title'],
        'description' => (string) $post['description'],
        'datePublished' => (string) $post['published_at'],
        'dateModified' => (string) $post['updated_at'],
        'articleSection' => (string) $post['category'],
        'url' => absolute_url('blog_post.php?slug=' . (string) $post['slug']),
        'publisher' => [
            '@type' => 'Organization',
            'name' => APP_NAME,
        ],
    ],
]);
?>
<article class="card article-shell">
    <span class="card-kicker"><?= e((string) $post['category']) ?></span>
    <h1 class="section-title article-title"><?= e((string) $post['title']) ?></h1>
    <div class="article-meta-bar">
        <span><?= e((string) $post['published_at']) ?></span>
        <span><?= e((string) $post['read_time']) ?></span>
        <span><?= count((array) ($post['sections'] ?? [])) ?> bloques</span>
    </div>
    <p class="content-prose article-intro"><?= e((string) $post['excerpt']) ?></p>

    <?php foreach ((array) ($post['sections'] ?? []) as $section): ?>
        <section class="article-section">
            <h2><?= e((string) ($section['heading'] ?? '')) ?></h2>
            <?php foreach ((array) ($section['paragraphs'] ?? []) as $paragraph): ?>
                <p class="content-prose"><?= e((string) $paragraph) ?></p>
            <?php endforeach; ?>
            <?php if (!empty($section['bullets']) && is_array($section['bullets'])): ?>
                <ul class="feature-list">
                    <?php foreach ($section['bullets'] as $bullet): ?>
                        <li><?= e((string) $bullet) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <div class="tag-row" style="margin-top: 18px;">
        <?php foreach ((array) ($post['tags'] ?? []) as $tag): ?>
            <span class="mini-badge"><?= e((string) $tag) ?></span>
        <?php endforeach; ?>
    </div>

    <div class="stack" style="margin-top: 18px;">
        <a class="button button-small" href="<?= e(url('blog.php')) ?>">Volver al blog</a>
        <a class="button secondary button-small" href="<?= e(url('search.php')) ?>">Buscar rutas</a>
    </div>
</article>

<?php if (!empty($otherPosts)): ?>
    <section style="margin-top: 14px;">
        <div class="section-heading">
            <div>
                <h2 class="section-title" style="margin-bottom: 4px;">Seguir leyendo</h2>
                <p class="meta" style="margin-top: 0;">Mas contenido relacionado para reforzar la parte editorial del proyecto.</p>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($otherPosts as $other): ?>
                <article class="card blog-card">
                    <span class="card-kicker"><?= e((string) $other['category']) ?></span>
                    <h3><?= e((string) $other['title']) ?></h3>
                    <p class="meta"><?= e((string) $other['published_at']) ?> - <?= e((string) $other['read_time']) ?></p>
                    <p class="content-prose"><?= e((string) $other['excerpt']) ?></p>
                    <a class="button button-small" href="<?= e(url('blog_post.php?slug=' . (string) $other['slug'])) ?>">Leer</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
