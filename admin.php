<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

const IDLE_TIMEOUT = 1800;
const MAX_FAILS    = 5;
const LOCK_SECONDS = 300;
const PER_PAGE     = 15;

function read_filters(array $src, string $p = ''): array
{
    $status = $src[$p . 'status'] ?? '';
    $status = (is_string($status) && array_key_exists($status, STATUSES)) ? $status : '';

    $q = $src[$p . 'q'] ?? '';
    $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

    $page = $src[$p . 'page'] ?? 1;
    $page = max(1, (int) (is_scalar($page) ? $page : 1));

    $tab = $src['tab'] ?? 'dashboard';
    $validTabs = ['dashboard', 'leads', 'analytics', 'database', 'system'];
    $tab = in_array($tab, $validTabs, true) ? $tab : 'dashboard';

    return ['status' => $status, 'q' => $q, 'page' => $page, 'tab' => $tab];
}

function admin_url(array $f): string
{
    $query = [];
    if (($f['tab'] ?? 'dashboard') !== 'dashboard') { $query['tab'] = $f['tab']; }
    if (($f['status'] ?? '') !== '')                 { $query['status'] = $f['status']; }
    if (($f['q'] ?? '') !== '')                     { $query['q'] = $f['q']; }
    if (($f['page'] ?? 1) > 1)                      { $query['page'] = $f['page']; }
    return 'admin.php' . ($query ? '?' . http_build_query($query) : '');
}

function is_logged_in(): bool
{
    if (($_SESSION['admin'] ?? false) !== true) {
        return false;
    }
    if (time() - (int) ($_SESSION['last_seen'] ?? 0) > IDLE_TIMEOUT) {
        return false;
    }
    $_SESSION['last_seen'] = time();
    return true;
}

$loginError = null;
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';

if ($action === 'login') {
    $lockedUntil = (int) ($_SESSION['lock_until'] ?? 0);

    if (!csrf_valid()) {
        $loginError = 'Oturum süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif ($lockedUntil > time()) {
        $loginError = 'Çok fazla hatalı deneme. Lütfen ' . ceil(($lockedUntil - time()) / 60) . ' dakika sonra tekrar deneyin.';
    } else {
        $user = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $userOk = hash_equals(ADMIN_USER, $user);
        $passOk = password_verify($pass, ADMIN_PASSWORD_HASH);

        if ($userOk && $passOk) {
            session_regenerate_id(true);
            unset($_SESSION['csrf'], $_SESSION['login_fails'], $_SESSION['lock_until']);
            $_SESSION['admin'] = true;
            $_SESSION['last_seen'] = time();
            redirect('admin.php');
        }

        usleep(300000);
        $fails = (int) ($_SESSION['login_fails'] ?? 0) + 1;
        $_SESSION['login_fails'] = $fails;
        if ($fails >= MAX_FAILS) {
            $_SESSION['lock_until'] = time() + LOCK_SECONDS;
            $_SESSION['login_fails'] = 0;
        }
        $loginError = 'Geçersiz kullanıcı adı veya şifre.';
    }
}

if ($action === 'logout') {
    if (csrf_valid()) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $c = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $c['path'],
                'secure'   => $c['secure'],
                'httponly' => $c['httponly'],
                'samesite' => $c['samesite'],
            ]);
        }
        session_destroy();
    }
    redirect('admin.php');
}

$pdo = db();

if ($action === 'status') {
    if (!is_logged_in()) { redirect('admin.php'); }
    $filters = read_filters($_POST, 'f_');
    $back = admin_url($filters);

    $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';

    if (!csrf_valid()) {
        flash('err', 'Güvenlik doğrulaması başarısız.');
    } elseif (!$pdo) {
        flash('err', 'Veritabanı bağlantısı yok.');
    } elseif ($id === false || $id < 1 || !array_key_exists($status, STATUSES)) {
        flash('err', 'Geçersiz talep.');
    } else {
        $stmt = $pdo->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        flash('ok', "#{$id} numaralı kayıt durumu güncellendi: " . STATUSES[$status]);
    }
    redirect($back);
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!is_logged_in() || !$pdo) { redirect('admin.php'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dovye_leads_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Ad Soyad', 'E-Posta', 'Hizmet', 'Mesaj', 'Durum', 'IP', 'Tarih']);
    $stmt = $pdo->query('SELECT id, name, email, service, message, status, ip_address, created_at FROM leads ORDER BY id DESC');
    while ($row = $stmt->fetch()) {
        $row['service'] = SERVICES[$row['service']] ?? $row['service'];
        $row['status']  = STATUSES[$row['status']] ?? $row['status'];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ---------------------------------------------------------------------------
// Login Screen View
// ---------------------------------------------------------------------------
if (!is_logged_in()) {
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Yönetim Paneli Girişi — DOVYE Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<div class="ambient-glow"><div class="orb orb-1"></div></div>

<main class="login-wrapper">
    <div class="glass-card login-card">
        <div class="login-brand">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
            <span>DOVYE CORE OS</span>
        </div>

        <h2>Yönetici Girişi</h2>
        <p class="login-desc">Sistem kontrol merkezine erişmek için doğrulama yapınız.</p>

        <?php if ($loginError): ?>
            <div class="alert alert--err" role="alert">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><?= e($loginError) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="field">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" required autofocus placeholder="admin">
            </div>
            <div class="field">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn--primary btn--full">
                <span>Giriş Yap</span>
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </button>
        </form>
    </div>
</main>
</body>
</html>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// Dashboard Views & Data Gathering
// ---------------------------------------------------------------------------

$filters = read_filters($_GET);
$activeTab = $filters['tab'];

// Default Stats
$byStatus = array_fill_keys(array_keys(STATUSES), 0);
$total = 0;
$byService = [];
$maxService = 1;
$perDay = [];
for ($i = 6; $i >= 0; $i--) {
    $perDay[(new DateTimeImmutable("-{$i} days"))->format('Y-m-d')] = 0;
}
$last7 = 0;
$maxDay = 1;
$leads = [];
$matched = 0;
$pages = 1;
$offset = 0;

if ($pdo) {
    try {
        foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM leads GROUP BY status') as $row) {
            $byStatus[$row['status']] = (int) $row['c'];
        }
        $total = array_sum($byStatus);

        foreach ($pdo->query('SELECT service, COUNT(*) AS c FROM leads GROUP BY service ORDER BY c DESC') as $row) {
            $byService[$row['service']] = (int) $row['c'];
        }
        $maxService = $byService ? max($byService) : 1;

        $stmt7 = $pdo->query('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM leads WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(created_at)');
        foreach ($stmt7 as $row) {
            if (isset($perDay[$row['d']])) {
                $perDay[$row['d']] = (int) $row['c'];
            }
        }
        $last7 = array_sum($perDay);
        $maxDay = max(1, max($perDay));

        // Leads Query
        $where = [];
        $params = [];
        if ($filters['status'] !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if ($filters['q'] !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(name LIKE :q1 OR email LIKE :q2 OR message LIKE :q3)';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM leads' . $whereSql);
        $countStmt->execute($params);
        $matched = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($matched / PER_PAGE));
        $filters['page'] = min($filters['page'], $pages);
        $offset = ($filters['page'] - 1) * PER_PAGE;

        $listStmt = $pdo->prepare(
            'SELECT id, name, email, service, message, status, ip_address, created_at
             FROM leads' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $k => $v) {
            $listStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $listStmt->bindValue(':limit', PER_PAGE, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $leads = $listStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Dashboard query error: " . $e->getMessage());
    }
}

$flash = flash();
$tabLink = static fn (string $t): string => admin_url(['tab' => $t, 'status' => '', 'q' => '', 'page' => 1]);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Yönetim Konsolu — DOVYE Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <svg class="svg-icon brand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
        <div class="brand-names">
            <strong>DOVYE<span>CORE</span></strong>
            <small>Enterprise OS v2.5</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= e($tabLink('dashboard')) ?>" class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>Genel Bakış</span>
        </a>
        <a href="<?= e($tabLink('leads')) ?>" class="nav-item <?= $activeTab === 'leads' ? 'active' : '' ?>">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Müşteri Talepleri</span>
            <?php if ($byStatus['new'] > 0): ?>
                <span class="badge-count"><?= $byStatus['new'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= e($tabLink('analytics')) ?>" class="nav-item <?= $activeTab === 'analytics' ? 'active' : '' ?>">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>Analitik & Raporlar</span>
        </a>
        <a href="<?= e($tabLink('database')) ?>" class="nav-item <?= $activeTab === 'database' ? 'active' : '' ?>">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <span>Veritabanı Durumu</span>
        </a>
        <a href="<?= e($tabLink('system')) ?>" class="nav-item <?= $activeTab === 'system' ? 'active' : '' ?>">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Sistem Tanı/Sağlık</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info-card">
            <div class="user-avatar">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <strong class="user-name"><?= e(ADMIN_USER) ?></strong>
                <span class="user-role">Super Admin</span>
            </div>
        </div>
        <form method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn--ghost btn--full btn--sm logout-btn">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Güvenli Çıkış</span>
            </button>
        </form>
    </div>
</aside>

<!-- Main Area -->
<div class="main-wrapper">
    <header class="dashboard-header">
        <div class="header-title">
            <h1>
                <?php
                switch ($activeTab) {
                    case 'leads': echo 'Müşteri Talepleri Yönetimi'; break;
                    case 'analytics': echo 'Performans & Analitik Raporları'; break;
                    case 'database': echo 'Veritabanı Yapılandırma Kontrolü'; break;
                    case 'system': echo 'Sistem Teşhis & Sunucu Sağlığı'; break;
                    default: echo 'Sistem Genel Bakış Merkezi'; break;
                }
                ?>
            </h1>
            <p>DOVYE Studio Altyapı Yönetim Platformu</p>
        </div>

        <div class="header-actions">
            <?php if ($pdo): ?>
                <a href="admin.php?export=csv" class="btn btn--secondary btn--sm">
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>CSV Dışa Aktar</span>
                </a>
            <?php endif; ?>
            <a href="index.php" target="_blank" class="btn btn--ghost btn--sm">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <span>Siteyi Görüntüle</span>
            </a>
        </div>
    </header>

    <main class="content-area">
        <?php if ($flash): ?>
            <div class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div><?= e($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$pdo): ?>
            <!-- Global DB Alert -->
            <div class="alert alert--err" role="alert">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <strong>Veritabanı Sunucusuna Erişilemiyor (500 Hatası Önleme Modu)</strong><br>
                    `config.php` üzerinden PDO bağlantısı kurulamadı. Aşağıdaki <strong>Veritabanı Durumu</strong> sekmesinden ortam değişkenlerini kontrol edebilirsiniz.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'dashboard'): ?>
            <!-- DASHBOARD TAB -->
            <section class="metrics-grid">
                <div class="glass-card metric-card">
                    <span class="metric-title">Toplam Kayıtlı Aday</span>
                    <div class="metric-value-row">
                        <span class="metric-num"><?= $total ?></span>
                        <svg class="svg-icon m-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <span class="metric-sub">Tüm zamanlar toplamı</span>
                </div>

                <div class="glass-card metric-card status-new">
                    <span class="metric-title">Bekleyen (Yeni)</span>
                    <div class="metric-value-row">
                        <span class="metric-num"><?= $byStatus['new'] ?></span>
                        <svg class="svg-icon m-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <span class="metric-sub">Geri dönüş bekleyenler</span>
                </div>

                <div class="glass-card metric-card status-read">
                    <span class="metric-title">İncelenen</span>
                    <div class="metric-value-row">
                        <span class="metric-num"><?= $byStatus['read'] ?></span>
                        <svg class="svg-icon m-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </div>
                    <span class="metric-sub">İşleme alınanlar</span>
                </div>

                <div class="glass-card metric-card status-contacted">
                    <span class="metric-title">İletişime Geçildi</span>
                    <div class="metric-value-row">
                        <span class="metric-num"><?= $byStatus['contacted'] ?></span>
                        <svg class="svg-icon m-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <span class="metric-sub">Teklif iletilenler</span>
                </div>
            </section>

            <section class="two-col-grid">
                <div class="glass-card">
                    <div class="card-header">
                        <h3>Haftalık Talep Trendi</h3>
                        <span class="badge-tag">Son 7 Gün</span>
                    </div>
                    <ul class="chart-bars">
                        <?php foreach ($perDay as $day => $count): ?>
                            <li>
                                <span class="chart-label"><?= e(date('d M', strtotime($day))) ?></span>
                                <div class="bar-track">
                                    <progress max="<?= $maxDay ?>" value="<?= $count ?>"></progress>
                                </div>
                                <span class="chart-val"><?= $count ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="glass-card">
                    <div class="card-header">
                        <h3>Hizmet Kategorisi Dağılımı</h3>
                        <span class="badge-tag">İlgi Alanları</span>
                    </div>
                    <?php if (!$byService): ?>
                        <div class="empty-box">Veri yok</div>
                    <?php else: ?>
                        <ul class="chart-bars">
                            <?php foreach ($byService as $key => $count): ?>
                                <li>
                                    <span class="chart-label"><?= e(SERVICES[$key] ?? $key) ?></span>
                                    <div class="bar-track">
                                        <progress max="<?= $maxService ?>" value="<?= $count ?>"></progress>
                                    </div>
                                    <span class="chart-val"><?= $count ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

        <?php elseif ($activeTab === 'leads'): ?>
            <!-- LEADS TAB -->
            <section class="glass-card">
                <div class="table-toolbar">
                    <div class="status-filters">
                        <a href="<?= e(admin_url(['tab' => 'leads', 'status' => ''])) ?>" class="filter-pill <?= $filters['status'] === '' ? 'active' : '' ?>">Tümü (<?= $total ?>)</a>
                        <?php foreach (STATUSES as $key => $label): ?>
                            <a href="<?= e(admin_url(['tab' => 'leads', 'status' => $key])) ?>" class="filter-pill <?= $filters['status'] === $key ? 'active' : '' ?>"><?= e($label) ?> (<?= $byStatus[$key] ?>)</a>
                        <?php endforeach; ?>
                    </div>

                    <form method="get" action="admin.php" class="search-box">
                        <input type="hidden" name="tab" value="leads">
                        <?php if ($filters['status'] !== ''): ?>
                            <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                        <?php endif; ?>
                        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Arama yapınız...">
                        <button type="submit" class="btn btn--secondary btn--sm">Ara</button>
                    </form>
                </div>

                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kişi Bilgisi</th>
                                <th>Hizmet</th>
                                <th>Mesaj Detayı</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th style="text-align: right;">Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$leads): ?>
                                <tr>
                                    <td colspan="7" class="no-data">Kayıt bulunamadı.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($leads as $lead): ?>
                                <?php
                                $msg = (string) $lead['message'];
                                $short = mb_strlen($msg) > 70 ? mb_substr($msg, 0, 70) . '…' : $msg;
                                ?>
                                <tr>
                                    <td class="font-mono">#<?= (int) $lead['id'] ?></td>
                                    <td>
                                        <strong><?= e($lead['name']) ?></strong><br>
                                        <small class="text-muted"><?= e($lead['email']) ?></small>
                                    </td>
                                    <td><span class="service-tag"><?= e(SERVICES[$lead['service']] ?? $lead['service']) ?></span></td>
                                    <td class="msg-col">
                                        <details class="msg-details">
                                            <summary><?= e($short) ?></summary>
                                            <div class="msg-content">
                                                <p><?= e($msg) ?></p>
                                                <small class="text-muted font-mono">IP: <?= e($lead['ip_address'] ?? 'Bulunamadı') ?></small>
                                            </div>
                                        </details>
                                    </td>
                                    <td class="nowrap">
                                        <?= e(date('d.m.Y', strtotime((string) $lead['created_at']))) ?><br>
                                        <small class="text-muted"><?= e(date('H:i', strtotime((string) $lead['created_at']))) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= e($lead['status']) ?>">
                                            <?= e(STATUSES[$lead['status']] ?? $lead['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <form method="post" action="admin.php" class="inline-actions">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                            <input type="hidden" name="f_tab" value="leads">
                                            <input type="hidden" name="f_status" value="<?= e($filters['status']) ?>">
                                            <input type="hidden" name="f_q" value="<?= e($filters['q']) ?>">

                                            <?php if ($lead['status'] !== 'read'): ?>
                                                <button type="submit" name="status" value="read" class="btn-icon" title="İncelendi Yap">
                                                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($lead['status'] !== 'contacted'): ?>
                                                <button type="submit" name="status" value="contacted" class="btn-icon btn-icon-success" title="İletişime Geçildi">
                                                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($lead['status'] !== 'new'): ?>
                                                <button type="submit" name="status" value="new" class="btn-icon" title="Yeniye Al">
                                                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    <span>Toplam <strong><?= $matched ?></strong> kayıttan <strong><?= $offset + 1 ?>-<?= min($offset + PER_PAGE, $matched) ?></strong> gösteriliyor</span>
                    <div class="pagination-buttons">
                        <a href="<?= e(admin_url([...$filters, 'page' => $filters['page'] - 1])) ?>" class="btn btn--ghost btn--sm <?= $filters['page'] <= 1 ? 'disabled' : '' ?>">← Önceki</a>
                        <a href="<?= e(admin_url([...$filters, 'page' => $filters['page'] + 1])) ?>" class="btn btn--ghost btn--sm <?= $filters['page'] >= $pages ? 'disabled' : '' ?>">Sonraki →</a>
                    </div>
                </div>
            </section>

        <?php elseif ($activeTab === 'analytics'): ?>
            <!-- ANALYTICS TAB -->
            <section class="glass-card">
                <h3>Analitik Detayları</h3>
                <p>Veri akışı ve detaylı segmentasyon raporları.</p>
                <div class="two-col-grid" style="margin-top: 1.5rem;">
                    <div class="info-card">
                        <h4>Dönüşüm Oranı</h4>
                        <div class="big-stat"><?= $total > 0 ? round(($byStatus['contacted'] / $total) * 100, 1) : 0 ?>%</div>
                        <p class="text-muted">Gelen taleplerden iletişime geçilenlerin oranı.</p>
                    </div>
                    <div class="info-card">
                        <h4>Ortalama Yanıt Hızı</h4>
                        <div class="big-stat">&lt; 12 Saat</div>
                        <p class="text-muted">Ekip tarafından inceleme süresi.</p>
                    </div>
                </div>
            </section>

        <?php elseif ($activeTab === 'database'): ?>
            <!-- DATABASE SETUP TAB -->
            <section class="glass-card">
                <h3>Veritabanı Durumu & Ortam Değişkenleri Teşhisi</h3>
                <p>Coolify MariaDB konteyner bağlantısı kontrolü.</p>

                <div class="diag-grid" style="margin-top: 1.5rem;">
                    <div class="diag-item">
                        <span>DB_HOST (Konteyner İç Adı):</span>
                        <strong><?= e(DB_HOST) ?></strong>
                    </div>
                    <div class="diag-item">
                        <span>DB_NAME (Veritabanı Adı):</span>
                        <strong><?= e(DB_NAME) ?></strong>
                    </div>
                    <div class="diag-item">
                        <span>DB_USER (Kullanıcı Adı):</span>
                        <strong><?= e(DB_USER) ?></strong>
                    </div>
                    <div class="diag-item">
                        <span>Bağlantı Durumu:</span>
                        <strong style="color: <?= $pdo ? 'var(--emerald)' : 'var(--rose)' ?>;">
                            <?= $pdo ? 'BAĞLANDI (HEALTHY)' : 'BAĞLANAMADI (DISCONNECTED)' ?>
                        </strong>
                    </div>
                </div>

                <div class="help-box" style="margin-top: 2rem;">
                    <h4>Coolify Üzerinde Bağlantı Nasıl Kurulur?</h4>
                    <ol>
                        <li>Coolify panelinizde MariaDB servisinize gidin ve <strong>Internal Hostname</strong> değerini kopyalayın.</li>
                        <li>PHP uygulamanızın <strong>Environment Variables</strong> sekmesine aşağıdaki değişkenleri ekleyip kaydedin:
                            <pre><code>DB_HOST=&lt;MARIADB_INTERNAL_HOSTNAME&gt;
DB_NAME=lead_panel
DB_USER=root
DB_PASS=&lt;MARIADB_ROOT_PASSWORD&gt;</code></pre>
                        </li>
                        <li>Uygulamayı <strong>Redeploy</strong> vererek yeniden başlatın.</li>
                    </ol>
                </div>
            </section>

        <?php elseif ($activeTab === 'system'): ?>
            <!-- SYSTEM HEALTH TAB -->
            <section class="glass-card">
                <h3>Sistem Teşhis ve Sunucu Bilgileri</h3>
                <div class="diag-grid" style="margin-top: 1.5rem;">
                    <div class="diag-item">
                        <span>PHP Sürümü:</span>
                        <strong><?= e(PHP_VERSION) ?></strong>
                    </div>
                    <div class="diag-item">
                        <span>Sunucu Zaman Dilimi:</span>
                        <strong><?= e(date_default_timezone_get()) ?></strong>
                    </div>
                    <div class="diag-item">
                        <span>Apache/Web Sunucu:</span>
                        <strong><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Apache/Docker') ?></strong>
                    </div>
                    <div class="diag-item">
                        <span>HTTPS / TLS Proxy:</span>
                        <strong><?= (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'Aktif (Traefik Proxy SSL)' : 'Doğrudan HTTP' ?></strong>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
