<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/premium_billing.php';

$user = require_login();
$pdo = db();
$provider = premium_checkout_provider();

if (!is_post()) {
    set_flash('warning', 'Solicitud invalida.');
    redirect('premium.php');
}
if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    set_flash('danger', 'Token de seguridad invalido.');
    redirect('premium.php');
}

if ($provider === 'demo') {
    redirect('premium_checkout.php');
}
if ($provider !== 'stripe') {
    set_flash('danger', 'No hay un metodo de cobro configurado. Revisa config.php.');
    redirect('premium.php');
}

$priceEur = premium_checkout_price_eur();
$amountCents = (int) round($priceEur * 100);
$isRenewal = user_has_active_premium($user);
$productName = $isRenewal
    ? 'Reto Asturias Activa - Renovacion premium mensual'
    : 'Reto Asturias Activa - Premium mensual';
$productDescription = $isRenewal
    ? 'Pago para ampliar 1 mes la suscripcion premium actual'
    : 'Suscripcion premium mensual de la plataforma';

try {
    $session = premium_stripe_api_request('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'success_url' => absolute_url('premium_success.php?session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => absolute_url('premium.php?checkout=cancel'),
        'line_items[0][price_data][currency]' => 'eur',
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDescription,
        'line_items[0][price_data][unit_amount]' => $amountCents,
        'line_items[0][quantity]' => 1,
        'metadata[user_id]' => (string) ((int) $user['id']),
        'metadata[purpose]' => $isRenewal ? 'premium_renewal' : 'premium_monthly',
        'client_reference_id' => (string) ((int) $user['id']),
        'customer_email' => (string) ($user['email'] ?? ''),
    ]);
} catch (Throwable $e) {
    set_flash('danger', 'No se pudo iniciar el pago: ' . $e->getMessage());
    redirect('premium.php');
}

$sessionId = (string) ($session['id'] ?? '');
$checkoutUrl = (string) ($session['url'] ?? '');
if ($sessionId === '' || $checkoutUrl === '') {
    set_flash('danger', 'Stripe no devolvio una sesion valida.');
    redirect('premium.php');
}

try {
    $insertPending = $pdo->prepare('
        INSERT INTO premium_payments
            (user_id, stripe_checkout_session_id, stripe_payment_intent_id, amount_eur, currency, status, created_at)
        VALUES
            (:user_id, :session_id, :payment_intent, :amount_eur, "eur", "pending", NOW())
    ');
    $insertPending->execute([
        ':user_id' => (int) $user['id'],
        ':session_id' => $sessionId,
        ':payment_intent' => (string) ($session['payment_intent'] ?? ''),
        ':amount_eur' => $priceEur,
    ]);
} catch (Throwable) {
    // Si ya existe el registro pendiente o hubo una carrera, continuamos.
}

header('Location: ' . $checkoutUrl, true, 303);
exit;
