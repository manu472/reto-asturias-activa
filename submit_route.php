<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_login();
$pdo = db();

if (!user_can_submit_routes($user)) {
    set_flash('warning', 'Proponer rutas forma parte de Premium. Activalo para enviar rutas al equipo de revision.');
    redirect('premium.php#premium-benefits');
}

$allowedDifficulties = ['Baja', 'Media', 'Alta', 'Muy Alta'];
$allowedActivities = ['Senderismo', 'Trail', 'Ciclismo', 'Running', 'BTT', 'Marcha nordica', 'Surf'];

$editSubmission = null;
$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId > 0) {
    $editStmt = $pdo->prepare('
        SELECT *
        FROM routes
        WHERE id = :id
          AND created_by = :user_id
          AND submission_status = "pending"
          AND is_preloaded = 0
        LIMIT 1
    ');
    $editStmt->execute([
        ':id' => $editId,
        ':user_id' => (int) $user['id'],
    ]);
    $editSubmission = $editStmt->fetch();
    if (!$editSubmission) {
        set_flash('warning', 'La propuesta a editar no existe o ya fue revisada.');
        redirect('submit_route.php');
    }
}

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad invalido.');
        redirect('submit_route.php');
    }

    $action = (string) ($_POST['action'] ?? 'save_submission');

    if ($action === 'withdraw_submission') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        if ($routeId <= 0) {
            set_flash('warning', 'No se pudo identificar la propuesta.');
            redirect('submit_route.php');
        }

        $withdrawStmt = $pdo->prepare('
            UPDATE routes
            SET submission_status = "rejected",
                review_note = "Retirada por el autor",
                reviewed_at = NOW(),
                reviewed_by = NULL,
                updated_at = NOW()
            WHERE id = :id
              AND created_by = :user_id
              AND submission_status = "pending"
              AND is_preloaded = 0
        ');
        $withdrawStmt->execute([
            ':id' => $routeId,
            ':user_id' => (int) $user['id'],
        ]);

        if ($withdrawStmt->rowCount() === 0) {
            set_flash('warning', 'La propuesta no se pudo retirar porque ya fue revisada.');
        } else {
            set_flash('success', 'Propuesta retirada correctamente.');
        }
        redirect('submit_route.php');
    }

    $routeId = (int) ($_POST['route_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $zone = trim((string) ($_POST['zone'] ?? ''));
    $difficulty = trim((string) ($_POST['difficulty'] ?? ''));
    $activityType = trim((string) ($_POST['activity_type'] ?? ''));
    $distanceKm = (float) ($_POST['distance_km'] ?? 0);
    $elevationM = (int) ($_POST['elevation_m'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));
    $coverImage = trim((string) ($_POST['cover_image'] ?? ''));
    $coordsRaw = trim((string) ($_POST['coordinates_json'] ?? ''));
    $coordsJson = '';

    $errors = [];
    if (mb_strlen($name) < 5 || mb_strlen($name) > 150) {
        $errors[] = 'El nombre debe tener entre 5 y 150 caracteres.';
    }
    if (mb_strlen($zone) < 3 || mb_strlen($zone) > 100) {
        $errors[] = 'La zona debe tener entre 3 y 100 caracteres.';
    }
    if (!in_array($difficulty, $allowedDifficulties, true)) {
        $errors[] = 'La dificultad seleccionada no es valida.';
    }
    if (!in_array($activityType, $allowedActivities, true)) {
        $errors[] = 'El tipo de actividad no es valido.';
    }
    if ($distanceKm <= 0 || $distanceKm > 200) {
        $errors[] = 'La distancia debe estar entre 0.1 y 200 km.';
    }
    if ($elevationM < 0 || $elevationM > 5000) {
        $errors[] = 'El desnivel debe estar entre 0 y 5000 m.';
    }
    if (mb_strlen($description) < 30 || mb_strlen($description) > 3000) {
        $errors[] = 'La descripcion debe tener entre 30 y 3000 caracteres.';
    }
    if ($coverImage !== '' && !filter_var($coverImage, FILTER_VALIDATE_URL)) {
        $errors[] = 'La URL de portada no es valida.';
    }

    try {
        $routePoints = route_points_from_form_input($coordsRaw, $_FILES['track_file'] ?? null);
        $coordsJson = (string) json_encode($routePoints, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }

    $duplicateStmt = $pdo->prepare('
        SELECT id, submission_status
        FROM routes
        WHERE LOWER(name) = LOWER(:name)
          AND LOWER(zone) = LOWER(:zone)
          AND id <> :current_id
        LIMIT 1
    ');
    $duplicateStmt->execute([
        ':name' => $name,
        ':zone' => $zone,
        ':current_id' => $routeId,
    ]);
    $duplicate = $duplicateStmt->fetch();
    if ($duplicate) {
        $statusText = (string) $duplicate['submission_status'];
        if ($statusText === 'approved') {
            $errors[] = 'Ya existe una ruta aprobada con ese nombre en esa zona.';
        } elseif ($statusText === 'pending') {
            $errors[] = 'Ya hay una propuesta pendiente con ese nombre en esa zona.';
        } else {
            $errors[] = 'Ya existe una propuesta similar revisada previamente. Ajusta nombre o zona.';
        }
    }

    if (!empty($errors)) {
        set_flash('danger', implode(' ', $errors));
        $redirectPath = $routeId > 0 ? 'submit_route.php?edit_id=' . $routeId : 'submit_route.php';
        redirect($redirectPath);
    }

    if ($routeId > 0) {
        $update = $pdo->prepare('
            UPDATE routes
            SET name = :name,
                zone = :zone,
                description = :description,
                distance_km = :distance_km,
                elevation_m = :elevation_m,
                difficulty = :difficulty,
                activity_type = :activity_type,
                base_points = :base_points,
                cover_image = :cover_image,
                coordinates_json = :coordinates_json,
                updated_at = NOW()
            WHERE id = :id
              AND created_by = :user_id
              AND submission_status = "pending"
              AND is_preloaded = 0
        ');
        $update->execute([
            ':name' => $name,
            ':zone' => $zone,
            ':description' => $description,
            ':distance_km' => $distanceKm,
            ':elevation_m' => $elevationM,
            ':difficulty' => $difficulty,
            ':activity_type' => $activityType,
            ':base_points' => difficulty_default_points($difficulty),
            ':cover_image' => $coverImage !== '' ? $coverImage : null,
            ':coordinates_json' => $coordsJson,
            ':id' => $routeId,
            ':user_id' => (int) $user['id'],
        ]);

        if ($update->rowCount() === 0) {
            set_flash('warning', 'No se pudo actualizar la propuesta porque ya fue revisada.');
        } else {
            notify_admins(
                $pdo,
                'route_submission_updated',
                'Propuesta de ruta actualizada',
                $user['name'] . ' ha actualizado la ruta propuesta "' . $name . '".',
                'admin.php?tab=routes'
            );
            set_flash('success', 'Propuesta actualizada correctamente.');
        }

        redirect('submit_route.php');
    }

    $insert = $pdo->prepare('
        INSERT INTO routes
            (name, zone, description, distance_km, elevation_m, difficulty, activity_type, base_points, cover_image, coordinates_json, is_preloaded, submission_status, created_by, created_at)
        VALUES
            (:name, :zone, :description, :distance_km, :elevation_m, :difficulty, :activity_type, :base_points, :cover_image, :coordinates_json, 0, "pending", :created_by, NOW())
    ');
    $insert->execute([
        ':name' => $name,
        ':zone' => $zone,
        ':description' => $description,
        ':distance_km' => $distanceKm,
        ':elevation_m' => $elevationM,
        ':difficulty' => $difficulty,
        ':activity_type' => $activityType,
        ':base_points' => difficulty_default_points($difficulty),
        ':cover_image' => $coverImage !== '' ? $coverImage : null,
        ':coordinates_json' => $coordsJson,
        ':created_by' => (int) $user['id'],
    ]);

    notify_admins(
        $pdo,
        'route_submission_new',
        'Nueva ruta propuesta',
        $user['name'] . ' ha propuesto la ruta "' . $name . '" para revision.',
        'admin.php?tab=routes'
    );

    set_flash('success', 'Ruta enviada correctamente. Queda pendiente de revision por el equipo administrador.');
    redirect('submit_route.php');
}

$mySubmissionsStmt = $pdo->prepare('
    SELECT id, name, zone, difficulty, activity_type, distance_km, submission_status, review_note, created_at, reviewed_at
    FROM routes
    WHERE created_by = :user_id AND is_preloaded = 0
    ORDER BY created_at DESC
    LIMIT 30
');
$mySubmissionsStmt->execute([':user_id' => (int) $user['id']]);
$mySubmissions = $mySubmissionsStmt->fetchAll();

$defaultCoords = '[{"lat":43.3614,"lng":-5.8494},{"lat":43.3697,"lng":-5.8429}]';
$form = [
    'id' => 0,
    'name' => '',
    'zone' => '',
    'difficulty' => 'Media',
    'activity_type' => 'Senderismo',
    'distance_km' => '',
    'elevation_m' => '',
    'cover_image' => '',
    'description' => '',
    'coordinates_json' => $defaultCoords,
];
if ($editSubmission) {
    $form = [
        'id' => (int) $editSubmission['id'],
        'name' => (string) $editSubmission['name'],
        'zone' => (string) $editSubmission['zone'],
        'difficulty' => (string) $editSubmission['difficulty'],
        'activity_type' => (string) $editSubmission['activity_type'],
        'distance_km' => (string) $editSubmission['distance_km'],
        'elevation_m' => (string) $editSubmission['elevation_m'],
        'cover_image' => (string) ($editSubmission['cover_image'] ?? ''),
        'description' => (string) $editSubmission['description'],
        'coordinates_json' => (string) $editSubmission['coordinates_json'],
    ];
}

render_header('Proponer ruta', [
    'description' => 'Formulario premium para proponer nuevas rutas al equipo de revision.',
    'canonical' => 'submit_route.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 900px; margin-inline: auto;">
    <h1 class="section-title"><?= $editSubmission ? 'Editar propuesta de ruta' : 'Proponer nueva ruta' ?></h1>
    <p class="meta">Esta ventaja premium te permite enviar rutas nuevas al equipo. Revisaremos el trazado, la informacion y la foto antes de publicarlas para toda la comunidad.</p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_submission">
        <input type="hidden" name="route_id" value="<?= (int) $form['id'] ?>">
        <div class="form-grid" style="margin-bottom: 10px;">
            <div>
                <label for="name">Nombre de la ruta</label>
                <input id="name" name="name" maxlength="150" required value="<?= e((string) $form['name']) ?>">
            </div>
            <div>
                <label for="zone">Zona / concejo</label>
                <input id="zone" name="zone" maxlength="100" required value="<?= e((string) $form['zone']) ?>">
            </div>
            <div>
                <label for="difficulty">Dificultad</label>
                <select id="difficulty" name="difficulty" required>
                    <?php foreach ($allowedDifficulties as $option): ?>
                        <option value="<?= e($option) ?>" <?= (string) $form['difficulty'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="activity_type">Actividad</label>
                <select id="activity_type" name="activity_type" required>
                    <?php foreach ($allowedActivities as $option): ?>
                        <option value="<?= e($option) ?>" <?= (string) $form['activity_type'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="distance_km">Distancia (km)</label>
                <input id="distance_km" name="distance_km" type="number" step="0.1" min="0.1" max="200" required value="<?= e((string) $form['distance_km']) ?>">
            </div>
            <div>
                <label for="elevation_m">Desnivel (m)</label>
                <input id="elevation_m" name="elevation_m" type="number" min="0" max="5000" required value="<?= e((string) $form['elevation_m']) ?>">
            </div>
            <div>
                <label for="cover_image">URL de foto (opcional)</label>
                <input id="cover_image" name="cover_image" maxlength="255" placeholder="https://..." value="<?= e((string) $form['cover_image']) ?>">
            </div>
        </div>
        <div style="margin-bottom: 10px;">
            <label for="description">Descripcion</label>
            <textarea id="description" name="description" minlength="30" maxlength="3000" required placeholder="Detalles de acceso, puntos destacados, recomendaciones y precauciones."><?= e((string) $form['description']) ?></textarea>
        </div>
        <div style="margin-bottom: 10px;">
            <label for="coordinates_json">Coordenadas JSON</label>
            <textarea id="coordinates_json" name="coordinates_json" placeholder='[{"lat":43.3614,"lng":-5.8494},{"lat":43.3697,"lng":-5.8429}]'><?= e((string) $form['coordinates_json']) ?></textarea>
            <small class="muted">Puedes pegar el JSON manual o subir debajo un GPX/KML. Si subes archivo, se usara ese trazado real.</small>
        </div>
        <div style="margin-bottom: 10px;">
            <label for="track_file">Track GPX/KML (opcional)</label>
            <input id="track_file" name="track_file" type="file" accept=".gpx,.kml,application/gpx+xml,application/vnd.google-earth.kml+xml,application/xml,text/xml">
            <small class="muted">Ideal para que el mapa de la ruta muestre el recorrido real con mas precision.</small>
        </div>
        <div class="stack">
            <button type="submit"><?= $editSubmission ? 'Actualizar propuesta' : 'Enviar propuesta' ?></button>
            <?php if ($editSubmission): ?>
                <a class="button secondary" href="<?= e(url('submit_route.php')) ?>">Cancelar edicion</a>
            <?php else: ?>
                <a class="button secondary" href="<?= e(url('search.php')) ?>">Volver a busqueda</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card" style="margin-top: 14px;">
    <h2 class="section-title">Mis propuestas recientes</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Ruta</th>
                    <th>Zona</th>
                    <th>Dificultad</th>
                    <th>Actividad</th>
                    <th>Distancia</th>
                    <th>Estado</th>
                    <th>Revision</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mySubmissions)): ?>
                    <tr><td colspan="9">Todavia no has enviado rutas.</td></tr>
                <?php endif; ?>
                <?php foreach ($mySubmissions as $row): ?>
                    <tr>
                        <td><?= e((string) $row['created_at']) ?></td>
                        <td><a href="<?= e(url('route.php?id=' . (int) $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                        <td><?= e((string) $row['zone']) ?></td>
                        <td><?= e((string) $row['difficulty']) ?></td>
                        <td><?= e((string) $row['activity_type']) ?></td>
                        <td><?= number_format((float) $row['distance_km'], 1) ?> km</td>
                        <td><?= e((string) $row['submission_status']) ?></td>
                        <td><?= !empty($row['review_note']) ? e((string) $row['review_note']) : '-' ?></td>
                        <td>
                            <?php if ((string) $row['submission_status'] === 'pending'): ?>
                                <a class="button secondary button-small" href="<?= e(url('submit_route.php?edit_id=' . (int) $row['id'])) ?>">Editar</a>
                                <form method="post" class="inline" onsubmit="return confirm('Retirar esta propuesta?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="withdraw_submission">
                                    <input type="hidden" name="route_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="button danger button-small">Retirar</button>
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
