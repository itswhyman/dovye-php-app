<?php
declare(strict_types=1);

/**
 * DOVYE STUDIO — Mega Cyber Engine v5.0
 */

define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'dovye-mysql'));
define('DB_PORT', getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: '3306'));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'lead_panel')));
define('DB_USER', getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('MYSQL_USER') ?: 'root')));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: '')));

define('DEFAULT_ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('DEFAULT_ADMIN_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$/C.2ixjjyh33BfhAMO2Y4.hk/qQFlv6doCLZASlWGP1zHZGtO3ioi');

date_default_timezone_set('Europe/Istanbul');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('dovye_v5_session');
    session_start();
}

function db(): ?PDO
{
    static $pdo = null;
    static $failed = false;

    if ($failed) return null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'",
            PDO::ATTR_TIMEOUT            => 3,
        ]);

        // Auto Schema Migration Check to prevent 1054 Unknown Column error
        auto_migrate_schema($pdo);

        return $pdo;
    } catch (PDOException $e) {
        $failed = true;
        error_log('DB Error: ' . $e->getMessage());
        return null;
    }
}

function auto_migrate_schema(PDO $pdo): void
{
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM live_cams LIKE 'provider'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE live_cams ADD COLUMN provider VARCHAR(50) NOT NULL DEFAULT 'Skyline / YouTube' AFTER embed_url");
        }
    } catch (Throwable $t) {}

    try {
        $colsAdmin = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'theme_pref'")->fetchAll();
        if (empty($colsAdmin)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN theme_pref VARCHAR(20) NOT NULL DEFAULT 'dark'");
        }
    } catch (Throwable $t) {}
}

function log_system_action(string $action, string $details = ''): void
{
    $pdo = db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO system_logs (action, details, ip_address) VALUES (:act, :det, :ip)');
        $stmt->execute([':act' => $action, ':det' => $details, ':ip' => client_ip()]);
    } catch (Throwable $t) {}
}

function parse_youtube_id(string $url): ?string
{
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\x2F|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $match)) {
        return $match[1];
    }
    return null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf'] ?? '', $sent);
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $isPrivate = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($isPrivate && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }
    return $remote;
}
