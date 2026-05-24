<?php
declare(strict_types=1);

function premium_stripe_secret_key(): string
{
    return trim((string) config_value('stripe_secret_key', getenv('STRIPE_SECRET_KEY') ?: ''));
}

function premium_stripe_publishable_key(): string
{
    return trim((string) config_value('stripe_publishable_key', getenv('STRIPE_PUBLISHABLE_KEY') ?: ''));
}

function premium_stripe_webhook_secret(): string
{
    return trim((string) config_value('stripe_webhook_secret', getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
}

function premium_checkout_price_eur(): float
{
    $value = (float) config_value('premium_price_eur', 4.00);
    return $value > 0 ? round($value, 2) : 4.00;
}

function premium_stripe_enabled(): bool
{
    return premium_stripe_secret_key() !== '' && premium_stripe_publishable_key() !== '';
}

function premium_checkout_provider(): string
{
    if (premium_stripe_enabled()) {
        return 'stripe';
    }

    $mode = mb_strtolower(trim((string) config_value('payment_mode', 'demo')));
    return $mode === 'demo' ? 'demo' : 'none';
}

function premium_checkout_available(): bool
{
    return premium_checkout_provider() !== 'none';
}

function premium_is_valid_card_number(string $number): bool
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';
    if ($digits === '' || strlen($digits) < 13 || strlen($digits) > 19) {
        return false;
    }

    $sum = 0;
    $alternate = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $digit = (int) $digits[$i];
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $alternate = !$alternate;
    }

    return $sum % 10 === 0;
}

function premium_card_brand(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';
    return match (true) {
        preg_match('/^4\d{12}(\d{3})?(\d{3})?$/', $digits) === 1 => 'visa',
        preg_match('/^(5[1-5]\d{14}|2(2[2-9]|[3-6]\d|7[01])\d{12}|2720\d{12})$/', $digits) === 1 => 'mastercard',
        preg_match('/^3[47]\d{13}$/', $digits) === 1 => 'amex',
        default => 'card',
    };
}

function premium_mask_card_number(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';
    $last4 = substr($digits, -4);
    return $last4 !== '' ? '**** **** **** ' . $last4 : '****';
}

/**
 * @return array<string,mixed>
 */
function premium_stripe_api_request(string $method, string $path, array $params = []): array
{
    $secretKey = premium_stripe_secret_key();
    if ($secretKey === '') {
        throw new RuntimeException('Stripe no esta configurado en el servidor.');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('No se pudo inicializar cURL para Stripe.');
    }

    $upperMethod = mb_strtoupper($method);
    $headers = [
        'Authorization: Bearer ' . $secretKey,
    ];
    if ($upperMethod === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $upperMethod,
    ]);

    if ($upperMethod === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } elseif (!empty($params)) {
        $url .= '?' . http_build_query($params);
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response)) {
        throw new RuntimeException('Stripe no respondio correctamente: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta no valida de Stripe.');
    }

    if ($status < 200 || $status >= 300) {
        $message = (string) ($decoded['error']['message'] ?? 'Error desconocido al contactar Stripe.');
        throw new RuntimeException('Stripe error: ' . $message);
    }

    return $decoded;
}

/**
 * Registra un pago Stripe y devuelve true solo si se procesa por primera vez.
 */
function premium_register_paid_session(PDO $pdo, int $userId, string $sessionId, ?string $paymentIntentId, float $amountEur, string $currency, string $rawPayload): bool
{
    $check = $pdo->prepare('
        SELECT id, status
        FROM premium_payments
        WHERE stripe_checkout_session_id = :session_id
        LIMIT 1
        FOR UPDATE
    ');
    $check->execute([':session_id' => $sessionId]);
    $existing = $check->fetch();
    if ($existing && (string) $existing['status'] === 'paid') {
        return false;
    }

    if ($existing) {
        $update = $pdo->prepare('
            UPDATE premium_payments
            SET user_id = :user_id,
                stripe_payment_intent_id = :payment_intent,
                amount_eur = :amount_eur,
                currency = :currency,
                status = "paid",
                paid_at = NOW(),
                raw_payload = :raw_payload,
                updated_at = NOW()
            WHERE id = :id
        ');
        $update->execute([
            ':user_id' => $userId,
            ':payment_intent' => $paymentIntentId,
            ':amount_eur' => $amountEur,
            ':currency' => mb_strtolower($currency),
            ':raw_payload' => $rawPayload,
            ':id' => (int) $existing['id'],
        ]);
        return true;
    }

    $insert = $pdo->prepare('
        INSERT INTO premium_payments
            (user_id, stripe_checkout_session_id, stripe_payment_intent_id, amount_eur, currency, status, paid_at, raw_payload, created_at)
        VALUES
            (:user_id, :session_id, :payment_intent, :amount_eur, :currency, "paid", NOW(), :raw_payload, NOW())
    ');
    $insert->execute([
        ':user_id' => $userId,
        ':session_id' => $sessionId,
        ':payment_intent' => $paymentIntentId,
        ':amount_eur' => $amountEur,
        ':currency' => mb_strtolower($currency),
        ':raw_payload' => $rawPayload,
    ]);
    return true;
}

function premium_grant_month(PDO $pdo, int $userId, float $priceMonth): void
{
    $userStmt = $pdo->prepare('
        SELECT id, premium_started_at, premium_expires_at
        FROM users
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ');
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        throw new RuntimeException('No se encontro el usuario para aplicar premium.');
    }

    $now = new DateTimeImmutable('now');
    $currentExpiry = null;
    if (!empty($user['premium_expires_at'])) {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $user['premium_expires_at']);
        if ($parsed instanceof DateTimeImmutable) {
            $currentExpiry = $parsed;
        } else {
            $currentExpiry = new DateTimeImmutable((string) $user['premium_expires_at']);
        }
    }

    $periodStart = ($currentExpiry instanceof DateTimeImmutable && $currentExpiry > $now) ? $currentExpiry : $now;
    $periodEnd = $periodStart->modify('+1 month');

    $updateUser = $pdo->prepare('
        UPDATE users
        SET is_premium = 1,
            premium_plan = "monthly",
            premium_price_month = :price_month,
            premium_started_at = COALESCE(premium_started_at, NOW()),
            premium_expires_at = :premium_expires_at,
            premium_auto_renew = 1,
            updated_at = NOW()
        WHERE id = :id
    ');
    $updateUser->execute([
        ':price_month' => $priceMonth,
        ':premium_expires_at' => $periodEnd->format('Y-m-d H:i:s'),
        ':id' => $userId,
    ]);

    $insertSubscription = $pdo->prepare('
        INSERT INTO premium_subscriptions (user_id, plan_type, status, price_month, started_at, ends_at, created_at)
        VALUES (:user_id, "monthly", "active", :price_month, :started_at, :ends_at, NOW())
    ');
    $insertSubscription->execute([
        ':user_id' => $userId,
        ':price_month' => $priceMonth,
        ':started_at' => $periodStart->format('Y-m-d H:i:s'),
        ':ends_at' => $periodEnd->format('Y-m-d H:i:s'),
    ]);

    create_notification(
        $pdo,
        $userId,
        'premium_payment_success',
        'Pago premium confirmado',
        'Tu pago se ha confirmado. Tu premium ya esta activo.',
        'premium.php'
    );
}
