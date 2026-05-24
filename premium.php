<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/premium_billing.php';

$user = require_login();
$pdo = db();

$refreshUser = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$refreshUser->execute([':id' => (int) $user['id']]);
$user = $refreshUser->fetch() ?: $user;

$monthlyPrice = premium_checkout_price_eur();
$isPremium = user_has_active_premium($user);
$checkoutProvider = premium_checkout_provider();

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad invalido.');
        redirect('premium.php');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel_premium') {
        if (!$isPremium) {
            set_flash('warning', 'No tienes un plan premium activo.');
            redirect('premium.php');
        }

        try {
            $pdo->beginTransaction();

            $cancelUser = $pdo->prepare('
                UPDATE users
                SET premium_auto_renew = 0,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $cancelUser->execute([':id' => (int) $user['id']]);

            $cancelSubscription = $pdo->prepare('
                UPDATE premium_subscriptions
                SET status = "canceled",
                    canceled_at = COALESCE(canceled_at, NOW()),
                    updated_at = NOW()
                WHERE user_id = :user_id
                  AND status = "active"
                ORDER BY id DESC
                LIMIT 1
            ');
            $cancelSubscription->execute([':user_id' => (int) $user['id']]);

            create_notification(
                $pdo,
                (int) $user['id'],
                'premium_canceled',
                'Renovacion premium desactivada',
                'Tu premium seguira activo hasta la fecha de fin del periodo actual.',
                'premium.php'
            );

            $pdo->commit();
            set_flash('success', 'Renovacion automatica desactivada. Mantienes premium hasta fin de periodo.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'No se pudo cancelar premium: ' . $e->getMessage());
        }

        redirect('premium.php');
    }
}

$refreshUser->execute([':id' => (int) $user['id']]);
$user = $refreshUser->fetch() ?: $user;
$isPremium = user_has_active_premium($user);
$checkoutProvider = premium_checkout_provider();

$subscriptionsStmt = $pdo->prepare('
    SELECT plan_type, status, price_month, started_at, ends_at, canceled_at, created_at
    FROM premium_subscriptions
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 10
');
$subscriptionsStmt->execute([':user_id' => (int) $user['id']]);
$subscriptions = $subscriptionsStmt->fetchAll();

$paymentsStmt = $pdo->prepare('
    SELECT amount_eur, currency, status, paid_at, created_at
    FROM premium_payments
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 10
');
$paymentsStmt->execute([':user_id' => (int) $user['id']]);
$payments = $paymentsStmt->fetchAll();

$expiresAt = (string) ($user['premium_expires_at'] ?? '');
$daysLeft = null;
if ($expiresAt !== '') {
    $expiresTs = strtotime($expiresAt);
    if ($expiresTs !== false) {
        $daysLeft = max(0, (int) ceil(($expiresTs - time()) / 86400));
    }
}
$premiumInsights = user_premium_insights($pdo, (int) $user['id'], 6);

render_header('Premium', [
    'description' => 'Activa Premium para proponer rutas, descargar formatos avanzados y planificar tus salidas con mas detalle.',
    'canonical' => 'premium.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card premium-hero premium-glow">
    <span class="card-kicker premium-kicker">Premium</span>
    <h1 class="section-title">Saca mas partido a cada ruta</h1>
    <p class="meta premium-copy">Por <?= number_format($monthlyPrice, 2) ?> EUR al mes desbloqueas herramientas pensadas para preparar mejor tus salidas: proponer rutas nuevas, llevar formatos offline, recibir estimaciones personales y avanzar mas rapido en retos.</p>
    <div class="stats premium-metrics">
        <div class="stat"><small>Estado</small><strong><?= $isPremium ? 'Premium activo' : 'Plan gratuito' ?></strong></div>
        <div class="stat"><small>Cuota mensual</small><strong><?= number_format($monthlyPrice, 2) ?> EUR</strong></div>
        <div class="stat"><small>Bonus por ruta</small><strong>+<?= premium_points_bonus_percent() ?>%</strong></div>
        <div class="stat"><small>Proponer rutas</small><strong>Incluido</strong></div>
    </div>

    <div class="stack premium-actions" style="margin-top: 12px;">
        <?php if ($checkoutProvider === 'demo'): ?>
            <a class="button" href="<?= e(url('premium_checkout.php')) ?>"><?= $isPremium ? 'Renovar Premium un mes mas' : 'Activar Premium' ?></a>
            <p class="meta premium-note">Modo local de pruebas activo: puedes comprobar el flujo de pago sin salir de XAMPP. Al conectar Stripe, este boton usara la pasarela real.</p>
        <?php elseif ($checkoutProvider === 'stripe'): ?>
            <form method="post" action="<?= e(url('create_checkout_session.php')) ?>" class="inline">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit"><?= $isPremium ? 'Renovar Premium un mes mas' : 'Activar Premium' ?></button>
            </form>
            <p class="meta premium-note">Pago seguro con Stripe Checkout. La tarjeta se introduce en Stripe, no en este servidor.</p>
        <?php else: ?>
            <button type="button" disabled><?= $isPremium ? 'Renovar Premium un mes mas' : 'Activar Premium' ?></button>
            <p class="meta premium-note">Falta configurar un metodo de pago en `config.php`. Usa el modo `demo` en local o completa las claves de Stripe para activar cobros reales.</p>
        <?php endif; ?>
        <?php if ($isPremium): ?>
            <p class="meta premium-note">Tu Premium esta activo<?= $daysLeft !== null ? ' (' . (int) $daysLeft . ' dias restantes aprox.)' : '' ?>. Si renuevas ahora, el nuevo mes se suma al final del periodo actual.</p>
            <?php if ((int) ($user['premium_auto_renew'] ?? 0) === 1): ?>
                <form method="post" class="inline" onsubmit="return confirm('Quieres desactivar la renovacion automatica?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cancel_premium">
                    <button type="submit" class="button secondary">Desactivar renovacion</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 14px;" id="premium-benefits">
    <article class="card premium-surface">
        <span class="card-kicker premium-kicker">Ventajas</span>
        <h2 class="section-title">Que incluye Premium</h2>
        <ul>
            <li>Proponer rutas nuevas para que el equipo las revise y pueda publicarlas.</li>
            <li>+<?= premium_points_bonus_percent() ?>% de puntos en cada ruta completada.</li>
            <li>+<?= premium_challenge_bonus_percent() ?>% de progreso en retos activos.</li>
            <li>Descargas offline avanzadas: KML, GeoJSON y roadbook listo para guardar como PDF.</li>
            <li>Estimacion personal de tiempo y esfuerzo segun tu historial real.</li>
            <li>Plan semanal con una ruta base, una de progresion y otra de reto.</li>
            <li>Panel con analitica ampliada, exportacion JSON y resumen por zonas.</li>
        </ul>
        <p class="meta"><?= $checkoutProvider === 'stripe' ? 'El pago se completa con Stripe Checkout y puedes seguir usando la web al volver.' : 'En local puedes probar Premium con el modo demo. Cuando conectes Stripe, el flujo pasara a cobro real sin rehacer esta pantalla.' ?></p>
    </article>

    <article class="card premium-surface">
        <span class="card-kicker premium-kicker">Sostenibilidad</span>
        <h2 class="section-title">Por que existe Premium</h2>
        <p class="meta">Premium ayuda a mantener una plataforma mas cuidada: mejores rutas, revision de propuestas, herramientas offline y mas datos utiles para quien sale con frecuencia.</p>
        <div class="stats">
            <div class="stat"><small>Precio claro</small><strong><?= number_format($monthlyPrice, 2) ?> EUR/mes</strong></div>
            <div class="stat"><small>Propuestas</small><strong>Revisadas</strong></div>
            <div class="stat"><small>Herramientas</small><strong>Offline + plan</strong></div>
            <div class="stat"><small>Progreso</small><strong>Mas rapido</strong></div>
        </div>
    </article>
</section>

<section class="card premium-surface" style="margin-top: 14px;">
    <span class="card-kicker premium-kicker">Comparativa</span>
    <h2 class="section-title">Gratis vs Premium</h2>
    <p class="meta">El plan gratuito sirve para descubrir rutas y participar. Premium esta pensado para quien quiere preparar mejor sus salidas y aportar nuevas rutas a la comunidad.</p>
    <div class="table-wrap">
        <table class="premium-comparison-table">
            <thead>
                <tr>
                    <th>Funcion</th>
                    <th>Gratis</th>
                    <th>Premium</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Proponer rutas</td>
                    <td>No disponible</td>
                    <td>Envio de propuestas para revision</td>
                </tr>
                <tr>
                    <td>Descarga de ruta</td>
                    <td>GPX basico</td>
                    <td>GPX + KML + GeoJSON + Roadbook premium</td>
                </tr>
                <tr>
                    <td>Planificacion</td>
                    <td>Manual</td>
                    <td>Plan semanal con base, progresion y reto</td>
                </tr>
                <tr>
                    <td>Lectura de la ruta</td>
                    <td>Ficha general</td>
                    <td>Tiempo personal y encaje segun tu historial</td>
                </tr>
                <tr>
                    <td>Progreso en gamificacion</td>
                    <td>Normal</td>
                    <td>+<?= premium_points_bonus_percent() ?>% puntos y +<?= premium_challenge_bonus_percent() ?>% en retos</td>
                </tr>
                <tr>
                    <td>Analitica</td>
                    <td>Basica</td>
                    <td>Avanzada + exportacion JSON</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php if ($isPremium): ?>
    <section class="card premium-surface" style="margin-top: 14px;">
        <div class="section-heading">
            <div>
                <span class="card-kicker premium-kicker">Desbloqueado</span>
                <h2 class="section-title" style="margin-bottom: 4px;">Centro premium</h2>
                <p class="meta" style="margin-top: 0;">Valor real desbloqueado en los ultimos <?= e((string) $premiumInsights['window_label']) ?>.</p>
            </div>
            <a class="button secondary button-small" href="<?= e(url('export_history.php?format=json')) ?>">Exportar JSON premium</a>
        </div>
        <div class="stats">
            <div class="stat"><small>Km acumulados</small><strong><?= number_format((float) $premiumInsights['km_window'], 1) ?> km</strong></div>
            <div class="stat"><small>Desnivel acumulado</small><strong><?= number_format((float) $premiumInsights['elevation_window'], 0) ?> m</strong></div>
            <div class="stat"><small>Duracion media</small><strong><?= format_minutes_human((int) $premiumInsights['avg_duration']) ?></strong></div>
            <div class="stat"><small>Puntos medios</small><strong><?= (int) $premiumInsights['avg_points'] ?></strong></div>
            <div class="stat"><small>Zona top</small><strong><?= $premiumInsights['top_zone'] !== '' ? e((string) $premiumInsights['top_zone']) : 'Sin datos' ?></strong></div>
            <div class="stat"><small>Actividad top</small><strong><?= $premiumInsights['top_activity'] !== '' ? e((string) $premiumInsights['top_activity']) : 'Sin datos' ?></strong></div>
        </div>
        <div class="premium-trend-grid" style="margin-top: 14px;">
            <?php foreach ($premiumInsights['series'] as $month): ?>
                <?php $routesPercent = (int) round(((int) $month['routes'] / max(1, (int) $premiumInsights['series_max_routes'])) * 100); ?>
                <div class="premium-bar">
                    <div class="premium-bar-track">
                        <div class="premium-bar-fill" style="height: <?= max(8, $routesPercent) ?>%;"></div>
                    </div>
                    <strong><?= e((string) $month['month_label']) ?></strong>
                    <small><?= (int) $month['routes'] ?> rutas</small>
                    <small><?= number_format((float) $month['km'], 1) ?> km</small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="card card-soft premium-surface" style="margin-top: 14px;">
        <span class="card-kicker premium-kicker">Preview</span>
        <h2 class="section-title">Lo que desbloqueas al activar Premium</h2>
        <div class="stats">
            <div class="stat"><small>Proponer rutas</small><strong>Incluido</strong></div>
            <div class="stat"><small>Roadbook premium</small><strong>Incluido</strong></div>
            <div class="stat"><small>Plan semanal</small><strong>3 rutas guiadas</strong></div>
            <div class="stat"><small>Bonus por ruta</small><strong>+<?= premium_points_bonus_percent() ?>%</strong></div>
        </div>
        <p class="meta" style="margin-top: 12px;">Activalo si quieres enviar rutas propias, llevar la informacion offline y tener una lectura mas personal de cada salida.</p>
    </section>
<?php endif; ?>

<section class="card premium-surface premium-table-card" style="margin-top: 14px;">
    <h2 class="section-title">Historial de suscripciones</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Estado</th>
                    <th>Precio</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Cancelado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptions)): ?>
                    <tr><td colspan="6">Aun no hay suscripciones registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($subscriptions as $row): ?>
                    <tr>
                        <td><?= e((string) $row['plan_type']) ?></td>
                        <td><?= e((string) $row['status']) ?></td>
                        <td><?= number_format((float) $row['price_month'], 2) ?> EUR</td>
                        <td><?= e((string) $row['started_at']) ?></td>
                        <td><?= e((string) $row['ends_at']) ?></td>
                        <td><?= !empty($row['canceled_at']) ? e((string) $row['canceled_at']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card premium-surface premium-table-card" style="margin-top: 14px;">
    <h2 class="section-title">Pagos registrados</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Importe</th>
                    <th>Moneda</th>
                    <th>Pagado</th>
                    <th>Creado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="5">Todavia no hay pagos registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $row): ?>
                    <tr>
                        <td><?= e((string) $row['status']) ?></td>
                        <td><?= number_format((float) $row['amount_eur'], 2) ?></td>
                        <td><?= e((string) $row['currency']) ?></td>
                        <td><?= !empty($row['paid_at']) ? e((string) $row['paid_at']) : '-' ?></td>
                        <td><?= e((string) $row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
