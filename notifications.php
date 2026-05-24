<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();
$pdo = db();

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad invalido.');
        redirect('notifications.php');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'mark_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $markOne = $pdo->prepare('
                UPDATE notifications
                SET is_read = 1, read_at = COALESCE(read_at, NOW())
                WHERE id = :id AND user_id = :user_id
            ');
            $markOne->execute([
                ':id' => $notificationId,
                ':user_id' => (int) $user['id'],
            ]);
        }
        session_cache_forget('notifications.unread.' . (int) $user['id']);
        redirect('notifications.php');
    }

    if ($action === 'mark_all_read') {
        $markAll = $pdo->prepare('
            UPDATE notifications
            SET is_read = 1, read_at = COALESCE(read_at, NOW())
            WHERE user_id = :user_id AND is_read = 0
        ');
        $markAll->execute([':user_id' => (int) $user['id']]);
        session_cache_forget('notifications.unread.' . (int) $user['id']);
        set_flash('success', 'Notificaciones marcadas como leidas.');
        redirect('notifications.php');
    }
}

$notificationsStmt = $pdo->prepare('
    SELECT *
    FROM notifications
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 100
');
$notificationsStmt->execute([':user_id' => (int) $user['id']]);
$notifications = $notificationsStmt->fetchAll();
$unreadCount = unread_notifications_count((int) $user['id']);

render_header('Notificaciones', [
    'description' => 'Centro privado de notificaciones.',
    'canonical' => 'notifications.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card">
    <h1 class="section-title">Notificaciones</h1>
    <p class="meta">Tienes <?= (int) $unreadCount ?> notificaciones sin leer.</p>
    <form method="post" class="inline" style="margin-bottom: 10px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="mark_all_read">
        <button class="button button-small" type="submit">Marcar todas como leidas</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Titulo</th>
                    <th>Mensaje</th>
                    <th>Estado</th>
                    <th>Accion</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notifications)): ?>
                    <tr><td colspan="5">No tienes notificaciones todavia.</td></tr>
                <?php endif; ?>
                <?php foreach ($notifications as $item): ?>
                    <tr>
                        <td><?= e((string) $item['created_at']) ?></td>
                        <td>
                            <strong><?= e((string) $item['title']) ?></strong>
                            <?php if (!empty($item['link_url'])): ?>
                                <div><a href="<?= e(url((string) $item['link_url'])) ?>">Ver detalle</a></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) $item['message']) ?></td>
                        <td><?= (int) $item['is_read'] === 1 ? 'Leida' : 'Nueva' ?></td>
                        <td>
                            <?php if ((int) $item['is_read'] === 0): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int) $item['id'] ?>">
                                    <button class="button secondary button-small" type="submit">Marcar leida</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
