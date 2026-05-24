<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();
$pdo = db();
$format = mb_strtolower(trim((string) ($_GET['format'] ?? 'csv')));
if (!in_array($format, ['csv', 'json'], true)) {
    $format = 'csv';
}

if ($format === 'json' && !user_has_active_premium($user)) {
    set_flash('warning', 'La exportacion avanzada en JSON es una ventaja del plan premium.');
    redirect('premium.php');
}

$historyStmt = $pdo->prepare('
    SELECT
        rc.completed_at,
        rc.duration_min,
        rc.points_obtained,
        rc.notes,
        r.name AS route_name,
        r.zone,
        r.activity_type,
        r.difficulty,
        r.distance_km
    FROM route_completions rc
    JOIN routes r ON r.id = rc.route_id
    WHERE rc.user_id = :user_id
    ORDER BY rc.completed_at DESC
');
$historyStmt->execute([':user_id' => (int) $user['id']]);
$history = $historyStmt->fetchAll();

$favoritesStmt = $pdo->prepare('
    SELECT
        rf.created_at AS favorited_at,
        r.name AS route_name,
        r.zone,
        r.activity_type,
        r.difficulty,
        r.distance_km
    FROM route_favorites rf
    JOIN routes r ON r.id = rf.route_id
    WHERE rf.user_id = :user_id
    ORDER BY rf.created_at DESC
');
$favoritesStmt->execute([':user_id' => (int) $user['id']]);
$favorites = $favoritesStmt->fetchAll();

function csv_safe_value(string $value): string
{
    $trimmed = ltrim($value);
    if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }

    return $value;
}

if ($format === 'json') {
    $summary = [
        'user' => [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'is_premium' => user_has_active_premium($user),
        ],
        'generated_at' => date('c'),
        'totals' => [
            'routes_completed' => count($history),
            'favorites_total' => count($favorites),
            'distance_km' => round(array_reduce($history, static function (float $carry, array $row): float {
                return $carry + (float) ($row['distance_km'] ?? 0);
            }, 0.0), 2),
            'points_total' => array_reduce($history, static function (int $carry, array $row): int {
                return $carry + (int) ($row['points_obtained'] ?? 0);
            }, 0),
        ],
        'routes_completed' => $history,
        'favorites' => $favorites,
    ];

    $filename = sprintf('historial_%d_%s.json', (int) $user['id'], date('Ymd_His'));
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$filename = sprintf('historial_%d_%s.csv', (int) $user['id'], date('Ymd_His'));

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'wb');
if ($out === false) {
    exit;
}

fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Reto Asturias Activa - Exportacion de historial'], ';');
fputcsv($out, ['Usuario', (string) $user['name'], 'Email', (string) $user['email']], ';');
fputcsv($out, ['Generado en', date('Y-m-d H:i:s')], ';');
fputcsv($out, [], ';');

fputcsv($out, ['Rutas completadas'], ';');
fputcsv($out, ['Fecha', 'Ruta', 'Zona', 'Actividad', 'Dificultad', 'Distancia_km', 'Duracion_min', 'Puntos', 'Notas'], ';');
foreach ($history as $row) {
    $notes = trim((string) ($row['notes'] ?? ''));
    $notes = preg_replace('/\s+/', ' ', $notes);
    fputcsv($out, [
        (string) $row['completed_at'],
        csv_safe_value((string) $row['route_name']),
        csv_safe_value((string) $row['zone']),
        csv_safe_value((string) $row['activity_type']),
        csv_safe_value((string) $row['difficulty']),
        number_format((float) $row['distance_km'], 2, '.', ''),
        (int) $row['duration_min'],
        (int) $row['points_obtained'],
        csv_safe_value((string) $notes),
    ], ';');
}

fputcsv($out, [], ';');
fputcsv($out, ['Rutas favoritas'], ';');
fputcsv($out, ['Fecha_favorito', 'Ruta', 'Zona', 'Actividad', 'Dificultad', 'Distancia_km'], ';');
foreach ($favorites as $row) {
    fputcsv($out, [
        (string) $row['favorited_at'],
        csv_safe_value((string) $row['route_name']),
        csv_safe_value((string) $row['zone']),
        csv_safe_value((string) $row['activity_type']),
        csv_safe_value((string) $row['difficulty']),
        number_format((float) $row['distance_km'], 2, '.', ''),
    ], ';');
}

fclose($out);
exit;
