<?php
declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'dovye-mysql'));
define('DB_PORT', getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: '3306'));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'lead_panel')));
define('DB_USER', getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('MYSQL_USER') ?: 'root')));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: '')));

date_default_timezone_set('Europe/Istanbul');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (session_status() === PHP_SESSION_NONE) {
    session_name('kralagario_v11_session');
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
            PDO::ATTR_TIMEOUT            => 3,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        $failed = true;
        return null;
    }
}

function get_game_settings(): array {
    $defaults = [
        'game_speed' => '1.4',
        'growth_rate' => '1.0',
        'max_split_cells' => '16',
        'map_size' => '8000',
        'remerge_time_sec' => '10',
        'rgb_speed' => '2'
    ];
    $pdo = db();
    if (!$pdo) return $defaults;

    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM game_settings")->fetchAll();
        foreach ($rows as $r) {
            $defaults[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Throwable $t) {}

    return $defaults;
}

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
