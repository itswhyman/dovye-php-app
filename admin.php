<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

const IDLE_TIMEOUT   = 1800; // 30 dk hareketsizlikte oturum kapanır
const MAX_FAILS      = 5;    // art arda yanlış giriş sınırı
const LOCK_SECONDS   = 300;  // sınır aşılınca 5 dk kilit
const PER_PAGE       = 15;

// ---------------------------------------------------------------------------
// Yardımcılar
// ---------------------------------------------------------------------------

/** Filtreleri (durum, arama, sayfa) doğrulayarak okur. $p: alan öneki. */
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
// POST işlemleri
// ---------------------------------------------------------------------------

$loginError = null;
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';

if ($action === 'login') {
    $lockedUntil = (int) ($_SESSION['lock_until'] ?? 0);

    if (!csrf_valid()) {
        $loginError = 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.';
    } elseif ($lockedUntil > time()) {
        $loginError = 'Çok fazla hatalı deneme. ' . ceil(($lockedUntil - time()) / 60) . ' dakika sonra tekrar deneyin.';
    } else {
        $user = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        // Her iki kontrol de çalışır: kullanıcı adı yanlış olsa bile süre farkı sızmaz.
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
        $loginError = 'Kullanıcı adı veya şifre hatalı.';
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
        flash('err', 'Geçersiz istek.');
    } else {
        $stmt = db()->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        flash('ok', "#{$id} numaralı kayıt “" . STATUSES[$status] . '” olarak güncellendi.');
    }
    redirect($back);
}

// ---------------------------------------------------------------------------
// Giriş ekranı
// ---------------------------------------------------------------------------

if (!is_logged_in()) {
    if (($_SESSION['admin'] ?? false) === true) {
        // Zaman aşımı: eski oturumu temizle
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
    <title>Yönetici girişi</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="login">
    <div class="card">
        <h1>Yönetici girişi</h1>
        <p class="muted">Müşteri adaylarını görmek için giriş yapın.</p>
        <?php if ($loginError): ?>
            <div class="alert alert--err" role="alert"><?= e($loginError) ?></div>
        <?php endif; ?>
        <form method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="field">
                <label for="username">Kullanıcı adı</label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn">Giriş yap</button>
        </form>
    </div>
</main>
</body>
</html>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// Panel verileri
// ---------------------------------------------------------------------------

$pdo = db();
$filters = read_filters($_GET);

// Özet sayılar
$byStatus = array_fill_keys(array_keys(STATUSES), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM leads GROUP BY status') as $row) {
    $byStatus[$row['status']] = (int) $row['c'];
}
$total = array_sum($byStatus);

// Servis dağılımı
$byService = [];
foreach ($pdo->query('SELECT service, COUNT(*) AS c FROM leads GROUP BY service ORDER BY c DESC') as $row) {
    $byService[$row['service']] = (int) $row['c'];
}
$maxService = $byService ? max($byService) : 1;

// Son 7 gün
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

// Liste (filtre + arama + sayfalama) — tamamı prepared statement
$where = [];
$params = [];
if ($filters['status'] !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $filters['status'];
}
if ($filters['q'] !== '') {
    $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
    // Native prepared statement'ta aynı adlı parametre tekrar kullanılamaz → q1/q2/q3
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
    'SELECT id, name, email, service, message, status, created_at
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
    <title>Müşteri &amp; Analytics</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <strong>Müşteri &amp; Analytics</strong>
    <div class="topbar__right">
        <span class="who"><?= e(ADMIN_USER) ?></span>
        <form method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn--ghost btn--sm">Çıkış</button>
        </form>
    </div>
</header>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="stats" aria-label="Özet">
        <div class="card stat"><span class="stat__label">Toplam aday</span><span class="stat__value"><?= $total ?></span></div>
        <div class="card stat stat--new"><span class="stat__label">Yeni</span><span class="stat__value"><?= $byStatus['new'] ?></span></div>
        <div class="card stat"><span class="stat__label">Okundu</span><span class="stat__value"><?= $byStatus['read'] ?></span></div>
        <div class="card stat stat--contacted"><span class="stat__label">İletişime geçildi</span><span class="stat__value"><?= $byStatus['contacted'] ?></span></div>
        <div class="card stat"><span class="stat__label">Son 7 gün</span><span class="stat__value"><?= $last7 ?></span></div>
    </section>

    <section class="insights">
        <div class="card">
            <h2>Son 7 günde gelen adaylar</h2>
            <ul class="bars">
                <?php foreach ($perDay as $day => $count): ?>
                    <li>
                        <span><?= e(date('d.m', strtotime($day))) ?></span>
                        <progress max="<?= $maxDay ?>" value="<?= $count ?>"></progress>
                        <span class="num"><?= $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card">
            <h2>Hizmet türüne göre dağılım</h2>
            <?php if (!$byService): ?>
                <p class="empty">Henüz veri yok.</p>
            <?php else: ?>
                <ul class="bars">
                    <?php foreach ($byService as $key => $count): ?>
                        <li>
                            <span><?= e(SERVICES[$key] ?? $key) ?></span>
                            <progress max="<?= $maxService ?>" value="<?= $count ?>"></progress>
                            <span class="num"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section aria-label="Müşteri adayları">
        <div class="filters">
            <nav class="tabs" aria-label="Duruma göre filtre">
                <a href="<?= e(admin_url($tabFilter(''))) ?>" <?= $filters['status'] === '' ? 'aria-current="true"' : '' ?>>Tümü</a>
                <?php foreach (STATUSES as $key => $label): ?>
                    <a href="<?= e(admin_url($tabFilter($key))) ?>" <?= $filters['status'] === $key ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <form class="search" method="get" action="admin.php" role="search">
                <?php if ($filters['status'] !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="İsim, e-posta, mesaj…" aria-label="Ara" maxlength="100">
                <button type="submit" class="btn btn--sm">Ara</button>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kişi</th>
                        <th>Hizmet</th>
                        <th>Mesaj</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$leads): ?>
                    <tr><td colspan="7" class="empty">Bu filtreyle eşleşen kayıt yok.</td></tr>
                <?php endif; ?>
                <?php foreach ($leads as $lead): ?>
                    <?php
                    $msg = (string) $lead['message'];
                    $short = mb_strlen($msg) > 80 ? mb_substr($msg, 0, 80) . '…' : $msg;
                    ?>
                    <tr>
                        <td class="nowrap"><?= (int) $lead['id'] ?></td>
                        <td>
                            <strong><?= e($lead['name']) ?></strong><br>
                            <a class="sub" href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a>
                        </td>
                        <td class="nowrap"><?= e(SERVICES[$lead['service']] ?? $lead['service']) ?></td>
                        <td class="msg">
                            <details>
                                <summary><?= e($short) ?></summary>
                                <p><?= e($msg) ?></p>
                            </details>
                        </td>
                        <td class="nowrap"><?= e(date('d.m.Y H:i', strtotime((string) $lead['created_at']))) ?></td>
                        <td><span class="badge badge--<?= e($lead['status']) ?>"><?= e(STATUSES[$lead['status']] ?? $lead['status']) ?></span></td>
                        <td>
                            <form class="actions" method="post" action="admin.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                <input type="hidden" name="f_status" value="<?= e($filters['status']) ?>">
                                <input type="hidden" name="f_q" value="<?= e($filters['q']) ?>">
                                <input type="hidden" name="f_page" value="<?= (int) $filters['page'] ?>">
                                <?php foreach (['read' => 'Okundu', 'contacted' => 'İletişime Geçildi', 'new' => 'Yeniye al'] as $key => $label): ?>
                                    <?php if ($lead['status'] !== $key): ?>
                                        <button type="submit" name="status" value="<?= e($key) ?>" class="btn btn--ghost btn--sm"><?= e($label) ?></button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pager">
            <span><?= $matched ?> kayıt · sayfa <?= $filters['page'] ?>/<?= $pages ?></span>
            <span class="links">
                <a class="btn btn--ghost btn--sm <?= $filters['page'] <= 1 ? 'disabled' : '' ?>"
                   href="<?= e(admin_url([...$filters, 'page' => $filters['page'] - 1])) ?>">← Önceki</a>
                <a class="btn btn--ghost btn--sm <?= $filters['page'] >= $pages ? 'disabled' : '' ?>"
                   href="<?= e(admin_url([...$filters, 'page' => $filters['page'] + 1])) ?>">Sonraki →</a>
            </span>
        </div>
    </section>
</main>
</body>
</html>
