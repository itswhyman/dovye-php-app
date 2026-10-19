<?php
declare(strict_types=1);

/**
 * Ortak yapılandırma: PDO bağlantısı, oturum ayarları, güvenlik başlıkları ve yardımcılar.
 * Üretimde değerleri ortam değişkenlerinden (getenv) verin; bu dosyayı web kökü dışında tutmanız önerilir.
 */

// ---- Veritabanı ----------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'lead_panel');
define('DB_USER', getenv('DB_USER') ?: 'lead_user');
define('DB_PASS', getenv('DB_PASS') ?: 'change-this-db-password');

// ---- Yönetici girişi -----------------------------------------------------
// Varsayılan kullanıcı: admin  |  varsayılan şifre: ChangeMe!2026  (İLK İŞ DEĞİŞTİRİN)
// Yeni hash üretmek için:  php -r "echo password_hash('yeni-sifreniz', PASSWORD_DEFAULT), PHP_EOL;"
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$/C.2ixjjyh33BfhAMO2Y4.hk/qQFlv6doCLZASlWGP1zHZGtO3ioi');

// ---- Uygulama sabitleri --------------------------------------------------
const SERVICES = [
    'web'       => 'Web Tasarım',
    'seo'       => 'SEO & Analitik',
    'mobile'    => 'Mobil Uygulama',
    'consult'   => 'Danışmanlık',
    'other'     => 'Diğer',
];

const STATUSES = [
    'new'       => 'Yeni',
    'read'      => 'Okundu',
    'contacted' => 'İletişime Geçildi',
];

date_default_timezone_set('Europe/Istanbul');

// ---- Güvenlik başlıkları -------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

// ---- Oturum --------------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_name('lp_session');
session_start();

// ---- PDO -----------------------------------------------------------------
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
            PDO::ATTR_EMULATE_PREPARES   => false, // gerçek prepared statement
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'", // PHP saat dilimiyle uyumlu (Europe/Istanbul)
        ]);
    } catch (PDOException $e) {
        error_log('DB bağlantı hatası: ' . $e->getMessage());
        http_response_code(500);
        exit('Veritabanına şu anda ulaşılamıyor. Lütfen daha sonra tekrar deneyin.');
    }

    return $pdo;
}

// ---- Yardımcılar ---------------------------------------------------------

/** HTML çıktısı için kaçış (XSS koruması). */
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

/** Tek seferlik mesaj (flash). */
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
