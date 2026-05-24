<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$user = current_user();
$period = (string) ($_GET['period'] ?? 'global');
if (!in_array($period, ['global', 'weekly', 'monthly'], true)) {
    $period = 'global';
}

$rows = [];
$myPosition = null;
$myPoints = null;
$label = 'Ranking Global';

if ($period === 'global') {
    $label = 'Ranking Global (Puntos Totales)';
    $rows = $pdo->query('
        SELECT id, name, total_points AS score, level
        FROM users
        WHERE is_active = 1
        ORDER BY total_points DESC, created_at ASC
        LIMIT 100
    ')->fetchAll();

    if ($user) {
        $myPoints = (int) $user['total_points'];
        $positionStmt = $pdo->prepare('
            SELECT COUNT(*) + 1
            FROM users
            WHERE is_active = 1 AND total_points > :points
        ');
        $positionStmt->execute([':points' => $myPoints]);
        $myPosition = (int) $positionStmt->fetchColumn();
    }
} else {
    $intervalDays = $period === 'weekly' ? 7 : 30;
    $label = $period === 'weekly' ? 'Ranking Semanal (últimos 7 días)' : 'Ranking Mensual (últimos 30 días)';
    $startDate = date('Y-m-d H:i:s', strtotime('-' . $intervalDays . ' days'));

    $rankingStmt = $pdo->prepare('
        SELECT
            u.id,
            u.name,
            u.level,
            COALESCE(SUM(rc.points_obtained), 0) AS score
        FROM users u
        LEFT JOIN route_completions rc ON rc.user_id = u.id AND rc.completed_at >= :start_date
        WHERE u.is_active = 1
        GROUP BY u.id
        HAVING score > 0
        ORDER BY score DESC, u.total_points DESC
        LIMIT 100
    ');
    $rankingStmt->execute([':start_date' => $startDate]);
    $rows = $rankingStmt->fetchAll();

    if ($user) {
        $myScoreStmt = $pdo->prepare('
            SELECT COALESCE(SUM(points_obtained), 0)
            FROM route_completions
            WHERE user_id = :user_id AND completed_at >= :start_date
        ');
        $myScoreStmt->execute([
            ':user_id' => (int) $user['id'],
            ':start_date' => $startDate,
        ]);
        $myPoints = (int) $myScoreStmt->fetchColumn();

        if ($myPoints > 0) {
            $positionPeriodStmt = $pdo->prepare('
                SELECT COUNT(*) + 1
                FROM (
                    SELECT u.id, COALESCE(SUM(rc.points_obtained), 0) AS score
                    FROM users u
                    LEFT JOIN route_completions rc ON rc.user_id = u.id AND rc.completed_at >= :start_date
                    WHERE u.is_active = 1
                    GROUP BY u.id
                    HAVING score > :my_points
                ) t
            ');
            $positionPeriodStmt->execute([
                ':start_date' => $startDate,
                ':my_points' => $myPoints,
            ]);
            $myPosition = (int) $positionPeriodStmt->fetchColumn();
        }
    }
}

render_header('Rankings', [
    'description' => 'Consulta el ranking global, semanal y mensual de la comunidad de Reto Asturias Activa.',
    'canonical' => 'rankings.php',
    'image' => 'assets/img/share-cover.svg',
]);
?>
<section class="hero">
    <h1>Rankings de la comunidad</h1>
    <p>Consulta la clasificación global o por periodos para seguir tu evolución y la del resto de participantes.</p>
</section>

<section class="card">
    <div class="stack" style="margin-bottom: 10px;">
        <a class="button <?= $period === 'global' ? '' : 'secondary' ?> button-small" href="<?= e(url('rankings.php?period=global')) ?>">Global</a>
        <a class="button <?= $period === 'weekly' ? '' : 'secondary' ?> button-small" href="<?= e(url('rankings.php?period=weekly')) ?>">Semanal</a>
        <a class="button <?= $period === 'monthly' ? '' : 'secondary' ?> button-small" href="<?= e(url('rankings.php?period=monthly')) ?>">Mensual</a>
    </div>
    <h2 class="section-title"><?= e($label) ?></h2>
    <?php if ($user): ?>
        <p class="meta">
            Tu situación:
            <?php if ($myPosition !== null): ?>
                posición <strong>#<?= (int) $myPosition ?></strong> con <strong><?= (int) $myPoints ?></strong> puntos en este ranking.
            <?php else: ?>
                aún no tienes puntuación en este periodo.
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Puntos</th>
                    <th>Nivel</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="4">No hay actividad suficiente para mostrar ranking.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e((string) $row['name']) ?></td>
                        <td><?= (int) $row['score'] ?></td>
                        <td><?= (int) $row['level'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
