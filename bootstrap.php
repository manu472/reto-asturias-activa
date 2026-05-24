<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

const APP_NAME = 'Reto Asturias Activa';
const APP_BASE_URL = '/Proyecto';
const SESSION_TIMEOUT_SECONDS = 1800;

initialize_app_session();
send_security_headers();

$GLOBALS['app_config'] = [];
$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    $loadedConfig = require $configPath;
    if (is_array($loadedConfig)) {
        $GLOBALS['app_config'] = $loadedConfig;
    }
}

if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    initialize_app_session();
    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => 'Tu sesion expiro por inactividad.',
    ];
}
$_SESSION['last_activity'] = time();

function initialize_app_session(): void
{
    if (PHP_SAPI === 'cli') {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function config_value(string $key, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = config_value('db_host', getenv('DB_HOST') ?: '127.0.0.1');
    $port = (string) config_value('db_port', getenv('DB_PORT') ?: '3306');
    $database = config_value('db_name', getenv('DB_NAME') ?: 'reto_asturias_activa');
    $user = config_value('db_user', getenv('DB_USER') ?: 'root');
    $password = config_value('db_pass', getenv('DB_PASS') ?: '');

    $portsToTry = array_values(array_unique(array_filter([$port, '3307', '3306'])));
    $lastException = null;
    foreach ($portsToTry as $candidatePort) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $candidatePort, $database);
        try {
            $pdo = new PDO($dsn, (string) $user, (string) $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
            error_log('DB connection error on port ' . $candidatePort . ': ' . $e->getMessage());
        }
    }

    throw new RuntimeException(
        'No se pudo conectar a MySQL. Revisa host/puerto/usuario en C:\\xampp\\htdocs\\Proyecto\\config.php.',
        0,
        $lastException
    );
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash_message_html(string $message): string
{
    $offset = 0;
    $html = '';
    if (preg_match_all('~https?://[^\s<]+~', $message, $matches, PREG_OFFSET_CAPTURE) !== false) {
        foreach ($matches[0] as $match) {
            $url = rtrim((string) $match[0], '.,;)');
            $start = (int) $match[1];
            $html .= e(substr($message, $offset, $start - $offset));
            $html .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a>';
            $html .= e(substr((string) $match[0], strlen($url)));
            $offset = $start + strlen((string) $match[0]);
        }
    }

    if ($html === '') {
        return e($message);
    }

    return $html . e(substr($message, $offset));
}

function url(string $path = ''): string
{
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . url($path);
}

function email_verification_url(string $token): string
{
    return absolute_url('verify_email.php?token=' . urlencode($token));
}

function password_reset_url(string $token): string
{
    return absolute_url('reset_password.php?token=' . urlencode($token));
}

function email_change_confirmation_url(string $token): string
{
    return absolute_url('confirm_email_change.php?token=' . urlencode($token));
}

function send_plain_email(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = trim((string) config_value('mail_from', 'no-reply@retoasturiasactiva.local'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'no-reply@retoasturiasactiva.local';
    }

    if (trim((string) config_value('smtp_host', '')) !== '') {
        return send_smtp_email($to, $subject, $body, $from, APP_NAME);
    }

    if (PHP_SAPI === 'cli') {
        return false;
    }

    $headers = [
        'From: ' . APP_NAME . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function smtp_read_response($socket): array
{
    $message = '';
    $code = 0;

    while (($line = fgets($socket, 515)) !== false) {
        $message .= $line;
        if (preg_match('/^(\d{3})([\s-])/', $line, $matches) === 1) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        }
    }

    return [$code, trim($message)];
}

function smtp_expect($socket, array $expectedCodes): bool
{
    [$code, $message] = smtp_read_response($socket);
    if (in_array($code, $expectedCodes, true)) {
        return true;
    }

    error_log('SMTP unexpected response: ' . $message);
    return false;
}

function smtp_command($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expectedCodes);
}

function smtp_header_text(string $value): string
{
    return preg_match('/[^\x20-\x7E]/', $value) === 1
        ? '=?UTF-8?B?' . base64_encode($value) . '?='
        : $value;
}

function smtp_dot_stuff(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $normalized);
    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

function send_smtp_email(string $to, string $subject, string $body, string $from, string $fromName = ''): bool
{
    $host = trim((string) config_value('smtp_host', ''));
    $port = (int) config_value('smtp_port', 587);
    $secure = mb_strtolower(trim((string) config_value('smtp_secure', 'tls')));
    $username = trim((string) config_value('smtp_username', $from));
    $password = str_replace(' ', '', (string) config_value('smtp_password', getenv('SMTP_PASSWORD') ?: ''));
    $timeout = (int) config_value('smtp_timeout', 20);
    $verifyPeer = (bool) config_value('smtp_verify_peer', true);

    if ($host === '' || $username === '' || $password === '') {
        error_log('SMTP is missing host, username, or password.');
        return false;
    }

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'peer_name' => $host,
            'SNI_enabled' => true,
            'SNI_server_name' => $host,
            'verify_peer' => $verifyPeer,
            'verify_peer_name' => $verifyPeer,
        ],
    ]);
    $socket = @stream_socket_client($target, $errno, $errstr, max(5, $timeout), STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        error_log('SMTP connection failed: ' . $errno . ' ' . $errstr);
        return false;
    }

    stream_set_timeout($socket, max(5, $timeout));

    try {
        $serverName = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        if (!smtp_expect($socket, [220])) {
            fclose($socket);
            return false;
        }

        if (!smtp_command($socket, 'EHLO ' . $serverName, [250])) {
            fclose($socket);
            return false;
        }

        if (in_array($secure, ['tls', 'starttls'], true)) {
            if (!smtp_command($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                error_log('SMTP STARTTLS negotiation failed.');
                fclose($socket);
                return false;
            }
            if (!smtp_command($socket, 'EHLO ' . $serverName, [250])) {
                fclose($socket);
                return false;
            }
        }

        if (!smtp_command($socket, 'AUTH LOGIN', [334])) {
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, base64_encode($username), [334])) {
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, base64_encode($password), [235])) {
            fclose($socket);
            return false;
        }

        if (!smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250])) {
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251])) {
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $fromHeader = ($fromName !== '' ? smtp_header_text($fromName) . ' ' : '') . '<' . $from . '>';
        $messageIdHost = preg_replace('/[^a-zA-Z0-9.-]/', '', $serverName) ?: 'localhost';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromHeader,
            'Reply-To: ' . $from,
            'To: <' . $to . '>',
            'Subject: ' . smtp_header_text($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $messageIdHost . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . smtp_dot_stuff($body) . "\r\n.\r\n");
        if (!smtp_expect($socket, [250])) {
            fclose($socket);
            return false;
        }

        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());
        if (is_resource($socket)) {
            fclose($socket);
        }
        return false;
    }
}

function send_email_verification_link(array $user, string $token): bool
{
    $email = (string) ($user['email'] ?? '');
    $name = trim((string) ($user['name'] ?? ''));
    $link = email_verification_url($token);
    $greeting = $name !== '' ? 'Hola ' . $name . ',' : 'Hola,';
    $body = $greeting . "\n\n"
        . "Gracias por registrarte en " . APP_NAME . ".\n\n"
        . "Para activar tu cuenta y poder iniciar sesión, verifica tu correo desde este enlace:\n"
        . $link . "\n\n"
        . "Si no has creado esta cuenta, puedes ignorar este mensaje.\n\n"
        . APP_NAME;

    return send_plain_email($email, 'Verifica tu correo | ' . APP_NAME, $body);
}

function send_password_reset_link(array $user, string $token): bool
{
    $email = (string) ($user['email'] ?? '');
    $name = trim((string) ($user['name'] ?? ''));
    $link = password_reset_url($token);
    $greeting = $name !== '' ? 'Hola ' . $name . ',' : 'Hola,';
    $body = $greeting . "\n\n"
        . "Hemos recibido una solicitud para cambiar la contraseña de tu cuenta en " . APP_NAME . ".\n\n"
        . "Crea una nueva contraseña desde este enlace:\n"
        . $link . "\n\n"
        . "Si no has solicitado este cambio, puedes ignorar este mensaje.\n\n"
        . APP_NAME;

    return send_plain_email($email, 'Cambia tu contraseña | ' . APP_NAME, $body);
}

function send_email_change_confirmation_link(array $user, string $newEmail, string $token): bool
{
    $name = trim((string) ($user['name'] ?? ''));
    $link = email_change_confirmation_url($token);
    $greeting = $name !== '' ? 'Hola ' . $name . ',' : 'Hola,';
    $body = $greeting . "\n\n"
        . "Has solicitado cambiar el correo de tu cuenta en " . APP_NAME . ".\n\n"
        . "Confirma el nuevo correo desde este enlace:\n"
        . $link . "\n\n"
        . "Si no has solicitado este cambio, puedes ignorar este mensaje.\n\n"
        . APP_NAME;

    return send_plain_email($newEmail, 'Confirma tu nuevo correo | ' . APP_NAME, $body);
}

function safe_local_path(string $path, string $default = 'index.php'): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return $default;
    }

    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $trimmed)) {
        return $default;
    }

    if (str_contains($trimmed, "\n") || str_contains($trimmed, "\r")) {
        return $default;
    }

    if (str_starts_with($trimmed, APP_BASE_URL)) {
        $trimmed = substr($trimmed, strlen(APP_BASE_URL));
    }

    $trimmed = ltrim($trimmed, '/');
    if ($trimmed === '' || str_starts_with($trimmed, '..')) {
        return $default;
    }

    return $trimmed;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function &runtime_cache_store(): array
{
    static $store = [];
    return $store;
}

function session_cache_get(string $key, int $ttlSeconds): mixed
{
    $ttlSeconds = max(1, $ttlSeconds);
    $runtimeKey = 'session_cache:' . $key;
    $runtimeCache = &runtime_cache_store();
    if (array_key_exists($runtimeKey, $runtimeCache)) {
        return $runtimeCache[$runtimeKey];
    }

    if (PHP_SAPI === 'cli' || !isset($_SESSION) || !is_array($_SESSION)) {
        return null;
    }

    $entry = $_SESSION['app_cache'][$key] ?? null;
    if (!is_array($entry) || !isset($entry['stored_at'])) {
        return null;
    }

    if ((time() - (int) $entry['stored_at']) > $ttlSeconds) {
        unset($_SESSION['app_cache'][$key]);
        return null;
    }

    $runtimeCache[$runtimeKey] = $entry['value'] ?? null;
    return $runtimeCache[$runtimeKey];
}

function session_cache_put(string $key, mixed $value): void
{
    $runtimeKey = 'session_cache:' . $key;
    $runtimeCache = &runtime_cache_store();
    $runtimeCache[$runtimeKey] = $value;

    if (PHP_SAPI === 'cli' || !isset($_SESSION) || !is_array($_SESSION)) {
        return;
    }

    $_SESSION['app_cache'][$key] = [
        'stored_at' => time(),
        'value' => $value,
    ];
}

function session_cache_forget(string $key): void
{
    $runtimeKey = 'session_cache:' . $key;
    $runtimeCache = &runtime_cache_store();
    unset($runtimeCache[$runtimeKey]);

    if (PHP_SAPI === 'cli' || !isset($_SESSION) || !is_array($_SESSION)) {
        return;
    }

    unset($_SESSION['app_cache'][$key]);
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }

    return (string) $_SESSION['csrf_token'];
}

function validate_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function is_strong_password(string $password): bool
{
    return (bool) preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password);
}

function should_show_debug_links(): bool
{
    return (bool) config_value('show_debug_links', false);
}

function ensure_email_change_tokens_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS email_change_tokens (
          id BIGINT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          new_email VARCHAR(150) NOT NULL,
          token_hash CHAR(64) NOT NULL UNIQUE,
          expires_at DATETIME NOT NULL,
          used_at DATETIME DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_email_change_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
          INDEX idx_email_change_tokens_user (user_id),
          INDEX idx_email_change_tokens_new_email (new_email),
          INDEX idx_email_change_tokens_expiry (expires_at)
        ) ENGINE=InnoDB
    ');

    $ensured = true;
}

function issue_email_verification_token(PDO $pdo, int $userId, int $ttlSeconds = 86400): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));

    $cleanup = $pdo->prepare('
        DELETE FROM email_verification_tokens
        WHERE user_id = :user_id OR expires_at < NOW()
    ');
    $cleanup->execute([':user_id' => $userId]);

    $insert = $pdo->prepare('
        INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at)
        VALUES (:user_id, :token_hash, :expires_at, NOW())
    ');
    $insert->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    return $token;
}

function issue_email_change_token(PDO $pdo, int $userId, string $newEmail, int $ttlSeconds = 3600): string
{
    ensure_email_change_tokens_table($pdo);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));
    $normalizedEmail = mb_strtolower(trim($newEmail));

    $cleanup = $pdo->prepare('
        DELETE FROM email_change_tokens
        WHERE user_id = :user_id OR expires_at < NOW()
    ');
    $cleanup->execute([':user_id' => $userId]);

    $insert = $pdo->prepare('
        INSERT INTO email_change_tokens (user_id, new_email, token_hash, expires_at, created_at)
        VALUES (:user_id, :new_email, :token_hash, :expires_at, NOW())
    ');
    $insert->execute([
        ':user_id' => $userId,
        ':new_email' => $normalizedEmail,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    return $token;
}

function issue_password_reset_token(PDO $pdo, int $userId, int $ttlSeconds = 3600): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));

    $cleanup = $pdo->prepare('
        DELETE FROM password_reset_tokens
        WHERE user_id = :user_id OR expires_at < NOW()
    ');
    $cleanup->execute([':user_id' => $userId]);

    $insert = $pdo->prepare('
        INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
        VALUES (:user_id, :token_hash, :expires_at, NOW())
    ');
    $insert->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
    ]);

    return $token;
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $cleanIp = preg_replace('/[^0-9a-fA-F:\.]/', '', $ip);
    return $cleanIp !== '' ? $cleanIp : 'unknown';
}

function login_attempt_key(string $email): string
{
    return hash('sha256', mb_strtolower(trim($email)) . '|' . client_ip());
}

function too_many_login_attempts(string $email, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    $key = login_attempt_key($email);
    $now = time();
    $bucket = $_SESSION['login_attempts'][$key] ?? null;
    if (!is_array($bucket)) {
        return false;
    }

    if (($now - (int) ($bucket['first'] ?? 0)) > $windowSeconds) {
        unset($_SESSION['login_attempts'][$key]);
        return false;
    }

    return (int) ($bucket['count'] ?? 0) >= $maxAttempts;
}

function register_login_attempt(string $email): void
{
    $key = login_attempt_key($email);
    $now = time();
    $bucket = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'first' => $now];

    if (($now - (int) ($bucket['first'] ?? 0)) > 900) {
        $bucket = ['count' => 0, 'first' => $now];
    }

    $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
    $_SESSION['login_attempts'][$key] = $bucket;
}

function clear_login_attempts(string $email): void
{
    $key = login_attempt_key($email);
    unset($_SESSION['login_attempts'][$key]);
}

function user_has_active_premium(?array $user = null): bool
{
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) {
        return false;
    }
    if ((int) ($user['is_premium'] ?? 0) !== 1) {
        return false;
    }

    $expiresAt = (string) ($user['premium_expires_at'] ?? '');
    if ($expiresAt === '') {
        return false;
    }
    $expiresTs = strtotime($expiresAt);
    if ($expiresTs === false) {
        return false;
    }

    return $expiresTs >= time();
}

function user_can_submit_routes(?array $user = null): bool
{
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) {
        return false;
    }

    return (int) ($user['is_admin'] ?? 0) === 1 || user_has_active_premium($user);
}

function premium_monthly_price(?array $user = null): float
{
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) {
        return 4.00;
    }

    $price = (float) ($user['premium_price_month'] ?? 4.00);
    return $price > 0 ? $price : 4.00;
}

function premium_points_modifier(): float
{
    return 1.35;
}

function premium_challenge_progress_modifier(): float
{
    return 1.25;
}

function premium_points_bonus_percent(): int
{
    return max(0, (int) round((premium_points_modifier() - 1.0) * 100));
}

function premium_challenge_bonus_percent(): int
{
    return max(0, (int) round((premium_challenge_progress_modifier() - 1.0) * 100));
}

function sync_premium_status(PDO $pdo, array $user): array
{
    if (!array_key_exists('is_premium', $user)) {
        return $user;
    }
    if ((int) ($user['is_premium'] ?? 0) !== 1) {
        return $user;
    }

    $expiresAt = (string) ($user['premium_expires_at'] ?? '');
    $expiresTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
    if ($expiresTs === false || $expiresTs >= time()) {
        return $user;
    }

    try {
        $expireUser = $pdo->prepare('
            UPDATE users
            SET is_premium = 0,
                premium_plan = "free",
                premium_auto_renew = 0,
                updated_at = NOW()
            WHERE id = :id
        ');
        $expireUser->execute([':id' => (int) $user['id']]);

        $expireSubscription = $pdo->prepare('
            UPDATE premium_subscriptions
            SET status = "expired",
                updated_at = NOW()
            WHERE user_id = :user_id
              AND status = "active"
              AND ends_at <= NOW()
        ');
        $expireSubscription->execute([':user_id' => (int) $user['id']]);

        $reload = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $reload->execute([':id' => (int) $user['id']]);
        $fresh = $reload->fetch();
        if ($fresh) {
            return $fresh;
        }
    } catch (Throwable $e) {
        error_log('Premium sync error: ' . $e->getMessage());
    }

    return $user;
}

function current_user(): ?array
{
    static $cachedUser = false;
    if ($cachedUser !== false) {
        return $cachedUser;
    }

    if (empty($_SESSION['user_id'])) {
        $cachedUser = null;
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user(false);
        $cachedUser = null;
        return null;
    }

    $user = sync_premium_status(db(), $user);

    $cachedUser = $user;
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

function logout_user(bool $redirectAfter = true): void
{
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_unset();
    session_destroy();
    initialize_app_session();

    if ($redirectAfter) {
        set_flash('success', 'Sesion cerrada correctamente.');
        redirect('map.php');
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        set_flash('warning', 'Necesitas iniciar sesion para acceder a esta seccion.');
        redirect('login.php');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ((int) $user['is_admin'] !== 1) {
        set_flash('danger', 'No tienes permisos de administrador.');
        redirect('map.php');
    }

    return $user;
}

function create_notification(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $linkUrl = null): void
{
    try {
        $insert = $pdo->prepare('
            INSERT INTO notifications (user_id, notification_type, title, message, link_url, is_read, created_at)
            VALUES (:user_id, :notification_type, :title, :message, :link_url, 0, NOW())
        ');
        $insert->execute([
            ':user_id' => $userId,
            ':notification_type' => mb_substr($type, 0, 40),
            ':title' => mb_substr($title, 0, 140),
            ':message' => mb_substr($message, 0, 500),
            ':link_url' => $linkUrl !== null ? mb_substr($linkUrl, 0, 255) : null,
        ]);
        session_cache_forget('notifications.unread.' . $userId);
    } catch (Throwable $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
}

function notify_admins(PDO $pdo, string $type, string $title, string $message, ?string $linkUrl = null): void
{
    try {
        $admins = $pdo->query('SELECT id FROM users WHERE is_admin = 1 AND is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
            create_notification($pdo, (int) $adminId, $type, $title, $message, $linkUrl);
        }
    } catch (Throwable $e) {
        error_log('Notify admins error: ' . $e->getMessage());
    }
}

function unread_notifications_count(?int $userId = null): int
{
    static $cache = [];

    if ($userId === null) {
        $user = current_user();
        $userId = $user ? (int) $user['id'] : null;
    }
    if (!$userId || $userId <= 0) {
        return 0;
    }

    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    $cacheKey = 'notifications.unread.' . $userId;
    $cachedCount = session_cache_get($cacheKey, 20);
    if (is_int($cachedCount)) {
        $cache[$userId] = $cachedCount;
        return $cache[$userId];
    }

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute([':user_id' => $userId]);
        $cache[$userId] = (int) $stmt->fetchColumn();
        session_cache_put($cacheKey, $cache[$userId]);
        return $cache[$userId];
    } catch (Throwable) {
        return 0;
    }
}

function difficulty_default_points(string $difficulty): int
{
    return match ($difficulty) {
        'Baja' => 50,
        'Media' => 100,
        'Alta' => 200,
        'Muy Alta' => 300,
        default => 75,
    };
}

function difficulty_factor(string $difficulty): float
{
    return match ($difficulty) {
        'Baja' => 1.00,
        'Media' => 1.20,
        'Alta' => 1.50,
        'Muy Alta' => 1.80,
        default => 1.00,
    };
}

function completion_duration_bounds(float $distanceKm, string $activityType): array
{
    $distance = max(0.1, $distanceKm);
    $activity = mb_strtolower(trim($activityType));

    if ($activity === 'surf') {
        return ['min' => 30, 'max' => 240];
    }

    $maxSpeedKmH = $activity === 'ciclismo' ? 40.0 : 12.0;
    $minSpeedKmH = $activity === 'ciclismo' ? 6.0 : 1.5;

    $minMinutes = (int) ceil(($distance / $maxSpeedKmH) * 60);
    $maxMinutes = (int) ceil(($distance / $minSpeedKmH) * 60);

    $minMinutes = max(10, $minMinutes);
    $maxMinutes = min(5000, max($minMinutes + 10, $maxMinutes));

    return ['min' => $minMinutes, 'max' => $maxMinutes];
}

function points_to_level(int $points): int
{
    return max(1, (int) floor($points / 1000) + 1);
}

function add_points(PDO $pdo, int $userId, int $points): void
{
    if ($points <= 0) {
        return;
    }

    $update = $pdo->prepare('UPDATE users SET total_points = total_points + :points, updated_at = NOW() WHERE id = :id');
    $update->execute([':points' => $points, ':id' => $userId]);

    $select = $pdo->prepare('SELECT total_points FROM users WHERE id = :id');
    $select->execute([':id' => $userId]);
    $totalPoints = (int) $select->fetchColumn();
    $level = points_to_level($totalPoints);

    $sync = $pdo->prepare('UPDATE users SET level = :level WHERE id = :id');
    $sync->execute([':level' => $level, ':id' => $userId]);
}

function site_default_description(): string
{
    return 'Explora rutas reales de Asturias, completa retos comunitarios y sigue tu progreso en Reto Asturias Activa.';
}

function site_default_share_image(): string
{
    return absolute_url('assets/img/share-cover.svg');
}

function meta_text(string $text, int $limit = 170): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
    if ($clean === '') {
        return site_default_description();
    }

    return mb_strlen($clean) > $limit ? mb_substr($clean, 0, max(0, $limit - 3)) . '...' : $clean;
}

function current_request_path(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = $requestUri !== '' ? parse_url($requestUri, PHP_URL_PATH) : null;
    if (!is_string($path) || $path === '') {
        $path = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    }
    if (str_starts_with($path, APP_BASE_URL)) {
        $path = substr($path, strlen(APP_BASE_URL));
    }

    $path = trim((string) $path);
    if ($path === '' || $path === '/') {
        return 'index.php';
    }

    return ltrim($path, '/');
}

function page_uses_interactive_map_assets(?string $path = null): bool
{
    $currentPath = $path !== null && $path !== '' ? $path : current_request_path();
    return in_array($currentPath, ['route.php', 'map.php'], true);
}

function meta_image_url(?string $image): string
{
    $value = trim((string) $image);
    if ($value === '') {
        return site_default_share_image();
    }
    if (preg_match('/^https?:\/\//i', $value) === 1) {
        return $value;
    }

    return absolute_url($value);
}

function rebuild_url_with_query(array $parts, array $query): string
{
    $url = '';
    if (isset($parts['scheme'])) {
        $url .= (string) $parts['scheme'] . '://';
    }
    if (isset($parts['user'])) {
        $url .= (string) $parts['user'];
        if (isset($parts['pass'])) {
            $url .= ':' . (string) $parts['pass'];
        }
        $url .= '@';
    }
    $url .= (string) ($parts['host'] ?? '');
    if (isset($parts['port'])) {
        $url .= ':' . (string) $parts['port'];
    }
    $url .= (string) ($parts['path'] ?? '');

    $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    if ($queryString !== '') {
        $url .= '?' . $queryString;
    }
    if (isset($parts['fragment'])) {
        $url .= '#' . (string) $parts['fragment'];
    }

    return $url;
}

function route_image_variant_url(string $image, int $width, int $quality = 90): ?string
{
    $value = trim($image);
    if ($value === '' || preg_match('/^https?:\/\//i', $value) !== 1) {
        return null;
    }

    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $host = mb_strtolower((string) $parts['host']);
    $path = (string) ($parts['path'] ?? '');
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $safeWidth = (string) max(480, min(2560, $width));

    if ($host === 'images.unsplash.com' || str_ends_with($host, '.images.unsplash.com')) {
        $query['w'] = $safeWidth;
        $query['q'] = (string) max(75, min(95, $quality));
        $query['auto'] = 'format';
        $query['fit'] = (string) ($query['fit'] ?? 'crop');

        return rebuild_url_with_query($parts, $query);
    }

    if ($host === 'commons.wikimedia.org' && str_contains($path, '/wiki/Special:FilePath/')) {
        $query['width'] = $safeWidth;

        return rebuild_url_with_query($parts, $query);
    }

    return null;
}

function route_image_src(?string $image, int $targetWidth = 1600): string
{
    $value = trim((string) $image);
    if ($value === '') {
        return '';
    }

    return route_image_variant_url($value, $targetWidth) ?? $value;
}

function route_image_srcset(?string $image, array $widths = []): string
{
    $value = trim((string) $image);
    if ($value === '') {
        return '';
    }

    $widths = $widths !== [] ? $widths : [640, 960, 1280, 1600, 2200];
    $items = [];
    foreach (array_unique(array_map('intval', $widths)) as $width) {
        $variant = route_image_variant_url($value, $width);
        if ($variant === null) {
            return '';
        }
        $items[] = $variant . ' ' . max(480, min(2560, $width)) . 'w';
    }

    return implode(', ', $items);
}

function route_recompletion_cooldown_hours(): int
{
    $value = (int) config_value('route_recompletion_cooldown_hours', 168);
    return max(24, min(720, $value));
}

function route_recompletion_notice(): string
{
    $hours = route_recompletion_cooldown_hours();
    $days = $hours / 24;
    if ((int) $days * 24 === $hours) {
        return (int) $days === 1 ? '1 dia' : (int) $days . ' dias';
    }

    return $hours . ' horas';
}

function user_route_completion_window(PDO $pdo, int $userId, int $routeId): array
{
    $stmt = $pdo->prepare('
        SELECT completed_at
        FROM route_completions
        WHERE user_id = :user_id AND route_id = :route_id
        ORDER BY completed_at DESC
        LIMIT 1
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':route_id' => $routeId,
    ]);
    $lastCompletedAt = $stmt->fetchColumn();
    if (!$lastCompletedAt) {
        return [
            'allowed' => true,
            'last_completed_at' => null,
            'next_available_at' => null,
            'seconds_remaining' => 0,
        ];
    }

    $cooldownHours = route_recompletion_cooldown_hours();
    $now = new DateTimeImmutable('now');
    try {
        $lastCompleted = new DateTimeImmutable((string) $lastCompletedAt);
    } catch (Throwable) {
        return [
            'allowed' => true,
            'last_completed_at' => (string) $lastCompletedAt,
            'next_available_at' => null,
            'seconds_remaining' => 0,
        ];
    }

    $nextAvailable = $lastCompleted->modify('+' . $cooldownHours . ' hours');
    $secondsRemaining = max(0, $nextAvailable->getTimestamp() - $now->getTimestamp());

    return [
        'allowed' => $secondsRemaining === 0,
        'last_completed_at' => $lastCompleted->format('Y-m-d H:i:s'),
        'next_available_at' => $nextAvailable->format('Y-m-d H:i:s'),
        'seconds_remaining' => $secondsRemaining,
    ];
}

function normalize_route_points(array $points, int $maxPoints = 1500): array
{
    $normalized = [];
    $seen = [];

    foreach ($points as $point) {
        if (!is_array($point)) {
            continue;
        }

        $lat = $point['lat'] ?? null;
        $lng = $point['lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            continue;
        }

        $latValue = round((float) $lat, 6);
        $lngValue = round((float) $lng, 6);
        if ($latValue < -90 || $latValue > 90 || $lngValue < -180 || $lngValue > 180) {
            continue;
        }

        $key = $latValue . ',' . $lngValue;
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalizedPoint = [
            'lat' => $latValue,
            'lng' => $lngValue,
        ];
        $ele = $point['ele'] ?? null;
        if (is_numeric($ele)) {
            $normalizedPoint['ele'] = round((float) $ele, 1);
        }

        $normalized[] = $normalizedPoint;

        if (count($normalized) >= $maxPoints) {
            break;
        }
    }

    return $normalized;
}

function parse_route_coordinates_json(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? normalize_route_points($decoded) : [];
}

function parse_track_coordinates_file(string $filePath, ?string $extension = null): array
{
    if (!is_file($filePath)) {
        return [];
    }

    $ext = mb_strtolower((string) ($extension ?: pathinfo($filePath, PATHINFO_EXTENSION)));
    if (!in_array($ext, ['gpx', 'kml'], true)) {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($filePath, 'SimpleXMLElement', LIBXML_NONET);
    if ($xml === false) {
        return [];
    }

    $points = [];
    $rootName = mb_strtolower((string) $xml->getName());

    if ($rootName === 'gpx') {
        foreach ($xml->trk as $track) {
            foreach ($track->trkseg as $segment) {
                foreach ($segment->trkpt as $trackPoint) {
                    $points[] = [
                        'lat' => (string) ($trackPoint['lat'] ?? ''),
                        'lng' => (string) ($trackPoint['lon'] ?? ''),
                        'ele' => (string) ($trackPoint->ele ?? ''),
                    ];
                }
            }
        }

        if (empty($points)) {
            foreach ($xml->rte as $route) {
                foreach ($route->rtept as $routePoint) {
                    $points[] = [
                        'lat' => (string) ($routePoint['lat'] ?? ''),
                        'lng' => (string) ($routePoint['lon'] ?? ''),
                        'ele' => (string) ($routePoint->ele ?? ''),
                    ];
                }
            }
        }
    } elseif ($rootName === 'kml') {
        $namespaces = $xml->getDocNamespaces(true);
        if (!isset($namespaces['kml'])) {
            $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
        }
        if (!isset($namespaces['gx'])) {
            $xml->registerXPathNamespace('gx', 'http://www.google.com/kml/ext/2.2');
        }

        $coordinateNodes = $xml->xpath('//kml:LineString/kml:coordinates | //LineString/coordinates') ?: [];
        foreach ($coordinateNodes as $node) {
            $chunks = preg_split('/\s+/', trim((string) $node)) ?: [];
            foreach ($chunks as $chunk) {
                $parts = array_map('trim', explode(',', $chunk));
                if (count($parts) < 2) {
                    continue;
                }
                $points[] = [
                    'lat' => $parts[1],
                    'lng' => $parts[0],
                    'ele' => $parts[2] ?? null,
                ];
            }
        }

        if (empty($points)) {
            $gxCoords = $xml->xpath('//gx:Track/gx:coord') ?: [];
            foreach ($gxCoords as $node) {
                $parts = preg_split('/\s+/', trim((string) $node)) ?: [];
                if (count($parts) < 2) {
                    continue;
                }
                $points[] = [
                    'lat' => $parts[1],
                    'lng' => $parts[0],
                    'ele' => $parts[2] ?? null,
                ];
            }
        }
    }

    return normalize_route_points($points);
}

function route_track_points_from_upload(array $file, int $maxBytes = 4_194_304): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Hubo un problema al subir el archivo GPX/KML.');
    }
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('El archivo GPX/KML supera el tamano permitido.');
    }

    $ext = mb_strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['gpx', 'kml'], true)) {
        throw new RuntimeException('Solo se permiten archivos GPX o KML.');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('No se pudo validar el archivo GPX/KML subido.');
    }

    $points = parse_track_coordinates_file($tmpPath, $ext);
    if (count($points) < 2) {
        throw new RuntimeException('El archivo GPX/KML no contiene un trazado valido.');
    }

    return $points;
}

function route_points_from_form_input(string $coordsRaw, ?array $uploadedFile = null): array
{
    $trackPoints = null;
    if (is_array($uploadedFile) && $uploadedFile !== []) {
        $trackPoints = route_track_points_from_upload($uploadedFile);
    }

    if (is_array($trackPoints)) {
        return $trackPoints;
    }

    $jsonPoints = parse_route_coordinates_json($coordsRaw);
    if (count($jsonPoints) < 2) {
        throw new RuntimeException('Debes indicar coordenadas JSON validas o subir un archivo GPX/KML con el trazado real.');
    }

    return $jsonPoints;
}

function route_lookup_key(string $value): string
{
    $normalized = trim(mb_strtolower($value));
    if ($normalized === '') {
        return '';
    }

    $normalized = strtr($normalized, [
        'á' => 'a',
        'à' => 'a',
        'ä' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ë' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'ï' => 'i',
        'î' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'ö' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'ü' => 'u',
        'û' => 'u',
        'ñ' => 'n',
        'ç' => 'c',
    ]);

    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
    if (is_string($transliterated) && $transliterated !== '') {
        $normalized = $transliterated;
    }

    $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;
    return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
}

function route_download_filename(string $name, string $extension = 'gpx'): string
{
    $base = route_lookup_key($name);
    $base = str_replace(' ', '-', $base);
    if ($base === '') {
        $base = 'ruta-asturias';
    }

    $safeExtension = preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($extension)) ?: 'gpx';
    return $base . '.' . $safeExtension;
}

function route_interpolated_track(array $points, int $stepsPerSegment = 4): array
{
    $points = normalize_route_points($points);
    if (count($points) < 2) {
        return $points;
    }

    $steps = max(2, min(10, $stepsPerSegment));
    $interpolated = [];
    $totalPoints = count($points);

    for ($index = 0; $index < $totalPoints - 1; $index++) {
        $start = $points[$index];
        $end = $points[$index + 1];
        for ($step = 0; $step < $steps; $step++) {
            if ($index > 0 && $step === 0) {
                continue;
            }

            $ratio = $step / $steps;
            $point = [
                'lat' => (float) $start['lat'] + (((float) $end['lat'] - (float) $start['lat']) * $ratio),
                'lng' => (float) $start['lng'] + (((float) $end['lng'] - (float) $start['lng']) * $ratio),
            ];

            if (isset($start['ele'], $end['ele']) && is_numeric($start['ele']) && is_numeric($end['ele'])) {
                $point['ele'] = (float) $start['ele'] + (((float) $end['ele'] - (float) $start['ele']) * $ratio);
            }

            $interpolated[] = $point;
        }
    }

    $interpolated[] = $points[array_key_last($points)];
    return normalize_route_points($interpolated);
}

function route_catmull_rom_value(float $p0, float $p1, float $p2, float $p3, float $t): float
{
    $t2 = $t * $t;
    $t3 = $t2 * $t;

    return 0.5 * (
        (2 * $p1)
        + (-$p0 + $p2) * $t
        + ((2 * $p0) - (5 * $p1) + (4 * $p2) - $p3) * $t2
        + (-$p0 + (3 * $p1) - (3 * $p2) + $p3) * $t3
    );
}

function route_smooth_track(array $points, int $samplesPerSegment = 5): array
{
    $points = normalize_route_points($points);
    $count = count($points);
    if ($count < 2) {
        return $points;
    }
    if ($count < 4) {
        return route_interpolated_track($points, max(4, $samplesPerSegment));
    }

    $samples = max(3, min(8, $samplesPerSegment));
    $smoothed = [];

    for ($index = 0; $index < $count - 1; $index++) {
        $p0 = $points[max(0, $index - 1)];
        $p1 = $points[$index];
        $p2 = $points[$index + 1];
        $p3 = $points[min($count - 1, $index + 2)];

        for ($sample = 0; $sample < $samples; $sample++) {
            if ($index > 0 && $sample === 0) {
                continue;
            }

            $t = $sample / $samples;
            $point = [
                'lat' => route_catmull_rom_value((float) $p0['lat'], (float) $p1['lat'], (float) $p2['lat'], (float) $p3['lat'], $t),
                'lng' => route_catmull_rom_value((float) $p0['lng'], (float) $p1['lng'], (float) $p2['lng'], (float) $p3['lng'], $t),
            ];

            if (isset($p1['ele'], $p2['ele']) && is_numeric($p1['ele']) && is_numeric($p2['ele'])) {
                $ele0 = is_numeric($p0['ele'] ?? null) ? (float) $p0['ele'] : (float) $p1['ele'];
                $ele3 = is_numeric($p3['ele'] ?? null) ? (float) $p3['ele'] : (float) $p2['ele'];
                $point['ele'] = route_catmull_rom_value($ele0, (float) $p1['ele'], (float) $p2['ele'], $ele3, $t);
            }

            $smoothed[] = $point;
        }
    }

    $smoothed[] = $points[$count - 1];
    return normalize_route_points($smoothed);
}

function curated_route_tracks(): array
{
    static $tracks = null;
    if (is_array($tracks)) {
        return $tracks;
    }

    $tracks = [
        'Ruta del Cares' => [
            ['lat' => 43.2542, 'lng' => -4.8105],
            ['lat' => 43.2576, 'lng' => -4.8077],
            ['lat' => 43.2618, 'lng' => -4.8038],
            ['lat' => 43.2661, 'lng' => -4.8004],
            ['lat' => 43.2710, 'lng' => -4.7973],
            ['lat' => 43.2761, 'lng' => -4.7925],
            ['lat' => 43.2813, 'lng' => -4.7868],
            ['lat' => 43.2864, 'lng' => -4.7801],
            ['lat' => 43.2914, 'lng' => -4.7729],
            ['lat' => 43.2958, 'lng' => -4.7658],
            ['lat' => 43.2993, 'lng' => -4.7590],
            ['lat' => 43.3018, 'lng' => -4.7522],
        ],
        'Senda del Oso' => [
            ['lat' => 43.3508, 'lng' => -5.9872],
            ['lat' => 43.3435, 'lng' => -5.9904],
            ['lat' => 43.3352, 'lng' => -5.9956],
            ['lat' => 43.3258, 'lng' => -6.0035],
            ['lat' => 43.3159, 'lng' => -6.0132],
            ['lat' => 43.3047, 'lng' => -6.0269],
            ['lat' => 43.2928, 'lng' => -6.0428],
            ['lat' => 43.2801, 'lng' => -6.0599],
            ['lat' => 43.2669, 'lng' => -6.0780],
            ['lat' => 43.2527, 'lng' => -6.0944],
            ['lat' => 43.2374, 'lng' => -6.1076],
            ['lat' => 43.2205, 'lng' => -6.1138],
            ['lat' => 43.2021, 'lng' => -6.1136],
            ['lat' => 43.1827, 'lng' => -6.1095],
            ['lat' => 43.1628, 'lng' => -6.1025],
        ],
        'Lagos de Covadonga' => [
            ['lat' => 43.2712, 'lng' => -4.9954],
            ['lat' => 43.2741, 'lng' => -4.9924],
            ['lat' => 43.2782, 'lng' => -4.9902],
            ['lat' => 43.2826, 'lng' => -4.9893],
            ['lat' => 43.2866, 'lng' => -4.9871],
            ['lat' => 43.2888, 'lng' => -4.9821],
            ['lat' => 43.2866, 'lng' => -4.9780],
            ['lat' => 43.2829, 'lng' => -4.9757],
            ['lat' => 43.2789, 'lng' => -4.9751],
            ['lat' => 43.2756, 'lng' => -4.9764],
            ['lat' => 43.2730, 'lng' => -4.9799],
            ['lat' => 43.2720, 'lng' => -4.9846],
            ['lat' => 43.2712, 'lng' => -4.9954],
        ],
        'Ruta de las Xanas' => [
            ['lat' => 43.2983, 'lng' => -6.0140],
            ['lat' => 43.2965, 'lng' => -6.0167],
            ['lat' => 43.2940, 'lng' => -6.0204],
            ['lat' => 43.2916, 'lng' => -6.0247],
            ['lat' => 43.2889, 'lng' => -6.0287],
            ['lat' => 43.2861, 'lng' => -6.0325],
            ['lat' => 43.2834, 'lng' => -6.0368],
            ['lat' => 43.2808, 'lng' => -6.0407],
            ['lat' => 43.2780, 'lng' => -6.0444],
            ['lat' => 43.2750, 'lng' => -6.0476],
            ['lat' => 43.2722, 'lng' => -6.0501],
        ],
        'Camin Encantau' => [
            ['lat' => 43.4192, 'lng' => -4.8605],
            ['lat' => 43.4179, 'lng' => -4.8574],
            ['lat' => 43.4162, 'lng' => -4.8535],
            ['lat' => 43.4141, 'lng' => -4.8494],
            ['lat' => 43.4117, 'lng' => -4.8451],
            ['lat' => 43.4092, 'lng' => -4.8410],
            ['lat' => 43.4067, 'lng' => -4.8374],
            ['lat' => 43.4049, 'lng' => -4.8339],
            ['lat' => 43.4034, 'lng' => -4.8304],
            ['lat' => 43.4024, 'lng' => -4.8269],
        ],
        'Ruta del Alba' => [
            ['lat' => 43.1898, 'lng' => -5.4551],
            ['lat' => 43.1881, 'lng' => -5.4506],
            ['lat' => 43.1860, 'lng' => -5.4453],
            ['lat' => 43.1838, 'lng' => -5.4398],
            ['lat' => 43.1816, 'lng' => -5.4345],
            ['lat' => 43.1789, 'lng' => -5.4281],
            ['lat' => 43.1760, 'lng' => -5.4217],
            ['lat' => 43.1728, 'lng' => -5.4152],
            ['lat' => 43.1697, 'lng' => -5.4106],
            ['lat' => 43.1669, 'lng' => -5.4062],
            ['lat' => 43.1645, 'lng' => -5.4030],
            ['lat' => 43.1621, 'lng' => -5.4012],
        ],
        'Bufones de Pria' => [
            ['lat' => 43.4547, 'lng' => -4.9981],
            ['lat' => 43.4537, 'lng' => -4.9951],
            ['lat' => 43.4525, 'lng' => -4.9919],
            ['lat' => 43.4509, 'lng' => -4.9882],
            ['lat' => 43.4491, 'lng' => -4.9848],
            ['lat' => 43.4471, 'lng' => -4.9807],
            ['lat' => 43.4450, 'lng' => -4.9762],
            ['lat' => 43.4429, 'lng' => -4.9721],
            ['lat' => 43.4406, 'lng' => -4.9680],
            ['lat' => 43.4380, 'lng' => -4.9630],
        ],
        'Senda Costera de Llanes' => [
            ['lat' => 43.4224, 'lng' => -4.7549],
            ['lat' => 43.4211, 'lng' => -4.7494],
            ['lat' => 43.4194, 'lng' => -4.7442],
            ['lat' => 43.4177, 'lng' => -4.7380],
            ['lat' => 43.4160, 'lng' => -4.7317],
            ['lat' => 43.4146, 'lng' => -4.7250],
            ['lat' => 43.4132, 'lng' => -4.7180],
            ['lat' => 43.4118, 'lng' => -4.7110],
            ['lat' => 43.4104, 'lng' => -4.7041],
            ['lat' => 43.4088, 'lng' => -4.6971],
            ['lat' => 43.4068, 'lng' => -4.6903],
            ['lat' => 43.4047, 'lng' => -4.6835],
        ],
        'Senda del Cervigon' => [
            ['lat' => 43.5554, 'lng' => -5.6519],
            ['lat' => 43.5536, 'lng' => -5.6485],
            ['lat' => 43.5518, 'lng' => -5.6448],
            ['lat' => 43.5501, 'lng' => -5.6408],
            ['lat' => 43.5481, 'lng' => -5.6367],
            ['lat' => 43.5460, 'lng' => -5.6325],
            ['lat' => 43.5441, 'lng' => -5.6286],
            ['lat' => 43.5424, 'lng' => -5.6244],
            ['lat' => 43.5407, 'lng' => -5.6203],
            ['lat' => 43.5391, 'lng' => -5.6162],
            ['lat' => 43.5371, 'lng' => -5.6122],
        ],
        'Subida al Angliru' => [
            ['lat' => 43.2044, 'lng' => -5.8823],
            ['lat' => 43.2019, 'lng' => -5.8798],
            ['lat' => 43.1988, 'lng' => -5.8774],
            ['lat' => 43.1954, 'lng' => -5.8748],
            ['lat' => 43.1918, 'lng' => -5.8719],
            ['lat' => 43.1881, 'lng' => -5.8694],
            ['lat' => 43.1844, 'lng' => -5.8665],
            ['lat' => 43.1806, 'lng' => -5.8633],
            ['lat' => 43.1767, 'lng' => -5.8599],
            ['lat' => 43.1729, 'lng' => -5.8567],
            ['lat' => 43.1688, 'lng' => -5.8529],
            ['lat' => 43.1642, 'lng' => -5.8485],
            ['lat' => 43.1598, 'lng' => -5.8442],
        ],
    ];

    foreach ($tracks as $name => $points) {
        $tracks[$name] = normalize_route_points($points);
    }

    return $tracks;
}

function route_curated_track(array $route): array
{
    static $indexedTracks = null;

    if (!is_array($indexedTracks)) {
        $indexedTracks = [];
        foreach (curated_route_tracks() as $routeName => $points) {
            $lookupKey = route_lookup_key((string) $routeName);
            if ($lookupKey === '') {
                continue;
            }
            $indexedTracks[$lookupKey] = normalize_route_points($points);
        }
    }

    $lookupKey = route_lookup_key((string) ($route['name'] ?? ''));
    if ($lookupKey === '') {
        return [];
    }

    return $indexedTracks[$lookupKey] ?? [];
}

function route_latest_completion_track(PDO $pdo, int $routeId): array
{
    static $cache = [];
    if (array_key_exists($routeId, $cache)) {
        return $cache[$routeId];
    }

    $stmt = $pdo->prepare('
        SELECT gpx_filename
        FROM route_completions
        WHERE route_id = :route_id
          AND gpx_filename IS NOT NULL
          AND gpx_filename <> ""
        ORDER BY completed_at DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute([':route_id' => $routeId]);
    $filename = (string) ($stmt->fetchColumn() ?: '');
    if ($filename === '') {
        $cache[$routeId] = [];
        return [];
    }

    $absolutePath = __DIR__ . '/uploads/tracks/' . basename($filename);
    $cache[$routeId] = parse_track_coordinates_file($absolutePath);
    return $cache[$routeId];
}

function route_reference_track(array $route, array $storedPoints): array
{
    $curated = route_curated_track($route);
    if (count($curated) >= 5 && count($curated) >= count($storedPoints)) {
        return [
            'points' => $curated,
            'source' => 'reference_track',
        ];
    }

    if (count($storedPoints) >= 4) {
        $smoothed = route_smooth_track($storedPoints, 5);
        if (count($smoothed) > count($storedPoints)) {
            return [
                'points' => $smoothed,
                'source' => 'smoothed_track',
            ];
        }
    }

    if (count($storedPoints) >= 2) {
        $interpolated = route_interpolated_track($storedPoints, 4);
        if (count($interpolated) > count($storedPoints)) {
            return [
                'points' => $interpolated,
                'source' => 'smoothed_track',
            ];
        }
    }

    return [
        'points' => $storedPoints,
        'source' => 'stored_points',
    ];
}

function route_map_payload(PDO $pdo, array $route): array
{
    $stored = parse_route_coordinates_json((string) ($route['coordinates_json'] ?? ''));
    $communityTrack = route_latest_completion_track($pdo, (int) ($route['id'] ?? 0));

    if (count($communityTrack) >= 5) {
        return [
            'points' => $communityTrack,
            'source' => 'community_track',
        ];
    }

    return route_reference_track($route, $stored);
}

function route_map_source_label(string $source): string
{
    return match ($source) {
        'community_track' => 'Track real GPX/KML aportado por la comunidad',
        'reference_track' => 'Trazado de referencia afinado para la ficha',
        'smoothed_track' => 'Trazado suavizado a partir de la ficha de la ruta',
        default => 'Coordenadas basicas de la ruta',
    };
}

function route_distance_between_points(array $from, array $to): float
{
    $lat1 = deg2rad((float) ($from['lat'] ?? 0));
    $lng1 = deg2rad((float) ($from['lng'] ?? 0));
    $lat2 = deg2rad((float) ($to['lat'] ?? 0));
    $lng2 = deg2rad((float) ($to['lng'] ?? 0));

    $deltaLat = $lat2 - $lat1;
    $deltaLng = $lng2 - $lng1;
    $a = sin($deltaLat / 2) ** 2
        + cos($lat1) * cos($lat2) * (sin($deltaLng / 2) ** 2);
    $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

    return 6371.0 * $c;
}

function route_cumulative_distances(array $points): array
{
    $points = normalize_route_points($points);
    if (empty($points)) {
        return [];
    }

    $distances = [0.0];
    for ($index = 1, $count = count($points); $index < $count; $index++) {
        $distances[] = $distances[$index - 1] + route_distance_between_points($points[$index - 1], $points[$index]);
    }

    return $distances;
}

function route_profile_source_label(string $source): string
{
    return match ($source) {
        'recorded' => 'Perfil basado en altitudes del track GPX/KML',
        'estimated' => 'Perfil estimado a partir del desnivel acumulado',
        default => 'Perfil no disponible',
    };
}

function route_elevation_profile(array $route, array $points, int $maxPoints = 120): array
{
    $points = normalize_route_points($points);
    if (count($points) < 2) {
        return [
            'points' => [],
            'source' => 'none',
            'min' => null,
            'max' => null,
            'distance_km' => (float) ($route['distance_km'] ?? 0),
        ];
    }

    $distances = route_cumulative_distances($points);
    $trackDistance = (float) ($distances[array_key_last($distances)] ?? 0.0);
    $routeDistance = max((float) ($route['distance_km'] ?? 0), $trackDistance, 0.1);
    $stride = max(1, (int) ceil(count($points) / max(12, $maxPoints)));

    $hasRecordedElevation = true;
    foreach ($points as $point) {
        if (!is_numeric($point['ele'] ?? null)) {
            $hasRecordedElevation = false;
            break;
        }
    }

    if ($hasRecordedElevation) {
        $profile = [];
        for ($index = 0, $count = count($points); $index < $count; $index += $stride) {
            $profile[] = [
                'distance_km' => round((float) ($distances[$index] ?? 0.0), 3),
                'elevation_m' => round((float) $points[$index]['ele'], 1),
            ];
        }
        $lastPoint = $points[array_key_last($points)];
        $lastDistance = round((float) ($distances[array_key_last($distances)] ?? 0.0), 3);
        $lastElevation = round((float) ($lastPoint['ele'] ?? 0.0), 1);
        $lastProfilePoint = $profile[array_key_last($profile)] ?? null;
        if (!is_array($lastProfilePoint) || (float) ($lastProfilePoint['distance_km'] ?? -1.0) !== $lastDistance) {
            $profile[] = [
                'distance_km' => $lastDistance,
                'elevation_m' => $lastElevation,
            ];
        }

        $elevations = array_column($profile, 'elevation_m');
        return [
            'points' => $profile,
            'source' => 'recorded',
            'min' => min($elevations),
            'max' => max($elevations),
            'distance_km' => $routeDistance,
        ];
    }

    $sampleCount = max(28, min(72, count($points) * 2));
    $shape = [];
    for ($sample = 0; $sample < $sampleCount; $sample++) {
        $t = $sampleCount === 1 ? 0.0 : $sample / ($sampleCount - 1);
        $shape[] = max(
            0.0,
            (0.74 * sin(M_PI * $t))
            + (0.18 * sin(3 * M_PI * $t))
            + (0.08 * sin(5 * M_PI * $t))
        );
    }

    $positiveDeltaSum = 0.0;
    for ($index = 1; $index < count($shape); $index++) {
        $positiveDeltaSum += max(0.0, $shape[$index] - $shape[$index - 1]);
    }
    $ascentTarget = max(40.0, (float) ($route['elevation_m'] ?? 0));
    $scale = $positiveDeltaSum > 0 ? ($ascentTarget / $positiveDeltaSum) : $ascentTarget;

    $profile = [];
    foreach ($shape as $index => $level) {
        $distance = $sampleCount === 1 ? 0.0 : ($routeDistance * ($index / ($sampleCount - 1)));
        $profile[] = [
            'distance_km' => round($distance, 3),
            'elevation_m' => round($level * $scale, 1),
        ];
    }

    $elevations = array_column($profile, 'elevation_m');
    return [
        'points' => $profile,
        'source' => 'estimated',
        'min' => min($elevations),
        'max' => max($elevations),
        'distance_km' => $routeDistance,
    ];
}

function route_points_to_gpx_xml(array $route, array $points, string $linkUrl = ''): string
{
    $points = normalize_route_points($points);
    $name = htmlspecialchars((string) ($route['name'] ?? APP_NAME), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $description = htmlspecialchars((string) ($route['description'] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $link = htmlspecialchars($linkUrl, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $generatedAt = gmdate('Y-m-d\TH:i:s\Z');

    $xml = [];
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<gpx version="1.1" creator="Reto Asturias Activa" xmlns="http://www.topografix.com/GPX/1/1">';
    $xml[] = '  <metadata>';
    $xml[] = '    <name>' . $name . '</name>';
    if ($description !== '') {
        $xml[] = '    <desc>' . $description . '</desc>';
    }
    if ($link !== '') {
        $xml[] = '    <link href="' . $link . '"><text>Ficha de ruta</text></link>';
    }
    $xml[] = '    <time>' . $generatedAt . '</time>';
    $xml[] = '  </metadata>';
    $xml[] = '  <trk>';
    $xml[] = '    <name>' . $name . '</name>';
    $xml[] = '    <trkseg>';

    foreach ($points as $point) {
        $lat = number_format((float) $point['lat'], 6, '.', '');
        $lng = number_format((float) $point['lng'], 6, '.', '');
        $xml[] = '      <trkpt lat="' . $lat . '" lon="' . $lng . '">';
        if (is_numeric($point['ele'] ?? null)) {
            $xml[] = '        <ele>' . number_format((float) $point['ele'], 1, '.', '') . '</ele>';
        }
        $xml[] = '      </trkpt>';
    }

    $xml[] = '    </trkseg>';
    $xml[] = '  </trk>';
    $xml[] = '</gpx>';

    return implode("\n", $xml) . "\n";
}

function route_points_to_kml_xml(array $route, array $points, string $linkUrl = ''): string
{
    $points = normalize_route_points($points);
    $name = htmlspecialchars((string) ($route['name'] ?? APP_NAME), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $description = htmlspecialchars((string) ($route['description'] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $link = htmlspecialchars($linkUrl, ENT_QUOTES | ENT_XML1, 'UTF-8');

    $coordinateChunks = [];
    foreach ($points as $point) {
        $lng = number_format((float) $point['lng'], 6, '.', '');
        $lat = number_format((float) $point['lat'], 6, '.', '');
        $ele = is_numeric($point['ele'] ?? null) ? number_format((float) $point['ele'], 1, '.', '') : '0';
        $coordinateChunks[] = $lng . ',' . $lat . ',' . $ele;
    }

    $xml = [];
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<kml xmlns="http://www.opengis.net/kml/2.2">';
    $xml[] = '  <Document>';
    $xml[] = '    <name>' . $name . '</name>';
    if ($description !== '') {
        $xml[] = '    <description>' . $description . '</description>';
    }
    if ($link !== '') {
        $xml[] = '    <Snippet>' . $link . '</Snippet>';
    }
    $xml[] = '    <Placemark>';
    $xml[] = '      <name>' . $name . '</name>';
    $xml[] = '      <LineString>';
    $xml[] = '        <tessellate>1</tessellate>';
    $xml[] = '        <coordinates>' . implode(' ', $coordinateChunks) . '</coordinates>';
    $xml[] = '      </LineString>';
    $xml[] = '    </Placemark>';
    $xml[] = '  </Document>';
    $xml[] = '</kml>';

    return implode("\n", $xml) . "\n";
}

function route_points_to_geojson(array $route, array $points, string $linkUrl = ''): string
{
    $points = normalize_route_points($points);
    $coordinates = [];
    foreach ($points as $point) {
        $coordinate = [
            round((float) $point['lng'], 6),
            round((float) $point['lat'], 6),
        ];
        if (is_numeric($point['ele'] ?? null)) {
            $coordinate[] = round((float) $point['ele'], 1);
        }
        $coordinates[] = $coordinate;
    }

    $payload = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => [
                'name' => (string) ($route['name'] ?? APP_NAME),
                'zone' => (string) ($route['zone'] ?? ''),
                'difficulty' => (string) ($route['difficulty'] ?? ''),
                'activity_type' => (string) ($route['activity_type'] ?? ''),
                'distance_km' => (float) ($route['distance_km'] ?? 0),
                'elevation_m' => (int) ($route['elevation_m'] ?? 0),
                'url' => $linkUrl,
            ],
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates,
            ],
        ]],
    ];

    return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function route_download_formats(): array
{
    return [
        'gpx' => [
            'label' => 'GPX',
            'extension' => 'gpx',
            'mime' => 'application/gpx+xml; charset=UTF-8',
            'description' => 'Compatible con la mayoria de apps de senderismo y GPS.',
            'premium_only' => false,
        ],
        'kml' => [
            'label' => 'KML',
            'extension' => 'kml',
            'mime' => 'application/vnd.google-earth.kml+xml; charset=UTF-8',
            'description' => 'Muy comodo para Google Earth y visores de mapas.',
            'premium_only' => true,
        ],
        'geojson' => [
            'label' => 'GeoJSON',
            'extension' => 'geojson',
            'mime' => 'application/geo+json; charset=UTF-8',
            'description' => 'Ideal para herramientas GIS, edicion y reutilizacion tecnica.',
            'premium_only' => true,
        ],
        'roadbook' => [
            'label' => 'Roadbook premium',
            'extension' => 'html',
            'mime' => 'text/html; charset=UTF-8',
            'description' => 'Dossier imprimible con hitos, resumen tecnico y hoja lista para guardar como PDF.',
            'premium_only' => true,
        ],
    ];
}

function route_effort_score(array $route): float
{
    $distance = max(0.1, (float) ($route['distance_km'] ?? 0.0));
    $elevation = max(0.0, (float) ($route['elevation_m'] ?? 0.0));
    $difficultyKey = route_lookup_key((string) ($route['difficulty'] ?? ''));
    $difficultyBoost = 1.0;
    if (str_contains($difficultyKey, 'alta') || str_contains($difficultyKey, 'dificil')) {
        $difficultyBoost = 5.0;
    } elseif (str_contains($difficultyKey, 'media')) {
        $difficultyBoost = 2.8;
    }

    return round(($distance * 1.45) + ($elevation / 120.0) + $difficultyBoost, 2);
}

function premium_user_baseline(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT
            COUNT(*) AS total_routes,
            COALESCE(AVG(r.distance_km), 0) AS avg_distance_km,
            COALESCE(AVG(r.elevation_m), 0) AS avg_elevation_m,
            COALESCE(SUM(CASE WHEN rc.duration_min > 0 THEN rc.duration_min ELSE 0 END), 0) AS total_minutes,
            COALESCE(SUM(CASE WHEN rc.duration_min > 0 THEN r.distance_km ELSE 0 END), 0) AS timed_distance_km
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
    ');
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch() ?: [];

    $totalRoutes = (int) ($row['total_routes'] ?? 0);
    $avgDistance = (float) ($row['avg_distance_km'] ?? 0.0);
    $avgElevation = (float) ($row['avg_elevation_m'] ?? 0.0);
    $totalMinutes = (float) ($row['total_minutes'] ?? 0.0);
    $timedDistance = (float) ($row['timed_distance_km'] ?? 0.0);
    $paceMinKm = $timedDistance > 0 ? ($totalMinutes / $timedDistance) : 11.5;

    if ($totalRoutes <= 0) {
        $avgDistance = 8.5;
        $avgElevation = 320.0;
        $paceMinKm = 11.5;
    } else {
        $avgDistance = max(4.0, $avgDistance);
        $avgElevation = max(120.0, $avgElevation);
        $paceMinKm = max(6.0, min(24.0, $paceMinKm));
    }

    return [
        'total_routes' => $totalRoutes,
        'avg_distance_km' => $avgDistance,
        'avg_elevation_m' => $avgElevation,
        'pace_min_km' => $paceMinKm,
        'effort_baseline' => route_effort_score([
            'distance_km' => $avgDistance,
            'elevation_m' => $avgElevation,
            'difficulty' => $avgElevation >= 700 ? 'alta' : ($avgElevation >= 350 ? 'media' : 'facil'),
        ]),
        'source' => $totalRoutes >= 3 ? 'history' : 'estimated',
    ];
}

function premium_route_fit_profile(float $ratio): array
{
    if ($ratio <= 0.85) {
        return [
            'label' => 'Ideal para ti',
            'tone' => 'success',
            'summary' => 'Encaja muy bien con tu nivel actual y es buena para sumar volumen sin castigarte.',
        ];
    }
    if ($ratio <= 1.05) {
        return [
            'label' => 'Buen encaje',
            'tone' => 'info',
            'summary' => 'Tiene una exigencia muy parecida a la que ya manejas en tus rutas recientes.',
        ];
    }
    if ($ratio <= 1.25) {
        return [
            'label' => 'Reto equilibrado',
            'tone' => 'warning',
            'summary' => 'Te va a exigir un poco mas, pero sigue siendo un reto razonable para progresar.',
        ];
    }
    if ($ratio <= 1.45) {
        return [
            'label' => 'Exigente',
            'tone' => 'warning',
            'summary' => 'Conviene planificarla con tiempo, revisar ritmo y no tomartela como una salida ligera.',
        ];
    }

    return [
        'label' => 'Muy ambiciosa',
        'tone' => 'danger',
        'summary' => 'Esta bastante por encima de tu media reciente; mejor afrontarla con preparacion o compania.',
    ];
}

function premium_route_coach(PDO $pdo, int $userId, array $route): array
{
    $baseline = premium_user_baseline($pdo, $userId);
    $distance = max(0.1, (float) ($route['distance_km'] ?? 0.0));
    $elevation = max(0.0, (float) ($route['elevation_m'] ?? 0.0));
    $routeEffort = route_effort_score($route);
    $ratio = $routeEffort / max(1.0, (float) $baseline['effort_baseline']);
    $fit = premium_route_fit_profile($ratio);

    $expectedDuration = (int) round(($distance * (float) $baseline['pace_min_km']) + ($elevation / 18.0));
    $expectedDuration = max(35, $expectedDuration);

    $tips = [];
    if ($ratio > 1.2) {
        $tips[] = 'Sal con margen de tiempo y piensa la ruta como salida principal del dia, no como paseo rapido.';
    } else {
        $tips[] = 'Buena candidata para mantener constancia y sumar una ruta util sin disparar la fatiga.';
    }
    if ($elevation >= 900) {
        $tips[] = 'Lleva agua y comida de apoyo porque el desnivel manda bastante en el desgaste real.';
    } elseif ($distance >= 14) {
        $tips[] = 'El kilometraje pesa casi tanto como el desnivel; vigila el ritmo desde el primer tercio.';
    } else {
        $tips[] = 'Si sales fresco puedes usarla como jornada tecnica o de recuperacion activa segun el ritmo.';
    }
    if ((string) $baseline['source'] !== 'history') {
        $tips[] = 'La estimacion personal aun usa una base orientativa. Cuantas mas rutas completes, mas fino quedara el calculo premium.';
    } else {
        $tips[] = 'El calculo usa tu historial real de rutas con distancia, desnivel y tiempos registrados.';
    }

    return [
        'expected_duration_min' => $expectedDuration,
        'expected_duration_label' => format_minutes_human($expectedDuration),
        'fit_label' => (string) $fit['label'],
        'fit_tone' => (string) $fit['tone'],
        'fit_summary' => (string) $fit['summary'],
        'effort_score' => $routeEffort,
        'ratio_percent' => (int) round(($ratio - 1.0) * 100),
        'baseline_label' => number_format((float) $baseline['avg_distance_km'], 1) . ' km y ' . number_format((float) $baseline['avg_elevation_m'], 0) . ' m+ de media',
        'tips' => $tips,
        'data_source' => (string) $baseline['source'],
    ];
}

function route_roadbook_checkpoints(array $profilePoints, float $routeDistanceKm = 0.0, int $desiredStops = 6): array
{
    if (empty($profilePoints)) {
        return [];
    }

    $lastIndex = count($profilePoints) - 1;
    $targetStops = max(3, min(7, $desiredStops));
    $indexes = [0];
    for ($slot = 1; $slot < $targetStops - 1; $slot++) {
        $indexes[] = (int) round(($slot / ($targetStops - 1)) * $lastIndex);
    }
    $indexes[] = $lastIndex;
    $indexes = array_values(array_unique(array_map(
        static fn (int $index): int => max(0, min($lastIndex, $index)),
        $indexes
    )));

    $checkpoints = [];
    $previousDistance = 0.0;
    foreach ($indexes as $order => $index) {
        $point = $profilePoints[$index];
        $distance = (float) ($point['distance_km'] ?? 0.0);
        $label = 'Hito ' . $order;
        if ($order === 0) {
            $label = 'Salida';
        } elseif ($index === $lastIndex) {
            $label = 'Meta';
        }

        $checkpoints[] = [
            'label' => $label,
            'distance_km' => $distance,
            'segment_km' => max(0.0, $distance - $previousDistance),
            'elevation_m' => (float) ($point['elevation_m'] ?? 0.0),
            'progress_percent' => $routeDistanceKm > 0 ? (int) round(($distance / $routeDistanceKm) * 100) : 0,
        ];
        $previousDistance = $distance;
    }

    return $checkpoints;
}

function route_points_to_roadbook_html(
    array $route,
    array $points,
    array $routeDetails,
    array $elevationProfile,
    string $shareUrl = '',
    array $coach = []
): string {
    $points = normalize_route_points($points);
    $distanceKm = (float) ($elevationProfile['distance_km'] ?? ($route['distance_km'] ?? 0.0));
    $checkpoints = route_roadbook_checkpoints((array) ($elevationProfile['points'] ?? []), $distanceKm);
    $tips = array_slice((array) ($routeDetails['tips'] ?? []), 0, 5);
    $title = htmlspecialchars((string) ($route['name'] ?? APP_NAME), ENT_QUOTES, 'UTF-8');
    $description = nl2br(htmlspecialchars((string) ($route['description'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $shape = htmlspecialchars((string) ($routeDetails['shape'] ?? ''), ENT_QUOTES, 'UTF-8');
    $duration = htmlspecialchars((string) (($routeDetails['duration']['label'] ?? 'Sin estimacion')), ENT_QUOTES, 'UTF-8');
    $terrain = htmlspecialchars((string) ($routeDetails['terrain'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bestSeason = htmlspecialchars((string) ($routeDetails['best_season'] ?? ''), ENT_QUOTES, 'UTF-8');
    $audience = htmlspecialchars((string) ($routeDetails['audience'] ?? ''), ENT_QUOTES, 'UTF-8');
    $orientation = htmlspecialchars((string) ($routeDetails['orientation'] ?? ''), ENT_QUOTES, 'UTF-8');
    $zone = htmlspecialchars((string) ($route['zone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $activityType = htmlspecialchars((string) ($route['activity_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $shareLink = htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8');
    $distanceLabel = number_format((float) ($route['distance_km'] ?? $distanceKm), 1) . ' km';
    $elevationLabel = number_format((float) ($route['elevation_m'] ?? 0), 0) . ' m+';
    $minElevation = $elevationProfile['min'] !== null ? number_format((float) $elevationProfile['min'], 0) . ' m' : 'Sin dato';
    $maxElevation = $elevationProfile['max'] !== null ? number_format((float) $elevationProfile['max'], 0) . ' m' : 'Sin dato';

    $tipsHtml = '';
    foreach ($tips as $tip) {
        $tipsHtml .= '<li>' . htmlspecialchars((string) $tip, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    $checkpointRows = '';
    foreach ($checkpoints as $checkpoint) {
        $checkpointRows .= '<tr>'
            . '<td>' . htmlspecialchars((string) $checkpoint['label'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . number_format((float) $checkpoint['distance_km'], 1) . ' km</td>'
            . '<td>' . number_format((float) $checkpoint['segment_km'], 1) . ' km</td>'
            . '<td>' . number_format((float) $checkpoint['elevation_m'], 0) . ' m</td>'
            . '<td>' . (int) $checkpoint['progress_percent'] . '%</td>'
            . '</tr>';
    }

    $coachHtml = '';
    if (!empty($coach)) {
        $coachHtml = '
        <section class="block premium">
            <h2>Lectura premium para ti</h2>
            <div class="facts">
                <div class="fact"><small>Tiempo personal estimado</small><strong>' . htmlspecialchars((string) ($coach['expected_duration_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></div>
                <div class="fact"><small>Encaje</small><strong>' . htmlspecialchars((string) ($coach['fit_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></div>
                <div class="fact"><small>Esfuerzo relativo</small><strong>' . ((int) ($coach['ratio_percent'] ?? 0) >= 0 ? '+' : '') . (int) ($coach['ratio_percent'] ?? 0) . '%</strong></div>
            </div>
            <p class="lead">' . htmlspecialchars((string) ($coach['fit_summary'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>
        </section>';
    }

    return '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . $title . ' - Roadbook premium</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f7f3ed; color: #1f2824; }
        .sheet { max-width: 960px; margin: 0 auto; padding: 32px 24px 48px; }
        .hero { background: linear-gradient(135deg, #fff4e3 0%, #eef7f1 100%); border: 1px solid #e6c8a0; border-radius: 20px; padding: 24px; }
        .eyebrow { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #f5dfbe; color: #7d4a14; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        h1, h2 { font-family: Arial, sans-serif; margin: 0 0 12px; }
        h1 { font-size: 34px; }
        h2 { font-size: 20px; margin-top: 0; }
        .lead { color: #50625a; line-height: 1.6; }
        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 18px; }
        .fact { background: rgba(255,255,255,0.86); border: 1px solid #e4d6c3; border-radius: 14px; padding: 14px; }
        .fact small { display: block; color: #6f7f78; margin-bottom: 6px; }
        .fact strong { font-size: 20px; }
        .grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 16px; margin-top: 18px; }
        .block { background: #ffffff; border: 1px solid #e4e7df; border-radius: 18px; padding: 18px; margin-top: 18px; }
        .block.premium { border-color: #e4c399; background: linear-gradient(180deg, #fffdf9 0%, #fcf6ee 100%); }
        ul { margin: 0; padding-left: 18px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e8ece8; padding: 10px 8px; text-align: left; font-size: 14px; }
        th { color: #54645d; }
        .actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
        .button { display: inline-block; text-decoration: none; padding: 11px 14px; border-radius: 999px; background: #1e6f50; color: #ffffff; font-weight: 700; }
        .button.alt { background: #f0e4d4; color: #7d4a14; }
        .meta { color: #5d6e66; margin-top: 10px; }
        @media print {
            body { background: #ffffff; }
            .sheet { max-width: none; padding: 0; }
            .actions { display: none; }
            .hero, .block, .fact { box-shadow: none; }
        }
        @media (max-width: 760px) {
            .grid { grid-template-columns: 1fr; }
            h1 { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <section class="hero">
            <span class="eyebrow">Roadbook premium</span>
            <h1>' . $title . '</h1>
            <p class="lead">' . $description . '</p>
            <div class="facts">
                <div class="fact"><small>Zona / actividad</small><strong>' . $zone . ' / ' . $activityType . '</strong></div>
                <div class="fact"><small>Distancia</small><strong>' . $distanceLabel . '</strong></div>
                <div class="fact"><small>Desnivel</small><strong>' . $elevationLabel . '</strong></div>
                <div class="fact"><small>Duracion orientativa</small><strong>' . $duration . '</strong></div>
            </div>
            <div class="actions">
                <button class="button" onclick="window.print()">Imprimir / Guardar en PDF</button>
                ' . ($shareLink !== '' ? '<a class="button alt" href="' . $shareLink . '">Volver a la ficha online</a>' : '') . '
            </div>
            <p class="meta">Documento pensado para llevar la ruta offline, imprimirla o guardarla como PDF desde el navegador.</p>
        </section>

        ' . $coachHtml . '

        <section class="grid">
            <div class="block">
                <h2>Resumen tecnico</h2>
                <div class="facts">
                    <div class="fact"><small>Tipo</small><strong>' . $shape . '</strong></div>
                    <div class="fact"><small>Terreno</small><strong>' . $terrain . '</strong></div>
                    <div class="fact"><small>Mejor epoca</small><strong>' . $bestSeason . '</strong></div>
                    <div class="fact"><small>Perfil recomendado</small><strong>' . $audience . '</strong></div>
                    <div class="fact"><small>Orientacion</small><strong>' . $orientation . '</strong></div>
                    <div class="fact"><small>Cotas</small><strong>' . $minElevation . ' - ' . $maxElevation . '</strong></div>
                </div>
            </div>
            <div class="block">
                <h2>Checklist rapida</h2>
                <ul>' . $tipsHtml . '</ul>
            </div>
        </section>

        <section class="block">
            <h2>Hitos del trazado</h2>
            <table>
                <thead>
                    <tr>
                        <th>Punto</th>
                        <th>Acumulado</th>
                        <th>Tramo</th>
                        <th>Altitud</th>
                        <th>Progreso</th>
                    </tr>
                </thead>
                <tbody>' . $checkpointRows . '</tbody>
            </table>
        </section>
    </div>
</body>
</html>';
}

function format_minutes_human(int $minutes): string
{
    $safeMinutes = max(0, $minutes);
    $hours = intdiv($safeMinutes, 60);
    $remainingMinutes = $safeMinutes % 60;

    if ($hours <= 0) {
        return $remainingMinutes . ' min';
    }
    if ($remainingMinutes === 0) {
        return $hours . ' h';
    }

    return $hours . ' h ' . $remainingMinutes . ' min';
}

function month_label_es(DateTimeImmutable $date): string
{
    $months = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    return ($months[(int) $date->format('n')] ?? $date->format('M')) . ' ' . $date->format('y');
}

function route_estimated_duration_range(array $route): array
{
    $distance = max(0.5, (float) ($route['distance_km'] ?? 0));
    $elevation = max(0, (int) ($route['elevation_m'] ?? 0));
    $activity = mb_strtolower(trim((string) ($route['activity_type'] ?? 'senderismo')));
    $difficulty = (string) ($route['difficulty'] ?? 'Media');

    if ($activity === 'surf') {
        $minutes = match ($difficulty) {
            'Baja' => [45, 90],
            'Media' => [60, 120],
            'Alta' => [75, 150],
            'Muy Alta' => [90, 180],
            default => [60, 120],
        };

        return [
            'min_minutes' => $minutes[0],
            'max_minutes' => $minutes[1],
            'label' => format_minutes_human($minutes[0]) . ' - ' . format_minutes_human($minutes[1]),
        ];
    }

    $fastSpeed = 5.0;
    $steadySpeed = 4.0;
    $elevationPenaltyFactor = 600.0;

    if (in_array($activity, ['trail', 'running'], true)) {
        $fastSpeed = 7.5;
        $steadySpeed = 6.2;
        $elevationPenaltyFactor = 720.0;
    } elseif (in_array($activity, ['ciclismo', 'btt'], true)) {
        $fastSpeed = 20.0;
        $steadySpeed = 15.0;
        $elevationPenaltyFactor = 900.0;
    } elseif ($activity === 'marcha nordica') {
        $fastSpeed = 6.0;
        $steadySpeed = 4.8;
        $elevationPenaltyFactor = 650.0;
    }

    $difficultyFactor = match ($difficulty) {
        'Baja' => 0.95,
        'Media' => 1.05,
        'Alta' => 1.2,
        'Muy Alta' => 1.35,
        default => 1.0,
    };

    $fastHours = (($distance / $fastSpeed) + (($elevation / $elevationPenaltyFactor) * 0.75)) * $difficultyFactor;
    $steadyHours = (($distance / $steadySpeed) + ($elevation / $elevationPenaltyFactor)) * $difficultyFactor * 1.1;

    $minMinutes = max(20, (int) round($fastHours * 60));
    $maxMinutes = max($minMinutes + 15, (int) round($steadyHours * 60));

    return [
        'min_minutes' => $minMinutes,
        'max_minutes' => $maxMinutes,
        'label' => format_minutes_human($minMinutes) . ' - ' . format_minutes_human($maxMinutes),
    ];
}

function route_start_end_points(array $points): array
{
    $points = normalize_route_points($points);
    return [
        'start' => $points[0] ?? null,
        'end' => !empty($points) ? $points[array_key_last($points)] : null,
    ];
}

function route_google_maps_link(?array $point): string
{
    if (!is_array($point) || !isset($point['lat'], $point['lng'])) {
        return '';
    }

    return 'https://www.google.com/maps?q='
        . rawurlencode(number_format((float) $point['lat'], 6, '.', '') . ',' . number_format((float) $point['lng'], 6, '.', ''));
}

function route_is_surf(array $route): bool
{
    return route_lookup_key((string) ($route['activity_type'] ?? '')) === 'surf';
}

function route_surf_details(array $route): array
{
    $nameKey = route_lookup_key((string) ($route['name'] ?? ''));
    $difficulty = (string) ($route['difficulty'] ?? 'Media');
    $defaultLevel = in_array($difficulty, ['Alta', 'Muy Alta'], true)
        ? 'Intermedio avanzado'
        : ($difficulty === 'Baja' ? 'Iniciacion con mar pequeno' : 'Intermedio');

    $defaults = [
        'level' => $defaultLevel,
        'tide' => 'Media marea',
        'wind' => 'Sur o suroeste suave',
        'swell' => 'Noroeste ordenado',
        'bottom' => 'Arena',
        'wave' => 'Picos variables de derecha e izquierda',
        'season' => 'Todo el ano con parte favorable',
        'safety' => 'Consulta bandera, corrientes y parte de olas antes de entrar.',
        'checklist' => [
            'Revisa marea, viento, periodo y tamano real en la orilla.',
            'Entra con leash, neopreno adecuado y referencias claras de salida.',
            'Respeta prioridades, escuelas y zonas de bano senalizadas.',
        ],
    ];

    $byName = [
        'surf en rodiles' => [
            'level' => 'Avanzado en la izquierda de la ria',
            'tide' => 'Baja para la ola principal; alta para picos mas suaves',
            'wind' => 'Sur, sureste o suroeste flojo',
            'swell' => 'Noroeste con periodo medio-alto',
            'bottom' => 'Arena con influencia de ria',
            'wave' => 'Izquierda larga, potente y con secciones tuberas',
            'safety' => 'Puede tener corriente fuerte y mucho nivel en el pico principal.',
        ],
        'surf en salinas el espartal' => [
            'level' => 'Intermedio',
            'tide' => 'Media marea, adaptable segun bancos',
            'wind' => 'Sur o suroeste',
            'swell' => 'Noroeste',
            'bottom' => 'Arena',
            'wave' => 'Beach break largo con muchos picos',
            'safety' => 'El tamano cambia mucho entre zonas; elige pico segun nivel.',
        ],
        'surf en la grande de tapia' => [
            'level' => 'Intermedio avanzado',
            'tide' => 'Media marea',
            'wind' => 'Sur o suroeste',
            'swell' => 'Noroeste',
            'bottom' => 'Arena y roca segun zona',
            'wave' => 'Picos con secciones rapidas y tradicion de campeonato',
            'safety' => 'Ojo a rocas, corriente y saturacion en dias buenos.',
        ],
        'surf en san lorenzo' => [
            'level' => 'Iniciacion a intermedio',
            'tide' => 'Baja o media segun bancos',
            'wind' => 'Sur o suroeste',
            'swell' => 'Norte o noroeste moderado',
            'bottom' => 'Arena',
            'wave' => 'Picos urbanos de derecha e izquierda',
            'safety' => 'Respeta zonas de bano y escuelas, especialmente en verano.',
        ],
        'surf en xago' => [
            'level' => 'Intermedio',
            'tide' => 'Baja a media',
            'wind' => 'Sur o sureste',
            'swell' => 'Noroeste, funciona con poco mar',
            'bottom' => 'Arena',
            'wave' => 'Picos rapidos y expuestos',
            'safety' => 'Playa abierta: revisa corrientes y viento antes de entrar.',
        ],
        'surf en santa marina' => [
            'level' => 'Iniciacion a intermedio',
            'tide' => 'Media marea',
            'wind' => 'Sur o suroeste',
            'swell' => 'Norte moderado',
            'bottom' => 'Arena',
            'wave' => 'Varios picos en playa urbana',
            'safety' => 'Atencion a corrientes cerca de la desembocadura del Sella.',
        ],
        'surf en vega' => [
            'level' => 'Intermedio',
            'tide' => 'Media marea',
            'wind' => 'Sur o suroeste',
            'swell' => 'Noroeste pequeno o medio',
            'bottom' => 'Arena y cantos en zonas',
            'wave' => 'Beach break abierto con derechas e izquierdas',
            'safety' => 'No aguanta demasiado mar; evita dias pasados de tamano.',
        ],
        'surf en san antolin' => [
            'level' => 'Intermedio avanzado',
            'tide' => 'Media marea',
            'wind' => 'Sur o sureste',
            'swell' => 'Noroeste',
            'bottom' => 'Arena y grava',
            'wave' => 'Playa abierta con olas potentes',
            'safety' => 'Alta exposicion y fuerte oleaje; evita entrar sin experiencia.',
        ],
        'surf en penarronda' => [
            'level' => 'Intermedio avanzado',
            'tide' => 'Media marea',
            'wind' => 'Sur o suroeste',
            'swell' => 'Norte o noroeste',
            'bottom' => 'Arena',
            'wave' => 'Barras de derecha e izquierda',
            'safety' => 'Vigila corrientes cerca del arroyo y cambios de banco.',
        ],
        'surf en frejulfe' => [
            'level' => 'Intermedio',
            'tide' => 'Llena o baja segun bancos',
            'wind' => 'Sur, suroeste o sureste',
            'swell' => 'Norte o noroeste',
            'bottom' => 'Arena',
            'wave' => 'Derechas e izquierdas orilleras',
            'safety' => 'Corrientes marcadas en margenes; entra con referencias claras.',
        ],
        'surf en playon de bayas' => [
            'level' => 'Intermedio',
            'tide' => 'Media marea',
            'wind' => 'Sur o suroeste',
            'swell' => 'Noroeste',
            'bottom' => 'Arena',
            'wave' => 'Arenal largo con picos repartidos',
            'safety' => 'Atencion a corrientes de resaca y distancia entre accesos.',
        ],
        'surf en verdicio' => [
            'level' => 'Intermedio avanzado',
            'tide' => 'Media marea',
            'wind' => 'Sur o sureste',
            'swell' => 'Noroeste pequeno a medio',
            'bottom' => 'Arena gruesa',
            'wave' => 'Izquierdas potentes con secciones huecas',
            'safety' => 'Rompe cerca de orilla y con fuerza; lee bien el pico antes de entrar.',
        ],
        'surf en otur' => [
            'level' => 'Intermedio',
            'tide' => 'Baja o media',
            'wind' => 'Sur o suroeste',
            'swell' => 'Noroeste pequeno',
            'bottom' => 'Arena',
            'wave' => 'Picos variables y maniobrables',
            'safety' => 'Controla la corriente y no apures si el mar sube rapido.',
        ],
    ];

    $details = array_replace($defaults, $byName[$nameKey] ?? []);
    $details['checklist'] = array_values(array_unique(array_merge(
        (array) ($details['checklist'] ?? []),
        [$details['safety']]
    )));

    return $details;
}

function route_shape_label(array $route, array $points): string
{
    if (route_is_surf($route)) {
        return 'Spot de surf';
    }

    $distance = max(0.1, (float) ($route['distance_km'] ?? 0));
    $ends = route_start_end_points($points);
    $start = $ends['start'];
    $end = $ends['end'];
    if (!is_array($start) || !is_array($end)) {
        return 'No definido';
    }

    $startEndDistance = route_distance_between_points($start, $end);
    if ($startEndDistance <= max(0.7, $distance * 0.12)) {
        return 'Circular';
    }

    return 'Lineal / travesia';
}

function route_terrain_label(array $route): string
{
    $description = mb_strtolower((string) ($route['description'] ?? ''));
    $activity = mb_strtolower((string) ($route['activity_type'] ?? ''));

    if (route_is_surf($route)) {
        $surf = route_surf_details($route);
        return (string) $surf['bottom'] . ', rompiente variable y corrientes segun marea.';
    }

    if (str_contains($description, 'cost') || str_contains($description, 'acantil') || str_contains($description, 'playa')) {
        return 'Senda costera, pradera y tramos expuestos al viento.';
    }
    if (str_contains($description, 'bosque') || str_contains($description, 'hayed') || str_contains($description, 'rio') || str_contains($description, 'desfiladero')) {
        return 'Sendero forestal, piedra humeda y tramos de ribera.';
    }
    if (str_contains($description, 'pico') || str_contains($description, 'cumbre') || str_contains($description, 'alta montana') || str_contains($description, 'cresta')) {
        return 'Terreno de montana con roca y pendiente sostenida.';
    }
    if (in_array($activity, ['ciclismo', 'btt'], true)) {
        return 'Pista compacta, carretera secundaria y subida continua.';
    }

    return 'Sendero mixto con firme variable segun la climatologia.';
}

function route_best_season_label(array $route): string
{
    $description = mb_strtolower((string) ($route['description'] ?? ''));
    $difficulty = (string) ($route['difficulty'] ?? 'Media');

    if (route_is_surf($route)) {
        return (string) route_surf_details($route)['season'];
    }

    if ($difficulty === 'Muy Alta' || str_contains($description, 'alta montana') || str_contains($description, 'cumbre')) {
        return 'Finales de primavera, verano y comienzos de otono.';
    }
    if (str_contains($description, 'cost') || str_contains($description, 'litoral') || str_contains($description, 'acantil')) {
        return 'Casi todo el ano, evitando temporales y viento fuerte.';
    }
    if (str_contains($description, 'bosque') || str_contains($description, 'rio') || str_contains($description, 'desfiladero')) {
        return 'Primavera y otono, cuando el entorno esta mas vistoso.';
    }

    return 'Primavera y verano, con meteo estable.';
}

function route_audience_label(array $route): string
{
    $distance = (float) ($route['distance_km'] ?? 0);
    $elevation = (int) ($route['elevation_m'] ?? 0);
    $difficulty = (string) ($route['difficulty'] ?? 'Media');
    $activity = mb_strtolower((string) ($route['activity_type'] ?? 'senderismo'));

    if (route_is_surf($route)) {
        return (string) route_surf_details($route)['level'];
    }

    if (in_array($activity, ['ciclismo', 'btt'], true)) {
        return $difficulty === 'Baja'
            ? 'Apta para ciclistas recreativos con minima preparacion.'
            : 'Recomendada para ciclistas con algo de fondo y control de subida.';
    }
    if ($difficulty === 'Baja' && $distance <= 10 && $elevation <= 250) {
        return 'Apta para publico general y familias habituadas a caminar.';
    }
    if ($difficulty === 'Media' && $distance <= 15 && $elevation <= 600) {
        return 'Ideal para personas con habito de rutas ocasionales.';
    }

    return 'Pensada para usuarios con experiencia y buena forma fisica.';
}

function route_orientation_label(array $route, string $mapSource): string
{
    if (route_is_surf($route)) {
        $surf = route_surf_details($route);
        return 'Mar: ' . (string) $surf['swell'] . '. Viento: ' . (string) $surf['wind'] . '.';
    }

    if ($mapSource === 'community_track') {
        return 'Muy sencilla: hay track real reciente de la comunidad.';
    }
    if ($mapSource === 'reference_track') {
        return 'Buena: la ficha incluye un trazado afinado para seguir la ruta.';
    }
    if ((string) ($route['difficulty'] ?? '') === 'Muy Alta') {
        return 'Media-alta: conviene llevar track y revisar la meteo antes de salir.';
    }

    return 'Media: recomendable llevar el movil cargado y revisar el recorrido.';
}

function route_practical_tips(array $route): array
{
    $activity = mb_strtolower((string) ($route['activity_type'] ?? 'senderismo'));
    $difficulty = (string) ($route['difficulty'] ?? 'Media');

    if (route_is_surf($route)) {
        return route_surf_details($route)['checklist'];
    }

    $tips = [
        'Lleva agua, bateria suficiente y calzado adaptado al firme.',
    ];

    if (in_array($activity, ['ciclismo', 'btt'], true)) {
        $tips[] = 'Comprueba frenos, presion de ruedas y visibilidad antes de la salida.';
    } else {
        $tips[] = 'Consulta el estado del terreno si ha llovido en los dias previos.';
    }

    if (in_array($difficulty, ['Alta', 'Muy Alta'], true)) {
        $tips[] = 'Empieza temprano y evita condiciones meteorologicas inestables.';
    } else {
        $tips[] = 'Aun siendo una ruta asequible, revisa el trazado completo antes de empezar.';
    }

    return $tips;
}

function route_details_bundle(array $route, array $points, string $mapSource): array
{
    $duration = route_estimated_duration_range($route);
    $ends = route_start_end_points($points);
    $downloadFormats = route_download_formats();
    $isSurf = route_is_surf($route);

    return [
        'duration' => $duration,
        'shape' => route_shape_label($route, $points),
        'terrain' => route_terrain_label($route),
        'best_season' => route_best_season_label($route),
        'audience' => route_audience_label($route),
        'orientation' => route_orientation_label($route, $mapSource),
        'tips' => route_practical_tips($route),
        'start_point' => $ends['start'],
        'end_point' => $ends['end'],
        'start_link' => route_google_maps_link($ends['start']),
        'end_link' => route_google_maps_link($ends['end']),
        'download_formats' => $downloadFormats,
        'surf' => $isSurf ? route_surf_details($route) : null,
    ];
}

function user_premium_insights(PDO $pdo, int $userId, int $months = 6): array
{
    $months = max(3, min(12, $months));
    $firstDay = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');

    $summaryStmt = $pdo->prepare('
        SELECT
            COUNT(*) AS routes_window,
            COALESCE(SUM(r.distance_km), 0) AS km_window,
            COALESCE(SUM(r.elevation_m), 0) AS elevation_window,
            COALESCE(AVG(rc.duration_min), 0) AS avg_duration,
            COALESCE(AVG(rc.points_obtained), 0) AS avg_points,
            COUNT(DISTINCT r.zone) AS zones_window
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
          AND rc.completed_at >= :first_day
    ');
    $summaryStmt->execute([
        ':user_id' => $userId,
        ':first_day' => $firstDay->format('Y-m-d 00:00:00'),
    ]);
    $summary = $summaryStmt->fetch() ?: [];

    $topZoneStmt = $pdo->prepare('
        SELECT r.zone
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
          AND rc.completed_at >= :first_day
        GROUP BY r.zone
        ORDER BY COUNT(*) DESC, SUM(r.distance_km) DESC, r.zone ASC
        LIMIT 1
    ');
    $topZoneStmt->execute([
        ':user_id' => $userId,
        ':first_day' => $firstDay->format('Y-m-d 00:00:00'),
    ]);
    $topZone = (string) ($topZoneStmt->fetchColumn() ?: '');

    $topActivityStmt = $pdo->prepare('
        SELECT r.activity_type
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
          AND rc.completed_at >= :first_day
        GROUP BY r.activity_type
        ORDER BY COUNT(*) DESC, AVG(rc.points_obtained) DESC, r.activity_type ASC
        LIMIT 1
    ');
    $topActivityStmt->execute([
        ':user_id' => $userId,
        ':first_day' => $firstDay->format('Y-m-d 00:00:00'),
    ]);
    $topActivity = (string) ($topActivityStmt->fetchColumn() ?: '');

    $toughestRouteStmt = $pdo->prepare('
        SELECT r.name
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
          AND rc.completed_at >= :first_day
        ORDER BY r.elevation_m DESC, r.distance_km DESC, rc.completed_at DESC
        LIMIT 1
    ');
    $toughestRouteStmt->execute([
        ':user_id' => $userId,
        ':first_day' => $firstDay->format('Y-m-d 00:00:00'),
    ]);
    $toughestRoute = (string) ($toughestRouteStmt->fetchColumn() ?: '');

    $seriesStmt = $pdo->prepare('
        SELECT
            DATE_FORMAT(rc.completed_at, "%Y-%m") AS month_key,
            COUNT(*) AS total_routes,
            COALESCE(SUM(r.distance_km), 0) AS total_km,
            COALESCE(SUM(rc.points_obtained), 0) AS total_points
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
          AND rc.completed_at >= :first_day
        GROUP BY DATE_FORMAT(rc.completed_at, "%Y-%m")
        ORDER BY month_key ASC
    ');
    $seriesStmt->execute([
        ':user_id' => $userId,
        ':first_day' => $firstDay->format('Y-m-d 00:00:00'),
    ]);
    $seriesRows = $seriesStmt->fetchAll();
    $seriesByMonth = [];
    foreach ($seriesRows as $row) {
        $seriesByMonth[(string) $row['month_key']] = $row;
    }

    $series = [];
    $maxRoutes = 1;
    $maxKm = 1.0;
    $maxPoints = 1;
    for ($offset = 0; $offset < $months; $offset++) {
        $monthDate = $firstDay->modify('+' . $offset . ' months');
        $monthKey = $monthDate->format('Y-m');
        $row = $seriesByMonth[$monthKey] ?? null;
        $monthRoutes = (int) ($row['total_routes'] ?? 0);
        $monthKm = (float) ($row['total_km'] ?? 0);
        $monthPoints = (int) ($row['total_points'] ?? 0);
        $series[] = [
            'month_key' => $monthKey,
            'month_label' => month_label_es($monthDate),
            'routes' => $monthRoutes,
            'km' => $monthKm,
            'points' => $monthPoints,
        ];
        $maxRoutes = max($maxRoutes, $monthRoutes);
        $maxKm = max($maxKm, $monthKm);
        $maxPoints = max($maxPoints, $monthPoints);
    }

    return [
        'window_label' => $months . ' meses',
        'routes_window' => (int) ($summary['routes_window'] ?? 0),
        'km_window' => (float) ($summary['km_window'] ?? 0),
        'elevation_window' => (int) round((float) ($summary['elevation_window'] ?? 0)),
        'avg_duration' => (int) round((float) ($summary['avg_duration'] ?? 0)),
        'avg_points' => (int) round((float) ($summary['avg_points'] ?? 0)),
        'zones_window' => (int) ($summary['zones_window'] ?? 0),
        'top_zone' => $topZone,
        'top_activity' => $topActivity,
        'toughest_route' => $toughestRoute,
        'series' => $series,
        'series_max_routes' => $maxRoutes,
        'series_max_km' => $maxKm,
        'series_max_points' => $maxPoints,
    ];
}

function user_route_preferences(PDO $pdo, int $userId): array
{
    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    $preferences = [
        'activity_type' => '',
        'zone' => '',
        'difficulty' => '',
    ];

    $cacheKey = 'user.route_preferences.' . $userId;
    $cachedPreferences = session_cache_get($cacheKey, 180);
    if (is_array($cachedPreferences)) {
        $cache[$userId] = array_merge($preferences, $cachedPreferences);
        return $cache[$userId];
    }

    foreach (array_keys($preferences) as $field) {
        $stmt = $pdo->prepare('
            SELECT pref.value
            FROM (
                SELECT r.' . $field . ' AS value, 2 AS weight
                FROM route_favorites rf
                JOIN routes r ON r.id = rf.route_id
                WHERE rf.user_id = :favorites_user_id
                UNION ALL
                SELECT r.' . $field . ' AS value, 1 AS weight
                FROM route_completions rc
                JOIN routes r ON r.id = rc.route_id
                WHERE rc.user_id = :completions_user_id
            ) pref
            WHERE pref.value IS NOT NULL AND pref.value <> ""
            GROUP BY pref.value
            ORDER BY SUM(pref.weight) DESC, pref.value ASC
            LIMIT 1
        ');
        $stmt->execute([
            ':favorites_user_id' => $userId,
            ':completions_user_id' => $userId,
        ]);
        $preferences[$field] = (string) ($stmt->fetchColumn() ?: '');
    }

    $cache[$userId] = $preferences;
    session_cache_put($cacheKey, $preferences);
    return $cache[$userId];
}

function route_recommendation_reason(array $route, array $preferences): string
{
    $reasons = [];
    if (($preferences['activity_type'] ?? '') !== '' && (string) ($route['activity_type'] ?? '') === (string) $preferences['activity_type']) {
        $reasons[] = 'encaja con tu actividad favorita';
    }
    if (($preferences['zone'] ?? '') !== '' && (string) ($route['zone'] ?? '') === (string) $preferences['zone']) {
        $reasons[] = 'coincide con tu zona mas repetida';
    }
    if (($preferences['difficulty'] ?? '') !== '' && (string) ($route['difficulty'] ?? '') === (string) $preferences['difficulty']) {
        $reasons[] = 'tiene tu nivel habitual';
    }

    if (empty($reasons)) {
        if ((float) ($route['avg_rating'] ?? 0) >= 4.5) {
            return 'destaca por su valoracion de la comunidad';
        }
        if ((int) ($route['total_completions'] ?? 0) >= 5) {
            return 'es una de las rutas con mas actividad';
        }
        return 'puede ayudarte a descubrir una nueva zona de Asturias';
    }

    return implode(' y ', array_slice($reasons, 0, 2));
}

function route_similarity_reason(array $candidateRoute, array $referenceRoute): string
{
    $reasons = [];
    if ((string) ($candidateRoute['zone'] ?? '') === (string) ($referenceRoute['zone'] ?? '')) {
        $reasons[] = 'misma zona';
    }
    if ((string) ($candidateRoute['activity_type'] ?? '') === (string) ($referenceRoute['activity_type'] ?? '')) {
        $reasons[] = 'misma actividad';
    }
    if ((string) ($candidateRoute['difficulty'] ?? '') === (string) ($referenceRoute['difficulty'] ?? '')) {
        $reasons[] = 'misma dificultad';
    }

    if (empty($reasons)) {
        return 'perfil parecido y buenas senales de comunidad';
    }

    return implode(' y ', array_slice($reasons, 0, 2));
}

function recommended_routes_for_user(PDO $pdo, ?array $user, int $limit = 3): array
{
    $limit = max(1, min(12, $limit));
    $preferences = [
        'activity_type' => '',
        'zone' => '',
        'difficulty' => '',
    ];
    $params = [];
    $scoreParts = [
        'ROUND(COALESCE(cstats.avg_rating, 0) * 10, 0)',
        'LEAST(COALESCE(rcstats.total_completions, 0), 40)',
    ];
    $where = ['r.submission_status = "approved"'];

    if ($user) {
        $preferences = user_route_preferences($pdo, (int) $user['id']);
        $where[] = 'NOT EXISTS (
            SELECT 1
            FROM route_completions rcx
            WHERE rcx.route_id = r.id AND rcx.user_id = :user_id
        )';
        $params[':user_id'] = (int) $user['id'];

        if ($preferences['activity_type'] !== '') {
            $scoreParts[] = 'CASE WHEN r.activity_type = :pref_activity THEN 45 ELSE 0 END';
            $params[':pref_activity'] = $preferences['activity_type'];
        }
        if ($preferences['zone'] !== '') {
            $scoreParts[] = 'CASE WHEN r.zone = :pref_zone THEN 35 ELSE 0 END';
            $params[':pref_zone'] = $preferences['zone'];
        }
        if ($preferences['difficulty'] !== '') {
            $scoreParts[] = 'CASE WHEN r.difficulty = :pref_difficulty THEN 20 ELSE 0 END';
            $params[':pref_difficulty'] = $preferences['difficulty'];
        }
    }

    $sql = '
        SELECT
            r.id,
            r.name,
            r.zone,
            r.difficulty,
            r.activity_type,
            r.distance_km,
            r.elevation_m,
            r.base_points,
            r.description,
            r.cover_image,
            COALESCE(cstats.avg_rating, 0) AS avg_rating,
            COALESCE(cstats.total_comments, 0) AS total_comments,
            COALESCE(rcstats.total_completions, 0) AS total_completions,
            ' . implode(' + ', $scoreParts) . ' AS recommendation_score
        FROM routes r
        LEFT JOIN (
            SELECT route_id, AVG(rating) AS avg_rating, COUNT(*) AS total_comments
            FROM comments
            WHERE status = "approved"
            GROUP BY route_id
        ) cstats ON cstats.route_id = r.id
        LEFT JOIN (
            SELECT route_id, COUNT(*) AS total_completions
            FROM route_completions
            GROUP BY route_id
        ) rcstats ON rcstats.route_id = r.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY recommendation_score DESC, avg_rating DESC, total_completions DESC, r.created_at DESC
        LIMIT :limit
    ';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function premium_weekly_plan(PDO $pdo, array $user, int $limit = 3): array
{
    $limit = max(2, min(4, $limit));
    $routes = recommended_routes_for_user($pdo, $user, 9);
    if (empty($routes)) {
        return [
            'cards' => [],
            'total_distance_km' => 0.0,
            'total_elevation_m' => 0.0,
            'summary' => 'Completa alguna ruta mas para que el planificador premium pueda proponerte una semana equilibrada.',
        ];
    }

    $baseline = premium_user_baseline($pdo, (int) ($user['id'] ?? 0));
    foreach ($routes as &$candidate) {
        $candidate['effort_score'] = route_effort_score($candidate);
        $candidate['fit_ratio'] = (float) $candidate['effort_score'] / max(1.0, (float) $baseline['effort_baseline']);
    }
    unset($candidate);

    usort($routes, static function (array $a, array $b): int {
        return ($a['fit_ratio'] <=> $b['fit_ratio']) ?: ((float) $a['distance_km'] <=> (float) $b['distance_km']);
    });

    $slots = [
        ['label' => 'Base', 'min' => 0.0, 'max' => 0.95, 'summary' => 'Para sumar continuidad sin castigar demasiado las piernas.'],
        ['label' => 'Progresion', 'min' => 0.95, 'max' => 1.18, 'summary' => 'La salida que te hace crecer manteniendo un riesgo razonable.'],
        ['label' => 'Reto', 'min' => 1.18, 'max' => 99.0, 'summary' => 'La ruta potente de la semana para rematar con ambicion.'],
    ];

    $selectedIds = [];
    $cards = [];
    foreach ($slots as $slot) {
        $picked = null;
        foreach ($routes as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId <= 0 || in_array($candidateId, $selectedIds, true)) {
                continue;
            }
            $ratio = (float) ($candidate['fit_ratio'] ?? 0.0);
            if ($ratio >= (float) $slot['min'] && $ratio <= (float) $slot['max']) {
                $picked = $candidate;
                break;
            }
        }
        if ($picked === null) {
            foreach ($routes as $candidate) {
                $candidateId = (int) ($candidate['id'] ?? 0);
                if ($candidateId > 0 && !in_array($candidateId, $selectedIds, true)) {
                    $picked = $candidate;
                    break;
                }
            }
        }
        if ($picked === null) {
            continue;
        }

        $picked['slot_label'] = $slot['label'];
        $picked['slot_summary'] = $slot['summary'];
        $selectedIds[] = (int) $picked['id'];
        $cards[] = $picked;
        if (count($cards) >= $limit) {
            break;
        }
    }

    if (count($cards) < $limit) {
        foreach ($routes as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId <= 0 || in_array($candidateId, $selectedIds, true)) {
                continue;
            }
            $candidate['slot_label'] = 'Extra';
            $candidate['slot_summary'] = 'Opcion complementaria para ampliar la semana si te ves con margen.';
            $selectedIds[] = $candidateId;
            $cards[] = $candidate;
            if (count($cards) >= $limit) {
                break;
            }
        }
    }

    $totalDistance = 0.0;
    $totalElevation = 0.0;
    foreach ($cards as $card) {
        $totalDistance += (float) ($card['distance_km'] ?? 0.0);
        $totalElevation += (float) ($card['elevation_m'] ?? 0.0);
    }

    return [
        'cards' => $cards,
        'total_distance_km' => $totalDistance,
        'total_elevation_m' => $totalElevation,
        'summary' => 'Semana premium equilibrada: una ruta base, una de progresion y una de reto para que la suscripcion te ayude a planificar de verdad.',
    ];
}

function similar_routes(PDO $pdo, array $route, int $limit = 3): array
{
    $limit = max(1, min(8, $limit));
    $stmt = $pdo->prepare('
        SELECT
            r.id,
            r.name,
            r.zone,
            r.difficulty,
            r.activity_type,
            r.distance_km,
            r.base_points,
            r.cover_image,
            COALESCE(cstats.avg_rating, 0) AS avg_rating,
            COALESCE(rcstats.total_completions, 0) AS total_completions,
            (
                CASE WHEN r.zone = :score_zone THEN 45 ELSE 0 END +
                CASE WHEN r.activity_type = :score_activity_type THEN 30 ELSE 0 END +
                CASE WHEN r.difficulty = :score_difficulty THEN 20 ELSE 0 END +
                ROUND(COALESCE(cstats.avg_rating, 0) * 10, 0) +
                LEAST(COALESCE(rcstats.total_completions, 0), 30)
            ) AS similarity_score
        FROM routes r
        LEFT JOIN (
            SELECT route_id, AVG(rating) AS avg_rating
            FROM comments
            WHERE status = "approved"
            GROUP BY route_id
        ) cstats ON cstats.route_id = r.id
        LEFT JOIN (
            SELECT route_id, COUNT(*) AS total_completions
            FROM route_completions
            GROUP BY route_id
        ) rcstats ON rcstats.route_id = r.id
        WHERE r.submission_status = "approved"
          AND r.id <> :route_id
          AND (
                r.zone = :filter_zone
                OR r.activity_type = :filter_activity_type
                OR r.difficulty = :filter_difficulty
          )
        ORDER BY similarity_score DESC, r.created_at DESC
        LIMIT :limit
    ');
    $zone = (string) ($route['zone'] ?? '');
    $activityType = (string) ($route['activity_type'] ?? '');
    $difficulty = (string) ($route['difficulty'] ?? '');

    $stmt->bindValue(':score_zone', $zone, PDO::PARAM_STR);
    $stmt->bindValue(':score_activity_type', $activityType, PDO::PARAM_STR);
    $stmt->bindValue(':score_difficulty', $difficulty, PDO::PARAM_STR);
    $stmt->bindValue(':filter_zone', $zone, PDO::PARAM_STR);
    $stmt->bindValue(':filter_activity_type', $activityType, PDO::PARAM_STR);
    $stmt->bindValue(':filter_difficulty', $difficulty, PDO::PARAM_STR);
    $stmt->bindValue(':route_id', (int) ($route['id'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function user_activity_insights(PDO $pdo, int $userId, int $level = 1): array
{
    $datesStmt = $pdo->prepare('
        SELECT DISTINCT DATE(completed_at) AS completion_date
        FROM route_completions
        WHERE user_id = :user_id
        ORDER BY completion_date DESC
    ');
    $datesStmt->execute([':user_id' => $userId]);
    $dateRows = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

    $completionDates = [];
    foreach ($dateRows as $dateRow) {
        try {
            $completionDates[] = new DateTimeImmutable((string) $dateRow);
        } catch (Throwable) {
            continue;
        }
    }

    $bestStreak = 0;
    $run = 0;
    $previousDate = null;
    foreach ($completionDates as $date) {
        if ($previousDate === null) {
            $run = 1;
        } else {
            $diffDays = (int) $previousDate->diff($date)->days;
            $run = $diffDays === 1 ? $run + 1 : 1;
        }
        $bestStreak = max($bestStreak, $run);
        $previousDate = $date;
    }

    $today = new DateTimeImmutable('today');
    $yesterday = $today->modify('-1 day');
    $currentStreak = 0;
    if (!empty($completionDates)) {
        $latestDate = $completionDates[0];
        $latestKey = $latestDate->format('Y-m-d');
        if ($latestKey === $today->format('Y-m-d') || $latestKey === $yesterday->format('Y-m-d')) {
            $currentStreak = 1;
            $previousDate = $latestDate;
            for ($index = 1, $max = count($completionDates); $index < $max; $index++) {
                $diffDays = (int) $previousDate->diff($completionDates[$index])->days;
                if ($diffDays !== 1) {
                    break;
                }
                $currentStreak++;
                $previousDate = $completionDates[$index];
            }
        }
    }

    $recentStmt = $pdo->prepare('
        SELECT
            SUM(CASE WHEN rc.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS routes_7d,
            SUM(CASE WHEN rc.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS routes_30d,
            COALESCE(SUM(CASE WHEN rc.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN r.distance_km ELSE 0 END), 0) AS km_7d,
            COALESCE(SUM(CASE WHEN rc.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN r.distance_km ELSE 0 END), 0) AS km_30d,
            COALESCE(SUM(CASE WHEN YEAR(rc.completed_at) = YEAR(CURDATE()) AND MONTH(rc.completed_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END), 0) AS routes_month,
            COALESCE(SUM(CASE WHEN YEAR(rc.completed_at) = YEAR(CURDATE()) AND MONTH(rc.completed_at) = MONTH(CURDATE()) THEN r.distance_km ELSE 0 END), 0) AS km_month,
            MAX(rc.completed_at) AS last_completion_at
        FROM route_completions rc
        JOIN routes r ON r.id = rc.route_id
        WHERE rc.user_id = :user_id
    ');
    $recentStmt->execute([':user_id' => $userId]);
    $recent = $recentStmt->fetch() ?: [];

    $goalRoutes = max(4, min(14, $level + 2));
    $goalKm = max(30.0, $goalRoutes * 7.5);
    $routesMonth = (int) ($recent['routes_month'] ?? 0);
    $kmMonth = (float) ($recent['km_month'] ?? 0);

    return [
        'current_streak' => $currentStreak,
        'best_streak' => $bestStreak,
        'routes_7d' => (int) ($recent['routes_7d'] ?? 0),
        'routes_30d' => (int) ($recent['routes_30d'] ?? 0),
        'km_7d' => (float) ($recent['km_7d'] ?? 0),
        'km_30d' => (float) ($recent['km_30d'] ?? 0),
        'routes_month' => $routesMonth,
        'km_month' => $kmMonth,
        'goal_routes' => $goalRoutes,
        'goal_km' => $goalKm,
        'routes_month_percent' => min(100, (int) round(($routesMonth / max(1, $goalRoutes)) * 100)),
        'km_month_percent' => min(100, (int) round(($kmMonth / max(1, $goalKm)) * 100)),
        'last_completion_at' => (string) ($recent['last_completion_at'] ?? ''),
    ];
}

function blog_posts_catalog(): array
{
    return [
        [
            'slug' => 'preparar-ruta-asturias-sin-sorpresas',
            'title' => 'Como preparar una ruta en Asturias sin llevarte sorpresas',
            'category' => 'Planificacion',
            'read_time' => '6 min',
            'excerpt' => 'Una guia practica para revisar distancia, desnivel, meteorologia y descarga offline antes de salir al monte.',
            'description' => 'Aprende a preparar una ruta en Asturias revisando distancia, desnivel, meteorologia y material para reducir imprevistos.',
            'published_at' => '2026-04-18',
            'updated_at' => '2026-04-18',
            'tags' => ['seguridad', 'planificacion', 'senderismo'],
            'sections' => [
                [
                    'heading' => 'Empieza por la ficha tecnica',
                    'paragraphs' => [
                        'Antes de fijarte en lo bonita que parece una ruta, revisa tres datos: distancia total, desnivel positivo y dificultad declarada. Es la manera mas rapida de saber si la salida encaja con tu momento fisico y con el tiempo que tienes.',
                        'En Asturias hay recorridos cortos que se vuelven serios por el desnivel o por el terreno. Por eso conviene leer la ruta completa y no decidir solo por el kilometraje.',
                    ],
                ],
                [
                    'heading' => 'No salgas sin una version offline',
                    'paragraphs' => [
                        'La cobertura no siempre acompana, sobre todo en montaña o en tramos boscosos. Llevar una descarga offline evita perder tiempo y reduce errores de navegacion.',
                    ],
                    'bullets' => [
                        'Descarga GPX si usas una app de senderismo.',
                        'Si eres premium, lleva tambien KML, GeoJSON o el roadbook listo para PDF.',
                        'Guarda el enlace de la ficha por si necesitas volver a consultar consejos o incidencias.',
                    ],
                ],
                [
                    'heading' => 'Meteo, horario y plan B',
                    'paragraphs' => [
                        'Planificar bien no es solo mirar el pronostico. Tambien conviene pensar a que hora empiezas, cuanto margen de luz tienes y en que punto te darias la vuelta si el dia se complica.',
                        'Una web de rutas es mucho mas util cuando no solo inspira, sino que ayuda a decidir con criterio. Esa es una de las ideas base de Reto Asturias Activa.',
                    ],
                ],
            ],
        ],
        [
            'slug' => 'que-llevar-ruta-montana-asturias',
            'title' => 'Que llevar en una ruta de montaña por Asturias',
            'category' => 'Equipo',
            'read_time' => '5 min',
            'excerpt' => 'El material basico que cambia de verdad una salida: agua, capas, comida, bateria y una navegacion preparada.',
            'description' => 'Checklist de material esencial para rutas de montaña por Asturias con foco en seguridad y autonomia.',
            'published_at' => '2026-04-14',
            'updated_at' => '2026-04-14',
            'tags' => ['equipo', 'seguridad', 'montana'],
            'sections' => [
                [
                    'heading' => 'Lo imprescindible',
                    'paragraphs' => [
                        'Hay cosas que no dependen de si la ruta parece facil o dificil: agua suficiente, bateria cargada, algo de comida y ropa que aguante un cambio de tiempo. En Asturias el ambiente puede cambiar rapido y eso afecta al confort y a la seguridad.',
                    ],
                    'bullets' => [
                        'Agua y algo de comida que no necesite preparacion.',
                        'Capa impermeable o cortavientos aunque el dia pinte bien.',
                        'Movil cargado y, si puedes, bateria externa.',
                        'Ruta descargada o roadbook guardado offline.',
                    ],
                ],
                [
                    'heading' => 'No copies el equipo de otra persona sin contexto',
                    'paragraphs' => [
                        'Una salida costera corta no pide lo mismo que una ruta con mucho desnivel. Por eso la mejor recomendacion siempre parte del terreno, la duracion y tu experiencia real.',
                        'La propia ficha de cada ruta deberia ayudarte a aterrizar esa decision. Cuanto mas personalizado sea el consejo, mas valor tiene la plataforma.',
                    ],
                ],
            ],
        ],
        [
            'slug' => 'primavera-rutas-asturias',
            'title' => 'Primavera en Asturias: como elegir bien tus rutas',
            'category' => 'Temporada',
            'read_time' => '4 min',
            'excerpt' => 'La primavera es una gran epoca para salir, pero conviene distinguir rutas costeras, valles y montaña alta.',
            'description' => 'Consejos para elegir rutas de primavera en Asturias según zona, barro, desnivel y cambios de meteo.',
            'published_at' => '2026-04-10',
            'updated_at' => '2026-04-10',
            'tags' => ['primavera', 'asturias', 'temporada'],
            'sections' => [
                [
                    'heading' => 'No todas las zonas se comportan igual',
                    'paragraphs' => [
                        'En primavera, la costa y los valles suelen ofrecer mejores condiciones antes que la montaña alta. Eso no significa que la montaña no sea viable, sino que exige leer mejor el contexto y no salir con mentalidad de verano.',
                        'Elegir bien la zona ya es media preparacion hecha. Una web centrada en Asturias gana mucho cuando te orienta por territorio y no solo por nombre de ruta.',
                    ],
                ],
                [
                    'heading' => 'Que mirar antes de decidir',
                    'bullets' => [
                        'Si ha llovido varios dias, evita subestimar barro y resbalones.',
                        'Prioriza rutas con escapatoria sencilla si vas retomando ritmo.',
                        'Valora orientacion y desnivel, no solo fotos o popularidad.',
                    ],
                ],
            ],
        ],
        [
            'slug' => 'usar-gpx-kml-roadbook-salidas',
            'title' => 'Como usar GPX, KML y roadbooks en tus salidas',
            'category' => 'Herramientas',
            'read_time' => '7 min',
            'excerpt' => 'Cuando cada formato tiene sentido y por que una buena descarga puede cambiar por completo la experiencia de una ruta.',
            'description' => 'Explicacion simple de los formatos GPX, KML, GeoJSON y roadbook para sacar mas partido a las rutas.',
            'published_at' => '2026-04-06',
            'updated_at' => '2026-04-06',
            'tags' => ['gpx', 'kml', 'roadbook'],
            'sections' => [
                [
                    'heading' => 'GPX para la mayoria de usuarios',
                    'paragraphs' => [
                        'Si solo quieres seguir una ruta en una app de senderismo, GPX suele ser suficiente. Es el formato mas directo para llevar el trazado y moverte con el movil.',
                    ],
                ],
                [
                    'heading' => 'KML y GeoJSON para ir un paso mas alla',
                    'paragraphs' => [
                        'KML va muy bien con visores como Google Earth, mientras que GeoJSON es mas tecnico y flexible para editar o reutilizar datos geograficos.',
                        'En una plataforma con modelo premium, estos formatos tienen sentido como ventaja de usuario avanzado porque resuelven necesidades reales, no cosmeticas.',
                    ],
                ],
                [
                    'heading' => 'El roadbook como herramienta practica',
                    'paragraphs' => [
                        'Un roadbook bien hecho resume los datos clave, marca hitos y se puede imprimir o guardar como PDF. Es perfecto para quien quiere llevar la ruta bien preparada, incluso sin depender del movil a cada momento.',
                    ],
                ],
            ],
        ],
    ];
}

function latest_blog_posts(int $limit = 3): array
{
    $limit = max(1, min(12, $limit));
    return array_slice(blog_posts_catalog(), 0, $limit);
}

function blog_post_by_slug(string $slug): ?array
{
    $target = trim($slug);
    foreach (blog_posts_catalog() as $post) {
        if ((string) ($post['slug'] ?? '') === $target) {
            return $post;
        }
    }

    return null;
}

function render_header(string $title, array $options = []): void
{
    $user = current_user();
    $unreadNotifications = $user ? unread_notifications_count((int) $user['id']) : 0;
    $flash = get_flash();
    $description = meta_text((string) ($options['description'] ?? site_default_description()));
    $canonicalUrl = absolute_url((string) ($options['canonical'] ?? current_request_path()));
    $robots = trim((string) ($options['robots'] ?? 'index,follow'));
    $shareImage = meta_image_url((string) ($options['image'] ?? ''));
    $ogType = trim((string) ($options['type'] ?? 'website'));
    $jsonLdInput = $options['json_ld'] ?? [];
    $jsonLdItems = [];
    if (is_array($jsonLdInput)) {
        $jsonLdItems = array_is_list($jsonLdInput) ? $jsonLdInput : [$jsonLdInput];
    }
    $currentPath = current_request_path();
    $usesInteractiveMapAssets = page_uses_interactive_map_assets($currentPath);
    $favoritesView = $currentPath === 'search.php' && (int) ($_GET['only_favorites'] ?? 0) === 1;
    $blogView = in_array($currentPath, ['blog.php', 'blog_post.php'], true);
    $stylesheetPath = 'assets/css/styles.css';
    $stylesheetVersion = is_file(__DIR__ . '/' . $stylesheetPath)
        ? (string) filemtime(__DIR__ . '/' . $stylesheetPath)
        : (string) time();
    $navClass = static function (bool $active, array $extra = []): string {
        $classes = array_merge(['nav-link'], $extra);
        if ($active) {
            $classes[] = 'is-active';
        }

        return implode(' ', array_filter($classes));
    };
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
    <meta property="og:locale" content="es_ES">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($title . ' | ' . APP_NAME) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($shareImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title . ' | ' . APP_NAME) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($shareImage) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/logo-raa.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;800&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <?php if ($usesInteractiveMapAssets): ?>
        <link rel="preconnect" href="https://unpkg.com" crossorigin>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(url($stylesheetPath . '?v=' . $stylesheetVersion)) ?>">
    <?php foreach ($jsonLdItems as $jsonLd): ?>
        <?php if (is_array($jsonLd) && !empty($jsonLd)): ?>
            <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true">
                    <img src="<?= e(url('assets/img/logo-raa.svg')) ?>" alt="">
                </span>
                <span class="brand-name"><?= e(APP_NAME) ?></span>
            </a>
            <nav class="main-nav" aria-label="Navegacion principal">
                <a class="<?= e($navClass($currentPath === 'map.php', ['nav-link-accent'])) ?>" href="<?= e(url('map.php')) ?>">Mapa</a>
                <a class="<?= e($navClass($currentPath === 'search.php' && !$favoritesView)) ?>" href="<?= e(url('search.php')) ?>">Busqueda</a>
                <a class="<?= e($navClass($currentPath === 'rankings.php')) ?>" href="<?= e(url('rankings.php')) ?>">Ranking</a>
                <a class="<?= e($navClass($currentPath === 'challenges.php')) ?>" href="<?= e(url('challenges.php')) ?>">Retos</a>
                <a class="<?= e($navClass($blogView)) ?>" href="<?= e(url('blog.php')) ?>">Blog</a>
                <?php if ($user): ?>
                    <a class="<?= e($navClass($currentPath === 'dashboard.php')) ?>" href="<?= e(url('dashboard.php')) ?>">Mi Panel</a>
                    <a class="<?= e($navClass($currentPath === 'profile.php')) ?>" href="<?= e(url('profile.php')) ?>">Perfil</a>
                    <a class="<?= e($navClass($currentPath === 'premium.php', ['nav-link-premium'])) ?>" href="<?= e(url('premium.php')) ?>">
                        Premium
                        <?php if (user_has_active_premium($user)): ?>
                            <span class="nav-badge">Activo</span>
                        <?php endif; ?>
                    </a>
                    <a class="<?= e($navClass($currentPath === 'notifications.php')) ?>" href="<?= e(url('notifications.php')) ?>">
                        Notificaciones
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="nav-count"><?= (int) $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (user_can_submit_routes($user)): ?>
                        <a class="<?= e($navClass($currentPath === 'submit_route.php')) ?>" href="<?= e(url('submit_route.php')) ?>">Proponer ruta</a>
                    <?php else: ?>
                        <a class="<?= e($navClass(false, ['nav-link-premium'])) ?>" href="<?= e(url('premium.php#premium-benefits')) ?>">
                            Proponer ruta
                            <span class="nav-badge">Premium</span>
                        </a>
                    <?php endif; ?>
                    <a class="<?= e($navClass($favoritesView)) ?>" href="<?= e(url('search.php?only_favorites=1')) ?>">Favoritas</a>
                    <?php if ((int) $user['is_admin'] === 1): ?>
                        <a class="<?= e($navClass($currentPath === 'admin.php', ['nav-link-utility'])) ?>" href="<?= e(url('admin.php')) ?>">Admin</a>
                    <?php endif; ?>
                    <a class="<?= e($navClass($currentPath === 'logout.php', ['nav-link-exit'])) ?>" href="<?= e(url('logout.php')) ?>">Salir</a>
                <?php else: ?>
                    <a class="<?= e($navClass($currentPath === 'login.php', ['nav-auth-link'])) ?>" href="<?= e(url('login.php')) ?>">Entrar</a>
                    <a class="button button-small nav-button nav-register-link" href="<?= e(url('register.php')) ?>">Crear cuenta</a>
                <?php endif; ?>
            </nav>
        </header>
        <main class="main-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>">
                    <?= flash_message_html((string) $flash['message']) ?>
                </div>
            <?php endif; ?>
    <?php
}

function render_footer(): void
{
    $usesInteractiveMapAssets = page_uses_interactive_map_assets();
    $scriptPath = 'assets/js/app.js';
    $scriptVersion = is_file(__DIR__ . '/' . $scriptPath)
        ? (string) filemtime(__DIR__ . '/' . $scriptPath)
        : (string) time();
    ?>
        </main>
        <footer class="site-footer">
            <div class="footer-grid">
                <section>
                    <h3>Reto Asturias Activa</h3>
                    <p>Proyecto web centrado en rutas de Asturias, gamificacion y una experiencia digital util para planificar, descubrir y compartir actividad al aire libre.</p>
                </section>
                <section>
                    <h3>Proyecto</h3>
                    <div class="footer-links">
                        <a href="<?= e(url('about.php')) ?>">Sobre nosotros</a>
                        <a href="<?= e(url('map.php')) ?>">Mapa general</a>
                        <a href="<?= e(url('search.php')) ?>">Busqueda</a>
                        <a href="<?= e(url('premium.php')) ?>">Premium</a>
                        <a href="<?= e(url('blog.php')) ?>">Blog</a>
                    </div>
                </section>
                <section>
                    <h3>Legal</h3>
                    <div class="footer-links">
                        <a href="<?= e(url('legal.php')) ?>">Aviso legal</a>
                        <a href="<?= e(url('privacy.php')) ?>">Privacidad</a>
                        <a href="<?= e(url('cookies.php')) ?>">Cookies</a>
                        <a href="<?= e(url('terms.php')) ?>">Terminos</a>
                    </div>
                </section>
                <section>
                    <h3>Recursos</h3>
                    <div class="footer-links">
                        <a href="<?= e(url('rankings.php')) ?>">Ranking</a>
                        <a href="<?= e(url('challenges.php')) ?>">Retos</a>
                    </div>
                </section>
            </div>
            <p class="footer-meta">Reto Asturias Activa - Proyecto DAW - <?= date('Y') ?></p>
        </footer>
    </div>
    <?php if ($usesInteractiveMapAssets): ?>
        <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script defer src="<?= e(url($scriptPath . '?v=' . $scriptVersion)) ?>"></script>
    <?php endif; ?>
</body>
</html>
    <?php
}
