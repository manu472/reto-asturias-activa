<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$today = date('Y-m-d');

$staticPages = [
    ['path' => 'index.php', 'changefreq' => 'weekly', 'priority' => '1.0', 'lastmod' => $today],
    ['path' => 'map.php', 'changefreq' => 'daily', 'priority' => '0.95', 'lastmod' => $today],
    ['path' => 'search.php', 'changefreq' => 'daily', 'priority' => '0.9', 'lastmod' => $today],
    ['path' => 'rankings.php', 'changefreq' => 'daily', 'priority' => '0.8', 'lastmod' => $today],
    ['path' => 'challenges.php', 'changefreq' => 'daily', 'priority' => '0.8', 'lastmod' => $today],
    ['path' => 'about.php', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
    ['path' => 'blog.php', 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
    ['path' => 'legal.php', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
    ['path' => 'privacy.php', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
    ['path' => 'cookies.php', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
    ['path' => 'terms.php', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
];

$routeRows = [];
try {
    $pdo = db();
    $routeRows = $pdo->query('
        SELECT id, COALESCE(updated_at, created_at) AS lastmod
        FROM routes
        WHERE submission_status = "approved"
        ORDER BY id ASC
    ')->fetchAll();
} catch (Throwable) {
    $routeRows = [];
}

function site_map_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
  <url>
    <loc><?= site_map_xml_escape(absolute_url((string) $page['path'])) ?></loc>
    <lastmod><?= site_map_xml_escape((string) $page['lastmod']) ?></lastmod>
    <changefreq><?= site_map_xml_escape((string) $page['changefreq']) ?></changefreq>
    <priority><?= site_map_xml_escape((string) $page['priority']) ?></priority>
  </url>
<?php endforeach; ?>
<?php foreach ($routeRows as $routeRow): ?>
  <url>
    <loc><?= site_map_xml_escape(absolute_url('route.php?id=' . (int) $routeRow['id'])) ?></loc>
    <lastmod><?= site_map_xml_escape(date('Y-m-d', strtotime((string) $routeRow['lastmod'] ?: $today))) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
<?php foreach (blog_posts_catalog() as $post): ?>
  <url>
    <loc><?= site_map_xml_escape(absolute_url('blog_post.php?slug=' . (string) $post['slug'])) ?></loc>
    <lastmod><?= site_map_xml_escape((string) ($post['updated_at'] ?? $today)) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endforeach; ?>
</urlset>
