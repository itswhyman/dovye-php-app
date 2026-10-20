<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

const IDLE_TIMEOUT = 1800; // 30 mins
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

    return ['status' => $status, 'q' => $q, 'page' => $page];
}

function admin_url(array $f): string
{
    $query = [];
    if (($f['status'] ?? '') !== '') { $query['status'] = $f['status']; }
    if (($f['q'] ?? '') !== '')      { $query['q'] = $f['q']; }
    if (($f['page'] ?? 1) > 1)       { $query['page'] = $f['page']; }
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

// ---------------------------------------------------------------------------
// POST Actions
// ---------------------------------------------------------------------------

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

if ($action === 'status') {
    if (!is_logged_in()) {
        redirect('admin.php');
    }
    $back = admin_url(read_filters($_POST, 'f_'));

    $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';

    if (!csrf_valid()) {
        flash('err', 'Güvenlik doğrulaması başarısız. Tekrar deneyin.');
    } elseif ($id === false || $id < 1 || !array_key_exists($status, STATUSES)) {
        flash('err', 'Geçersiz talep.');
    } else {
        $stmt = db()->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        flash('ok', "#{$id} numaralı talep “" . STATUSES[$status] . '” olarak güncellendi.');
    }
    redirect($back);
}

// CSV Export Action
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!is_logged_in()) {
        redirect('admin.php');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dovye_leads_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Ad Soyad', 'E-Posta', 'Hizmet', 'Mesaj', 'Durum', 'IP', 'Tarih']);
    $rows = db()->query('SELECT id, name, email, service, message, status, ip_address, created_at FROM leads ORDER BY id DESC');
    while ($row = $rows->fetch()) {
        $row['service'] = SERVICES[$row['service']] ?? $row['service'];
        $row['status'] = STATUSES[$row['status']] ?? $row['status'];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ---------------------------------------------------------------------------
// Login Screen
// ---------------------------------------------------------------------------

if (!is_logged_in()) {
    if (($_SESSION['admin'] ?? false) === true) {
        $_SESSION = [];
        $loginError ??= 'Oturum süreniz doldu. Lütfen tekrar giriş yapın.';
    }
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Yönetici Paneli — DOVYE Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="glow-bg"><div class="glow-orb glow-1"></div></div>
<main class="login">
    <div class="card login-card">
        <div class="login-brand">
            <span class="logo-icon">◇</span>
            <span>DOVYE Core</span>
        </div>
        <h1>Yönetim Merkezi</h1>
        <p class="muted">Devam etmek için yönetici kimliğinizle doğrulama yapın.</p>

        <?php if ($loginError): ?>
            <div class="alert alert--err" role="alert"><?= e($loginError) ?></div>
        <?php endif; ?>

        <form method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="field">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus placeholder="admin">
            </div>
            <div class="field">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn--primary btn--full">
                <span>Giriş Yap</span>
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
// Main Dashboard Data
// ---------------------------------------------------------------------------

$pdo = db();
$filters = read_filters($_GET);

// Overview Stats
$byStatus = array_fill_keys(array_keys(STATUSES), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM leads GROUP BY status') as $row) {
    $byStatus[$row['status']] = (int) $row['c'];
}
$total = array_sum($byStatus);

// Service Distribution
$byService = [];
foreach ($pdo->query('SELECT service, COUNT(*) AS c FROM leads GROUP BY service ORDER BY c DESC') as $row) {
    $byService[$row['service']] = (int) $row['c'];
}
$maxService = $byService ? max($byService) : 1;

// Last 7 days
$perDay = [];
for ($i = 6; $i >= 0; $i--) {
    $perDay[(new DateTimeImmutable("-{$i} days"))->format('Y-m-d')] = 0;
}
$stmt = $pdo->query('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM leads
                     WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(created_at)');
foreach ($stmt as $row) {
    if (isset($perDay[$row['d']])) {
        $perDay[$row['d']] = (int) $row['c'];
    }
}
$last7 = array_sum($perDay);
$maxDay = max(1, max($perDay));

// Filtered List
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

$flash = flash();
$tabFilter = static fn (string $s): array => ['status' => $s, 'q' => $filters['q'], 'page' => 1];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Müşteri & Analytics Paneli — DOVYE Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">

<header class="topbar">
    <div class="topbar__left">
        <span class="logo-icon">◇</span>
        <strong>DOVYE<span>CORE</span></strong>
        <span class="version-badge">v2.4.0</span>
    </div>
    <div class="topbar__right">
        <a href="admin.php?export=csv" class="btn btn--ghost btn--sm export-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            <span>Dışa Aktar (CSV)</span>
        </a>
        <div class="user-pill">
            <span class="online-indicator"></span>
            <span class="who"><?= e(ADMIN_USER) ?></span>
        </div>
        <form method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn--ghost btn--sm">Çıkış</button>
        </form>
    </div>
</header>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status">
            <span><?= $flash['type'] === 'ok' ? '✅' : '⚠️' ?></span>
            <div><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <section class="stats" aria-label="Özet Metrikler">
        <div class="card stat">
            <span class="stat__label">Toplam Müşteri</span>
            <span class="stat__value"><?= $total ?></span>
            <span class="stat__sub">Tüm zamanlar</span>
        </div>
        <div class="card stat stat--new">
            <span class="stat__label">Bekleyen (Yeni)</span>
            <span class="stat__value"><?= $byStatus['new'] ?></span>
            <span class="stat__sub">Yanıtlama bekleniyor</span>
        </div>
        <div class="card stat stat--read">
            <span class="stat__label">İncelenen</span>
            <span class="stat__value"><?= $byStatus['read'] ?></span>
            <span class="stat__sub">İşlemde</span>
        </div>
        <div class="card stat stat--contacted">
            <span class="stat__label">Tamamlanan</span>
            <span class="stat__value"><?= $byStatus['contacted'] ?></span>
            <span class="stat__sub">Geri dönüş yapıldı</span>
        </div>
        <div class="card stat">
            <span class="stat__label">Son 7 Gün</span>
            <span class="stat__value"><?= $last7 ?></span>
            <span class="stat__sub">Haftalık hacim</span>
        </div>
    </section>

    <!-- Visual Insights -->
    <section class="insights">
        <div class="card insight-card">
            <div class="insight-header">
                <h3>Son 7 Günlük Talep Trendi</h3>
                <span class="tag">Canlı Veri</span>
            </div>
            <ul class="bars">
                <?php foreach ($perDay as $day => $count): ?>
                    <li>
                        <span class="day-label"><?= e(date('d M', strtotime($day))) ?></span>
                        <div class="progress-wrap">
                            <progress max="<?= $maxDay ?>" value="<?= $count ?>"></progress>
                        </div>
                        <span class="num"><?= $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card insight-card">
            <div class="insight-header">
                <h3>Hizmet Kategorisi Dağılımı</h3>
                <span class="tag">Segmentasyon</span>
            </div>
            <?php if (!$byService): ?>
                <p class="empty">Henüz kaydedilmiş bir veri bulunmuyor.</p>
            <?php else: ?>
                <ul class="bars">
                    <?php foreach ($byService as $key => $count): ?>
                        <li>
                            <span class="day-label"><?= e(SERVICES[$key] ?? $key) ?></span>
                            <div class="progress-wrap">
                                <progress max="<?= $maxService ?>" value="<?= $count ?>"></progress>
                            </div>
                            <span class="num"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <!-- Table Section -->
    <section class="data-section" aria-label="Müşteri adayları listesi">
        <div class="filters-bar">
            <nav class="tabs" aria-label="Filtreler">
                <a href="<?= e(admin_url($tabFilter(''))) ?>" <?= $filters['status'] === '' ? 'aria-current="true"' : '' ?>>
                    Tümü <span class="tab-count"><?= $total ?></span>
                </a>
                <?php foreach (STATUSES as $key => $label): ?>
                    <a href="<?= e(admin_url($tabFilter($key))) ?>" <?= $filters['status'] === $key ? 'aria-current="true"' : '' ?>>
                        <?= e($label) ?> <span class="tab-count"><?= $byStatus[$key] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form class="search-form" method="get" action="admin.php" role="search">
                <?php if ($filters['status'] !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                <?php endif; ?>
                <div class="search-input-wrap">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="İsim, e-posta veya içerik ara…" aria-label="Ara">
                </div>
                <button type="submit" class="btn btn--secondary btn--sm">Ara</button>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Müşteri</th>
                        <th>Hizmet</th>
                        <th>Mesaj / Detay</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$leads): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <span class="empty-icon">📂</span>
                            <p>Aradığınız kriterlere uygun kayıt bulunamadı.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($leads as $lead): ?>
                    <?php
                    $msg = (string) $lead['message'];
                    $short = mb_strlen($msg) > 75 ? mb_substr($msg, 0, 75) . '…' : $msg;
                    ?>
                    <tr>
                        <td class="id-cell">#<?= (int) $lead['id'] ?></td>
                        <td>
                            <div class="user-info">
                                <strong class="user-name"><?= e($lead['name']) ?></strong>
                                <a class="user-email" href="mailto:<?= e($lead['email']) ?>">
                                    <?= e($lead['email']) ?>
                                </a>
                            </div>
                        </td>
                        <td>
                            <span class="service-pill"><?= e(SERVICES[$lead['service']] ?? $lead['service']) ?></span>
                        </td>
                        <td class="msg-cell">
                            <details class="msg-accordion">
                                <summary><?= e($short) ?></summary>
                                <div class="msg-full">
                                    <p><?= e($msg) ?></p>
                                    <div class="msg-meta">IP: <?= e($lead['ip_address'] ?? 'Gizli') ?></div>
                                </div>
                            </details>
                        </td>
                        <td class="nowrap date-cell">
                            <?= e(date('d.m.Y', strtotime((string) $lead['created_at']))) ?>
                            <small><?= e(date('H:i', strtotime((string) $lead['created_at']))) ?></small>
                        </td>
                        <td>
                            <span class="badge badge--<?= e($lead['status']) ?>">
                                <?= e(STATUSES[$lead['status']] ?? $lead['status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <form class="actions-group" method="post" action="admin.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                <input type="hidden" name="f_status" value="<?= e($filters['status']) ?>">
                                <input type="hidden" name="f_q" value="<?= e($filters['q']) ?>">
                                <input type="hidden" name="f_page" value="<?= (int) $filters['page'] ?>">
                                
                                <?php if ($lead['status'] !== 'read'): ?>
                                    <button type="submit" name="status" value="read" class="btn btn--action" title="İncelendi İşaretle">👁️</button>
                                <?php endif; ?>
                                <?php if ($lead['status'] !== 'contacted'): ?>
                                    <button type="submit" name="status" value="contacted" class="btn btn--action btn--success" title="İletişime Geçildi">✅</button>
                                <?php endif; ?>
                                <?php if ($lead['status'] !== 'new'): ?>
                                    <button type="submit" name="status" value="new" class="btn btn--action" title="Yeniye Al">🔄</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pager-bar">
            <span class="pager-info">
                Toplam <strong><?= $matched ?></strong> kayıttan <strong><?= $offset + 1 ?>-<?= min($offset + PER_PAGE, $matched) ?></strong> arası gösteriliyor (Sayfa <?= $filters['page'] ?>/<?= $pages ?>)
            </span>
            <div class="pager-controls">
                <a class="btn btn--ghost btn--sm <?= $filters['page'] <= 1 ? 'disabled' : '' ?>"
                   href="<?= e(admin_url([...$filters, 'page' => $filters['page'] - 1])) ?>">
                    ← Önceki
                </a>
                <a class="btn btn--ghost btn--sm <?= $filters['page'] >= $pages ? 'disabled' : '' ?>"
                   href="<?= e(admin_url([...$filters, 'page' => $filters['page'] + 1])) ?>">
                    Sonraki →
                </a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
