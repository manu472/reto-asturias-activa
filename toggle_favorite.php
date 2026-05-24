<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();

if (!is_post()) {
    redirect('map.php');
}

if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    set_flash('danger', 'Token de seguridad invalido.');
    redirect('map.php');
}

$routeId = (int) ($_POST['route_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'add');
$returnTo = safe_local_path((string) ($_POST['return_to'] ?? 'search.php'), 'search.php');

if ($routeId <= 0) {
    set_flash('warning', 'Ruta no valida.');
    redirect($returnTo);
}

$pdo = db();
$routeCheck = $pdo->prepare('SELECT id FROM routes WHERE id = :id LIMIT 1');
$routeCheck->execute([':id' => $routeId]);
if (!$routeCheck->fetchColumn()) {
    set_flash('warning', 'La ruta no existe o fue eliminada.');
    redirect($returnTo);
}

if ($action === 'remove') {
    $remove = $pdo->prepare('DELETE FROM route_favorites WHERE user_id = :user_id AND route_id = :route_id');
    $remove->execute([
        ':user_id' => (int) $user['id'],
        ':route_id' => $routeId,
    ]);
    session_cache_forget('user.route_preferences.' . (int) $user['id']);
    set_flash('success', 'Ruta eliminada de favoritas.');
    redirect($returnTo);
}

$add = $pdo->prepare('INSERT IGNORE INTO route_favorites (user_id, route_id, created_at) VALUES (:user_id, :route_id, NOW())');
$add->execute([
    ':user_id' => (int) $user['id'],
    ':route_id' => $routeId,
]);

session_cache_forget('user.route_preferences.' . (int) $user['id']);
set_flash('success', 'Ruta guardada en favoritas.');
redirect($returnTo);
