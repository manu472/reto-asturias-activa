<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/premium_billing.php';

$user = require_login();
$pdo = db();

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    set_flash('warning', 'No se recibio una sesion de pago valida.');
    redirect('premium.php');
}
if (!premium_stripe_enabled()) {
    set_flash('danger', 'Stripe no esta configurado correctamente.');
    redirect('premium.php');
}

$session = null;
$processedNow = false;
$errorMessage = '';

try {
    $session = premium_stripe_api_request(
        'GET',
        'checkout/sessions/' . rawurlencode($sessionId),
        ['expand[]' => 'payment_intent']
    );

    $sessionUserId = (int) ($session['metadata']['user_id'] ?? 0);
    if ($sessionUserId !== (int) $user['id']) {
        throw new RuntimeException('La sesion de pago no corresponde a tu cuenta.');
    }
    if ((string) ($session['payment_status'] ?? '') !== 'paid') {
        throw new RuntimeException('El pago aun no aparece como confirmado.');
    }

    $paymentIntent = '';
    if (isset($session['payment_intent']['id'])) {
        $paymentIntent = (string) $session['payment_intent']['id'];
    } elseif (isset($session['payment_intent'])) {
        $paymentIntent = (string) $session['payment_intent'];
    }
    $amountTotal = ((int) ($session['amount_total'] ?? 0)) / 100;
    $currency = (string) ($session['currency'] ?? 'eur');

    $pdo->beginTransaction();
    $processedNow = premium_register_paid_session(
        $pdo,
        (int) $user['id'],
        $sessionId,
        $paymentIntent !== '' ? $paymentIntent : null,
        $amountTotal > 0 ? $amountTotal : premium_checkout_price_eur(),
        $currency,
        (string) json_encode($session, JSON_UNESCAPED_UNICODE)
    );
    if ($processedNow) {
        premium_grant_month($pdo, (int) $user['id'], premium_checkout_price_eur());
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = $e->getMessage();
}

if ($errorMessage !== '') {
    set_flash('danger', 'No se pudo confirmar el pago: ' . $errorMessage);
    redirect('premium.php');
}

render_header('Pago premium confirmado', [
    'description' => 'Confirmacion privada del pago premium.',
    'canonical' => 'premium_success.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 760px; margin-inline: auto;">
    <h1 class="section-title"><?= $processedNow ? 'Pago confirmado' : 'Pago ya registrado' ?></h1>
    <p class="meta">
        <?= $processedNow
            ? 'Hemos activado tu premium correctamente y ya puedes usar sus ventajas.'
            : 'Este pago ya estaba registrado previamente en tu cuenta.' ?>
    </p>
    <div class="stats" style="margin-top: 10px;">
        <div class="stat"><small>Sesion Stripe</small><strong><?= e($sessionId) ?></strong></div>
        <div class="stat"><small>Importe</small><strong><?= number_format(((int) ($session['amount_total'] ?? 0)) / 100, 2) ?> EUR</strong></div>
        <div class="stat"><small>Estado</small><strong><?= e((string) ($session['payment_status'] ?? 'unknown')) ?></strong></div>
    </div>
    <div class="stack" style="margin-top: 12px;">
        <a class="button" href="<?= e(url('premium.php')) ?>">Volver a premium</a>
        <a class="button secondary" href="<?= e(url('dashboard.php')) ?>">Ir a mi panel</a>
    </div>
</section>
<?php render_footer(); ?>
