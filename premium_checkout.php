<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/premium_billing.php';

$user = require_login();
$pdo = db();
$provider = premium_checkout_provider();

if ($provider === 'stripe') {
    set_flash('info', 'Tu cuenta ya esta configurada para cobrar con Stripe Checkout.');
    redirect('premium.php');
}
if ($provider !== 'demo') {
    set_flash('danger', 'No hay ningun metodo de pago configurado en este momento.');
    redirect('premium.php');
}

$errors = [];
$values = [
    'cardholder_name' => '',
    'card_number' => '',
    'expiry_month' => '',
    'expiry_year' => '',
    'cvc' => '',
];

if (is_post()) {
    if (!validate_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('danger', 'Token de seguridad invalido.');
        redirect('premium_checkout.php');
    }

    $values['cardholder_name'] = trim((string) ($_POST['cardholder_name'] ?? ''));
    $values['card_number'] = trim((string) ($_POST['card_number'] ?? ''));
    $values['expiry_month'] = trim((string) ($_POST['expiry_month'] ?? ''));
    $values['expiry_year'] = trim((string) ($_POST['expiry_year'] ?? ''));
    $values['cvc'] = trim((string) ($_POST['cvc'] ?? ''));

    $cardNumberDigits = preg_replace('/\D+/', '', $values['card_number']) ?? '';
    $expiryMonth = (int) $values['expiry_month'];
    $expiryYear = (int) $values['expiry_year'];
    $cvcDigits = preg_replace('/\D+/', '', $values['cvc']) ?? '';

    if ($values['cardholder_name'] === '' || mb_strlen($values['cardholder_name']) < 3) {
        $errors[] = 'Introduce el nombre del titular.';
    }
    if (!premium_is_valid_card_number($values['card_number'])) {
        $errors[] = 'El numero de tarjeta no es valido.';
    }
    if ($expiryMonth < 1 || $expiryMonth > 12) {
        $errors[] = 'El mes de caducidad debe estar entre 1 y 12.';
    }
    if ($expiryYear < (int) date('Y') || $expiryYear > ((int) date('Y') + 20)) {
        $errors[] = 'El ano de caducidad no es valido.';
    }
    if (empty($errors)) {
        $expiryCutoff = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', sprintf('%04d-%d-1 00:00:00', $expiryYear, $expiryMonth));
        if (!$expiryCutoff instanceof DateTimeImmutable) {
            $errors[] = 'La fecha de caducidad no es valida.';
        } else {
            $expiryCutoff = $expiryCutoff->modify('last day of this month')->setTime(23, 59, 59);
            if ($expiryCutoff < new DateTimeImmutable('now')) {
                $errors[] = 'La tarjeta esta caducada.';
            }
        }
    }
    if (strlen($cvcDigits) < 3 || strlen($cvcDigits) > 4) {
        $errors[] = 'El CVC debe tener 3 o 4 digitos.';
    }

    if (empty($errors)) {
        $price = premium_checkout_price_eur();
        $sessionId = 'demo_cs_' . bin2hex(random_bytes(8));
        $paymentIntent = 'demo_pi_' . bin2hex(random_bytes(8));
        $payload = [
            'provider' => 'demo',
            'source' => 'local_checkout_form',
            'cardholder_name' => $values['cardholder_name'],
            'card_brand' => premium_card_brand($cardNumberDigits),
            'card_masked' => premium_mask_card_number($cardNumberDigits),
            'captured_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $pdo->beginTransaction();
            premium_register_paid_session(
                $pdo,
                (int) $user['id'],
                $sessionId,
                $paymentIntent,
                $price,
                'eur',
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE)
            );
            premium_grant_month($pdo, (int) $user['id'], $price);
            $pdo->commit();

            set_flash(
                'success',
                'Pago registrado correctamente. Tu premium ya esta activo o renovado por 1 mes.'
            );
            redirect('premium.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'No se pudo registrar el pago: ' . $e->getMessage();
        }
    }
}

render_header('Pago premium', [
    'description' => 'Formulario privado de pago premium.',
    'canonical' => 'premium_checkout.php',
    'robots' => 'noindex,nofollow',
]);
?>
<section class="card" style="max-width: 760px; margin-inline: auto;">
    <h1 class="section-title">Pago premium</h1>
    <p class="meta">Completa el pago con tarjeta para activar o renovar tu premium por <?= number_format(premium_checkout_price_eur(), 2) ?> EUR.</p>
    <p class="meta">Modo local de pruebas activo: este formulario simula el cobro dentro de tu entorno XAMPP. Cuando anadas claves reales de Stripe, la web cobrara con la pasarela real.</p>

    <?php if (!empty($errors)): ?>
        <div class="flash danger">
            <?= e(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="stack" style="margin-top: 16px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="cardholder_name">Titular de la tarjeta</label>
        <input
            id="cardholder_name"
            name="cardholder_name"
            type="text"
            maxlength="120"
            autocomplete="cc-name"
            value="<?= e($values['cardholder_name']) ?>"
            required
        >

        <label for="card_number">Numero de tarjeta</label>
        <input
            id="card_number"
            name="card_number"
            type="text"
            inputmode="numeric"
            maxlength="23"
            autocomplete="cc-number"
            placeholder="4242 4242 4242 4242"
            value="<?= e($values['card_number']) ?>"
            required
        >

        <div class="grid grid-3">
            <div>
                <label for="expiry_month">Mes</label>
                <input
                    id="expiry_month"
                    name="expiry_month"
                    type="number"
                    min="1"
                    max="12"
                    inputmode="numeric"
                    autocomplete="cc-exp-month"
                    placeholder="MM"
                    value="<?= e($values['expiry_month']) ?>"
                    required
                >
            </div>
            <div>
                <label for="expiry_year">Ano</label>
                <input
                    id="expiry_year"
                    name="expiry_year"
                    type="number"
                    min="<?= e((string) date('Y')) ?>"
                    max="<?= e((string) ((int) date('Y') + 20)) ?>"
                    inputmode="numeric"
                    autocomplete="cc-exp-year"
                    placeholder="AAAA"
                    value="<?= e($values['expiry_year']) ?>"
                    required
                >
            </div>
            <div>
                <label for="cvc">CVC</label>
                <input
                    id="cvc"
                    name="cvc"
                    type="password"
                    minlength="3"
                    maxlength="4"
                    inputmode="numeric"
                    autocomplete="cc-csc"
                    placeholder="123"
                    value="<?= e($values['cvc']) ?>"
                    required
                >
            </div>
        </div>

        <div class="stats" style="margin-top: 8px;">
            <div class="stat"><small>Importe</small><strong><?= number_format(premium_checkout_price_eur(), 2) ?> EUR</strong></div>
            <div class="stat"><small>Plan</small><strong>Premium mensual</strong></div>
            <div class="stat"><small>Renovacion</small><strong>+1 mes al pagar</strong></div>
        </div>

        <div class="inline" style="margin-top: 12px;">
            <button type="submit">Confirmar pago y activar premium</button>
            <a class="button secondary" href="<?= e(url('premium.php')) ?>">Cancelar</a>
        </div>
    </form>
</section>
<?php render_footer(); ?>
