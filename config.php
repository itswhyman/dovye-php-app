<?php
declare(strict_types=1);

/**
 * DOVYE Studio — Core Engine & Configuration
 */

// ---- Veritabanı Yapılandırması (Coolify & Standalone Uyumlu) -------------
define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'dovye-mysql'));
define('DB_PORT', getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: '3306'));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'lead_panel')));
define('DB_USER', getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('MYSQL_USER') ?: 'root')));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: '')));

// ---- Yönetici Girişi -----------------------------------------------------
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$/C.2ixjjyh33BfhAMO2Y4.hk/qQFlv6doCLZASlWGP1zHZGtO3ioi'); // Default: ChangeMe!2026

// ---- Hizmetler ve Durumlar -----------------------------------------------
const SERVICES = [
    'web'       => 'Web & Mobile App Development',
    'uiux'      => 'UI/UX Design & Brand System',
    'seo'       => 'SEO & Performance Growth',
    'consult'   => 'Cloud & DevOps Architecture',
    'other'     => 'Özel Yazılım Çözümleri',
];

const STATUSES = [
    'new'       => 'Yeni Aday',
    'read'      => 'İncelendi',
    'contacted' => 'İletişime Geçildi',
];

date_default_timezone_set('Europe/Istanbul');

// ---- Security Headers ---------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---- Session Configuration ----------------------------------------------
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
    session_name('dovye_session');
    session_start();
}

// ---- PDO Connection Helper ----------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'",
        ]);
    } catch (PDOException $e) {
        error_log('DB Connection Error: ' . $e->getMessage());
        http_response_code(500);
        die('
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Sistem Bağlantı Bekleniyor | DOVYE Studio</title>
            <style>
                body { background: #090d16; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; text-align: center; }
                .card { background: #131b2e; padding: 2.5rem; border-radius: 20px; border: 1px solid #1e293b; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
                h1 { color: #38bdf8; font-size: 1.5rem; margin-bottom: 0.5rem; font-weight: 700; }
                p { color: #94a3b8; font-size: 0.95rem; line-height: 1.6; }
                .badge { display: inline-flex; align-items: center; gap: 6px; padding: 0.35rem 0.85rem; background: rgba(239, 68, 68, 0.15); color: #f87171; border-radius: 99px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1.25rem; border: 1px solid rgba(239, 68, 68, 0.2); }
            </style>
        </head>
        <body>
            <div class="card">
                <span class="badge">⚠️ Veritabanı Bağlantısı Kesildi</span>
                <h1>DOVYE Studio Altyapısı</h1>
                <p>Veritabanı sunucusuna erişilemiyor. Lütfen MariaDB konteynerinizin aktif olduğundan ve env değişkenlerinin doğru tanımlandığından emin olun.</p>
            </div>
        </body>
        </html>
        ');
    }

    return $pdo;
}

// ---- Helpers ------------------------------------------------------------
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
