<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$user = current_user();
$isAdmin = $user !== null && (int) ($user['is_admin'] ?? 0) === 1;

if (is_post()) {
    if (!$user) {
        set_flash('warning', 'Debes iniciar sesión para unirte a un reto.');
        redirect('login.php');
    }
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad inválido.');
        redirect('challenges.php');
    }

    $challengeId = (int) ($_POST['challenge_id'] ?? 0);
    if ($challengeId <= 0) {
        set_flash('danger', 'Reto no válido.');
        redirect('challenges.php');
    }

    $challengeExists = $pdo->prepare('
        SELECT id
        FROM challenges
        WHERE id = :id
          AND is_active = 1
          AND CURDATE() BETWEEN start_date AND end_date
        LIMIT 1
    ');
    $challengeExists->execute([':id' => $challengeId]);
    if (!$challengeExists->fetchColumn()) {
        set_flash('warning', 'Ese reto no está activo.');
        redirect('challenges.php');
    }

    $join = $pdo->prepare('
        INSERT IGNORE INTO challenge_participants (challenge_id, user_id, progress_value, joined_at)
        VALUES (:challenge_id, :user_id, 0, NOW())
    ');
    $join->execute([
        ':challenge_id' => $challengeId,
        ':user_id' => (int) $user['id'],
    ]);

    set_flash('success', 'Te has unido al reto correctamente.');
    redirect('challenges.php');
}

if ($user) {
    $challengesStmt = $pdo->prepare('
        SELECT
            c.*,
            cp.progress_value AS my_progress,
            cp.completed_at AS my_completed_at,
            COALESCE(SUM(cp_all.progress_value), 0) AS global_progress
        FROM challenges c
        LEFT JOIN challenge_participants cp ON cp.challenge_id = c.id AND cp.user_id = :user_id
        LEFT JOIN challenge_participants cp_all ON cp_all.challenge_id = c.id
        WHERE c.is_active = 1 AND CURDATE() BETWEEN c.start_date AND c.end_date
        GROUP BY c.id, cp.progress_value, cp.completed_at
        ORDER BY c.end_date ASC
    ');
    $challengesStmt->execute([':user_id' => (int) $user['id']]);
    $challenges = $challengesStmt->fetchAll();
} else {
    $challenges = $pdo->query('
        SELECT
            c.*,
            NULL AS my_progress,
            NULL AS my_completed_at,
            COALESCE(SUM(cp.progress_value), 0) AS global_progress
        FROM challenges c
        LEFT JOIN challenge_participants cp ON cp.challenge_id = c.id
        WHERE c.is_active = 1 AND CURDATE() BETWEEN c.start_date AND c.end_date
        GROUP BY c.id
        ORDER BY c.end_date ASC
    ')->fetchAll();
}

$selectedChallengeId = (int) ($_GET['id'] ?? ($challenges[0]['id'] ?? 0));
$leaderboard = [];
if ($selectedChallengeId > 0) {
    $leaderboardStmt = $pdo->prepare('
        SELECT
            u.name,
            cp.progress_value,
            cp.completed_at
        FROM challenge_participants cp
        JOIN users u ON u.id = cp.user_id
        WHERE cp.challenge_id = :challenge_id
        ORDER BY cp.progress_value DESC, cp.joined_at ASC
        LIMIT 15
    ');
    $leaderboardStmt->execute([':challenge_id' => $selectedChallengeId]);
    $leaderboard = $leaderboardStmt->fetchAll();
}

render_header('Retos comunitarios', [
    'description' => 'Participa en retos activos, acumula progreso al completar rutas y compite con otros usuarios.',
    'canonical' => 'challenges.php',
    'image' => 'assets/img/share-cover.svg',
]);
?>
<section class="hero">
    <h1>Retos Comunitarios</h1>
    <p>Únete a desafíos activos, suma progreso automático al completar rutas y desbloquea recompensas extra.</p>
    <?php if ($isAdmin): ?>
        <div class="stack" style="margin-top: 14px;">
            <a class="button secondary" href="<?= e(url('admin.php?tab=challenges#challenge-form')) ?>">Añadir reto</a>
        </div>
    <?php endif; ?>
</section>

<section class="grid grid-2">
    <article class="card">
        <h2 class="section-title">Retos activos</h2>
        <?php if (empty($challenges)): ?>
            <p class="muted">No hay retos activos actualmente.</p>
        <?php endif; ?>

        <?php foreach ($challenges as $challenge): ?>
            <?php
            $target = max(1.0, (float) $challenge['target_value']);
            $globalProgress = (float) $challenge['global_progress'];
            $globalPercent = min(100, (int) round(($globalProgress / $target) * 100));
            $myProgress = $challenge['my_progress'] !== null ? (float) $challenge['my_progress'] : null;
            $myPercent = $myProgress !== null ? min(100, (int) round(($myProgress / $target) * 100)) : 0;
            $joined = $myProgress !== null;
            ?>
            <div style="border-bottom: 1px solid #dfebdf; padding: 10px 0;">
                <h3 style="margin: 0;"><?= e((string) $challenge['title']) ?></h3>
                <p class="meta" style="margin: 5px 0;"><?= e((string) $challenge['description']) ?></p>
                <p class="meta" style="margin: 5px 0;">
                    Objetivo: <?= number_format($target, 1) ?> (<?= e((string) $challenge['target_type']) ?>) - Recompensa <?= (int) $challenge['reward_points'] ?> pts
                </p>
                <div class="progress"><span style="width: <?= $globalPercent ?>%;"></span></div>
                <p class="meta" style="margin: 6px 0;">Progreso global: <?= number_format($globalProgress, 1) ?> / <?= number_format($target, 1) ?> (<?= $globalPercent ?>%)</p>
                <?php if ($user): ?>
                    <?php if ($joined): ?>
                        <p class="meta">Tu progreso: <?= number_format($myProgress, 1) ?> / <?= number_format($target, 1) ?> (<?= $myPercent ?>%) <?= !empty($challenge['my_completed_at']) ? '- Completado' : '' ?></p>
                    <?php else: ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="challenge_id" value="<?= (int) $challenge['id'] ?>">
                            <button type="submit" class="button button-small">Participar</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="button button-small" href="<?= e(url('login.php')) ?>">Inicia sesión para participar</a>
                <?php endif; ?>
                <a class="button secondary button-small" href="<?= e(url('challenges.php?id=' . (int) $challenge['id'])) ?>">Ver clasificación</a>
            </div>
        <?php endforeach; ?>
    </article>

    <article class="card">
        <h2 class="section-title">Clasificación del reto</h2>
        <?php if ($selectedChallengeId <= 0): ?>
            <p class="muted">Sin retos para mostrar ranking.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Usuario</th>
                            <th>Progreso</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $entry): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e((string) $entry['name']) ?></td>
                                <td><?= number_format((float) $entry['progress_value'], 1) ?></td>
                                <td><?= !empty($entry['completed_at']) ? 'Completado' : 'Activo' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php render_footer(); ?>
