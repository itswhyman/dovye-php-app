<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

const IDLE_TIMEOUT = 3600;

function is_logged_in(): bool
{
    if (($_SESSION['admin_logged'] ?? false) !== true) return false;
    if (time() - (int) ($_SESSION['last_activity'] ?? 0) > IDLE_TIMEOUT) return false;
    $_SESSION['last_activity'] = time();
    return true;
}

$pdo = db();
$errors = [];

// Initialize Admin Credentials from DB or Config
if ($pdo) {
    try {
        $check = $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ((int)$check === 0) {
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
            $stmt->execute([':u' => DEFAULT_ADMIN_USER, ':p' => DEFAULT_ADMIN_HASH]);
        }
    } catch (Throwable $t) {}
}

// ---------------------------------------------------------------------------
// POST ACTIONS
// ---------------------------------------------------------------------------
$action = $_POST['action'] ?? '';

// LOGIN
if ($action === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Oturum doğrulaması başarısız.';
    } elseif (!$pdo) {
        // Fallback check against default config if DB down
        if ($user === DEFAULT_ADMIN_USER && password_verify($pass, DEFAULT_ADMIN_HASH)) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $user;
            $_SESSION['last_activity'] = time();
            redirect('admin.php');
        } else {
            $errors[] = 'Giriş bilgileri hatalı.';
        }
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $user]);
        $row = $stmt->fetch();

        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $row['username'];
            $_SESSION['last_activity'] = time();
            log_system_action('ADMIN_LOGIN_SUCCESS', "User: {$row['username']}");
            redirect('admin.php');
        } else {
            log_system_action('ADMIN_LOGIN_FAILED', "Attempted user: $user");
            $errors[] = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}

// LOGOUT
if ($action === 'logout') {
    if (csrf_valid()) {
        log_system_action('ADMIN_LOGOUT', "User: " . ($_SESSION['admin_user'] ?? ''));
        $_SESSION = [];
        session_destroy();
    }
    redirect('admin.php');
}

// UPDATE ADMIN PASSWORD & USERNAME
if ($action === 'update_password' && is_logged_in()) {
    if (!csrf_valid()) {
        flash('err', 'Oturum geçersiz.');
    } else {
        $new_user = trim($_POST['new_username'] ?? '');
        $new_pass = trim($_POST['new_password'] ?? '');

        if (mb_strlen($new_user) < 3) {
            flash('err', 'Kullanıcı adı en az 3 karakter olmalıdır.');
        } elseif (mb_strlen($new_pass) < 6) {
            flash('err', 'Yeni şifre en az 6 karakter olmalıdır.');
        } elseif (!$pdo) {
            flash('err', 'Veritabanı bağlantısı yok.');
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $currentUser = $_SESSION['admin_user'] ?? DEFAULT_ADMIN_USER;

            $stmt = $pdo->prepare('UPDATE admin_users SET username = :u, password_hash = :p WHERE username = :curr');
            $stmt->execute([':u' => $new_user, ':p' => $hash, ':curr' => $currentUser]);

            $_SESSION['admin_user'] = $new_user;
            log_system_action('ADMIN_CREDENTIALS_UPDATED', "New Username: $new_user");
            flash('ok', 'Yönetici bilgileri ve şifreniz başarıyla güncellendi!');
        }
    }
    redirect('admin.php?tab=security');
}

// ADD YOUTUBE VIDEO
if ($action === 'add_video' && is_logged_in()) {
    if (csrf_valid() && $pdo) {
        $title    = trim($_POST['title'] ?? '');
        $url      = trim($_POST['youtube_url'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $videoId  = parse_youtube_id($url);

        if (!$videoId) {
            flash('err', 'Geçersiz YouTube URL adresi!');
        } else {
            $stmt = $pdo->prepare('INSERT INTO youtube_videos (title, youtube_url, video_id, category) VALUES (:t, :u, :v, :c)');
            $stmt->execute([':t' => $title, ':u' => $url, ':v' => $videoId, ':c' => $category]);
            log_system_action('YOUTUBE_VIDEO_ADDED', "Title: $title, VideoID: $videoId");
            flash('ok', 'YouTube videosu eklendi ve ana sayfada yayınlandı!');
        }
    }
    redirect('admin.php?tab=youtube');
}

// DELETE YOUTUBE VIDEO
if ($action === 'delete_video' && is_logged_in()) {
    if (csrf_valid() && $pdo) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM youtube_videos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        log_system_action('YOUTUBE_VIDEO_DELETED', "Video ID: $id");
        flash('ok', 'Video silindi.');
    }
    redirect('admin.php?tab=youtube');
}

// ADD LIVE CAM
if ($action === 'add_cam' && is_logged_in()) {
    if (csrf_valid() && $pdo) {
        $title    = trim($_POST['title'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $embed    = trim($_POST['embed_url'] ?? '');

        if (empty($title) || empty($embed)) {
            flash('err', 'Başlık ve Embed URL zorunludur.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO live_cams (title, location, embed_url) VALUES (:t, :l, :e)');
            $stmt->execute([':t' => $title, ':l' => $location, ':e' => $embed]);
            log_system_action('LIVE_CAM_ADDED', "Cam Title: $title");
            flash('ok', 'Canlı kamera akışı eklendi!');
        }
    }
    redirect('admin.php?tab=cameras');
}

// DELETE LIVE CAM
if ($action === 'delete_cam' && is_logged_in()) {
    if (csrf_valid() && $pdo) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM live_cams WHERE id = :id');
        $stmt->execute([':id' => $id]);
        log_system_action('LIVE_CAM_DELETED', "Cam ID: $id");
        flash('ok', 'Kamera silindi.');
    }
    redirect('admin.php?tab=cameras');
}

// CLEAR LOGS
if ($action === 'clear_logs' && is_logged_in()) {
    if (csrf_valid() && $pdo) {
        $pdo->exec('TRUNCATE TABLE system_logs');
        log_system_action('LOGS_CLEARED', 'All system logs cleared by admin.');
        flash('ok', 'Tüm sistem logları temizlendi.');
    }
    redirect('admin.php?tab=logs');
}

// ---------------------------------------------------------------------------
// VIEW CONTROLLER
// ---------------------------------------------------------------------------
if (!is_logged_in()) {
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş — DOVYE Management Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body style="display:grid; place-items:center; min-height:100vh;">
<div class="cyber-grid-bg"></div>

<div class="baklava-card" style="width:100%; max-width:400px;">
    <div style="display:flex; align-items:center; gap:0.5rem; font-weight:800; color:var(--neon-cyan); margin-bottom:1.5rem;">
        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
        <span>DOVYE CORE OS</span>
    </div>

    <h2>Yönetici Girişi</h2>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;">Sistem kontrol merkezine erişim.</p>

    <?php if ($errors): ?>
        <div class="cyber-alert alert-err"><?= e($errors[0]) ?></div>
    <?php endif; ?>

    <form method="post" action="admin.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <div class="cyber-field">
            <label>Kullanıcı Adı</label>
            <input type="text" name="username" required placeholder="admin">
        </div>
        <div class="cyber-field">
            <label>Şifre</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="cyber-btn btn-cyan btn-full">Giriş Yap →</button>
    </form>
</div>
</body>
</html>
    <?php
    exit;
}

// Logged In Dashboard Data
$activeTab = $_GET['tab'] ?? 'dashboard';

$yt_videos = $pdo ? $pdo->query('SELECT * FROM youtube_videos ORDER BY id DESC')->fetchAll() : [];
$cams      = $pdo ? $pdo->query('SELECT * FROM live_cams ORDER BY id DESC')->fetchAll() : [];
$logs      = $pdo ? $pdo->query('SELECT * FROM system_logs ORDER BY id DESC LIMIT 50')->fetchAll() : [];
$leads     = $pdo ? $pdo->query('SELECT * FROM leads ORDER BY id DESC LIMIT 50')->fetchAll() : [];

$flash = flash();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yönetim Konsolu — DOVYE Studio</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
            <div>
                <strong style="display:block;">DOVYE<span>CORE</span></strong>
                <small style="color:var(--text-muted); font-family:var(--font-mono);">CMS v3.0 OS</small>
            </div>
        </div>

        <nav class="admin-nav">
            <a href="admin.php?tab=dashboard" class="admin-nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">Genel Bakış</a>
            <a href="admin.php?tab=youtube" class="admin-nav-item <?= $activeTab === 'youtube' ? 'active' : '' ?>">YouTube Video Hub</a>
            <a href="admin.php?tab=cameras" class="admin-nav-item <?= $activeTab === 'cameras' ? 'active' : '' ?>">Dünya Canlı Kameralar</a>
            <a href="admin.php?tab=leads" class="admin-nav-item <?= $activeTab === 'leads' ? 'active' : '' ?>">Müşteri Talepleri</a>
            <a href="admin.php?tab=logs" class="admin-nav-item <?= $activeTab === 'logs' ? 'active' : '' ?>">Sistem Logları</a>
            <a href="admin.php?tab=security" class="admin-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>">Şifre & Güvenlik</a>
        </nav>

        <div style="padding: 1.25rem; border-top: 1px solid var(--border-dark);">
            <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.75rem;">Aktif Kullanıcı: <strong><?= e($_SESSION['admin_user']) ?></strong></div>
            <form method="post" action="admin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="cyber-btn btn-ghost btn-sm btn-full">Güvenli Çıkış</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <?php if ($flash): ?>
            <div class="cyber-alert alert-<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($activeTab === 'dashboard'): ?>
            <h2>Genel Bakış Merkezi</h2>
            <p style="color:var(--text-muted); margin-bottom:2rem;">Canlı sistem durumu ve yayınlanmış içerikler.</p>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
                <div class="baklava-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">Yayınlanan Videolar</span>
                    <div style="font-size:2rem; font-weight:800; color:var(--neon-cyan);"><?= count($yt_videos) ?></div>
                </div>
                <div class="baklava-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">Canlı Kameralar</span>
                    <div style="font-size:2rem; font-weight:800; color:var(--neon-pink);"><?= count($cams) ?></div>
                </div>
                <div class="baklava-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">Müşteri Talepleri</span>
                    <div style="font-size:2rem; font-weight:800; color:var(--neon-emerald);"><?= count($leads) ?></div>
                </div>
            </div>

        <?php elseif ($activeTab === 'youtube'): ?>
            <h2>YouTube Video Yönetimi</h2>
            <p style="color:var(--text-muted); margin-bottom:1.5rem;">Sitenin YouTube oynatma motoruna yeni videolar ekleyin.</p>

            <div class="baklava-card" style="margin-bottom:2rem;">
                <h3>Yeni YouTube Videosu Ekle</h3>
                <form method="post" action="admin.php?tab=youtube" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_video">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="cyber-field">
                            <label>Video Başlığı</label>
                            <input type="text" name="title" required placeholder="Cyberpunk 2077 Night City 4K">
                        </div>
                        <div class="cyber-field">
                            <label>YouTube Linki (URL)</label>
                            <input type="url" name="youtube_url" required placeholder="https://www.youtube.com/watch?v=Leed38_x0_0">
                        </div>
                    </div>
                    <div class="cyber-field">
                        <label>Kategori</label>
                        <input type="text" name="category" placeholder="Lo-Fi Beats / Cyberpunk / Tech">
                    </div>
                    <button type="submit" class="cyber-btn btn-cyan">Videoyu Ekle ve Yayınla →</button>
                </form>
            </div>

            <div class="baklava-card">
                <h3>Yayınlanan Videolar (<?= count($yt_videos) ?>)</h3>
                <table class="admin-table" style="margin-top:1rem;">
                    <thead><tr><th>ID</th><th>Başlık</th><th>Kategori</th><th>Video ID</th><th>İşlem</th></tr></thead>
                    <tbody>
                        <?php foreach ($yt_videos as $v): ?>
                            <tr>
                                <td>#<?= $v['id'] ?></td>
                                <td><strong><?= e($v['title']) ?></strong></td>
                                <td><?= e($v['category']) ?></td>
                                <td><code><?= e($v['video_id']) ?></code></td>
                                <td>
                                    <form method="post" action="admin.php?tab=youtube" onsubmit="return confirm('Silinsin mi?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_video">
                                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                        <button type="submit" class="cyber-btn btn-pink btn-sm">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($activeTab === 'cameras'): ?>
            <h2>Dünya Canlı Kameralar Yönetimi</h2>
            <p style="color:var(--text-muted); margin-bottom:1.5rem;">Ana sayfadaki canlı yayın kameralarını ekleyin/düzenleyin.</p>

            <div class="baklava-card" style="margin-bottom:2rem;">
                <h3>Yeni Canlı Kamera Ekle</h3>
                <form method="post" action="admin.php?tab=cameras" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_cam">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="cyber-field">
                            <label>Kamera Başlığı</label>
                            <input type="text" name="title" required placeholder="Shibuya Crossing Live">
                        </div>
                        <div class="cyber-field">
                            <label>Lokasyon</label>
                            <input type="text" name="location" placeholder="Tokyo, Japan">
                        </div>
                    </div>
                    <div class="cyber-field">
                        <label>Embed / Akış URL (YouTube Embed Linki)</label>
                        <input type="url" name="embed_url" required placeholder="https://www.youtube.com/embed/h3fUgOKFMNU">
                    </div>
                    <button type="submit" class="cyber-btn btn-cyan">Kameryı Ekle →</button>
                </form>
            </div>

            <div class="baklava-card">
                <h3>Aktif Kameralar (<?= count($cams) ?>)</h3>
                <table class="admin-table" style="margin-top:1rem;">
                    <thead><tr><th>ID</th><th>Başlık</th><th>Lokasyon</th><th>Embed URL</th><th>İşlem</th></tr></thead>
                    <tbody>
                        <?php foreach ($cams as $c): ?>
                            <tr>
                                <td>#<?= $c['id'] ?></td>
                                <td><strong><?= e($c['title']) ?></strong></td>
                                <td><?= e($c['location']) ?></td>
                                <td><small><?= e($c['embed_url']) ?></small></td>
                                <td>
                                    <form method="post" action="admin.php?tab=cameras" onsubmit="return confirm('Silinsin mi?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_cam">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="cyber-btn btn-pink btn-sm">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($activeTab === 'security'): ?>
            <h2>Şifre ve Yönetici Güvenliği</h2>
            <p style="color:var(--text-muted); margin-bottom:1.5rem;">Yönetici kullanıcı adınızı ve şifrenizi buradan güncelleyin.</p>

            <div class="baklava-card" style="max-width:500px;">
                <h3>Giriş Bilgilerini Güncelle</h3>
                <form method="post" action="admin.php?tab=security" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_password">
                    <div class="cyber-field">
                        <label>Yeni Kullanıcı Adı</label>
                        <input type="text" name="new_username" required value="<?= e($_SESSION['admin_user']) ?>">
                    </div>
                    <div class="cyber-field">
                        <label>Yeni Şifre</label>
                        <input type="password" name="new_password" required placeholder="En az 6 karakter">
                    </div>
                    <button type="submit" class="cyber-btn btn-cyan btn-full">Bilgileri Güncelle →</button>
                </form>
            </div>

        <?php elseif ($activeTab === 'logs'): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <div>
                    <h2>Sistem Audit Logları</h2>
                    <p style="color:var(--text-muted);">Tüm erişim, giriş ve içerik ekleme kayıtları.</p>
                </div>
                <form method="post" action="admin.php?tab=logs">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="cyber-btn btn-pink btn-sm">Logları Temizle</button>
                </form>
            </div>

            <div class="baklava-card">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Aksiyon</th><th>Detay</th><th>IP Adresi</th><th>Tarih</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td>#<?= $l['id'] ?></td>
                                <td><span style="color:var(--neon-cyan); font-family:var(--font-mono);"><?= e($l['action']) ?></span></td>
                                <td><?= e($l['details']) ?></td>
                                <td><code><?= e($l['ip_address']) ?></code></td>
                                <td><small><?= e($l['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($activeTab === 'leads'): ?>
            <h2>Müşteri Talepleri (<?= count($leads) ?>)</h2>
            <p style="color:var(--text-muted); margin-bottom:1.5rem;">İletişim formundan gelen iletiler.</p>

            <div class="baklava-card">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>İsim</th><th>E-Posta</th><th>Hizmet</th><th>Mesaj</th><th>IP</th><th>Tarih</th></tr></thead>
                    <tbody>
                        <?php foreach ($leads as $ld): ?>
                            <tr>
                                <td>#<?= $ld['id'] ?></td>
                                <td><strong><?= e($ld['name']) ?></strong></td>
                                <td><?= e($ld['email']) ?></td>
                                <td><?= e(SERVICES[$ld['service']] ?? $ld['service']) ?></td>
                                <td><?= e($ld['message']) ?></td>
                                <td><code><?= e($ld['ip_address']) ?></code></td>
                                <td><small><?= e($ld['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
