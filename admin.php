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

if ($pdo) {
    try {
        $check = $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ((int)$check === 0) {
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, email, password_hash, theme_pref) VALUES (:u, "admin@dovye.com.tr", :p, "dark")');
            $stmt->execute([':u' => DEFAULT_ADMIN_USER, ':p' => DEFAULT_ADMIN_HASH]);
        }
    } catch (Throwable $t) {}
}

$action = $_POST['action'] ?? '';

// LOGIN
if ($action === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Oturum geçersiz.';
    } elseif (!$pdo) {
        if ($user === DEFAULT_ADMIN_USER && password_verify($pass, DEFAULT_ADMIN_HASH)) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $user;
            $_SESSION['admin_theme'] = 'dark';
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
            $_SESSION['admin_theme'] = $row['theme_pref'] ?? 'dark';
            $_SESSION['last_activity'] = time();
            log_system_action('ADMIN_LOGIN_SUCCESS', "User: {$row['username']}");
            redirect('admin.php');
        } else {
            log_system_action('ADMIN_LOGIN_FAILED', "Attempted user: $user", 'WARN');
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
if ($action === 'update_security' && is_logged_in()) {
    if (csrf_valid() && $pdo) {
        $new_user = trim($_POST['new_username'] ?? '');
        $new_pass = trim($_POST['new_password'] ?? '');

        if (mb_strlen($new_user) < 3) {
            flash('err', 'Kullanıcı adı en az 3 karakter olmalı.');
        } elseif (mb_strlen($new_pass) < 6) {
            flash('err', 'Şifre en az 6 karakter olmalı.');
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $curr = $_SESSION['admin_user'] ?? DEFAULT_ADMIN_USER;

            $stmt = $pdo->prepare('UPDATE admin_users SET username = :u, password_hash = :p WHERE username = :c');
            $stmt->execute([':u' => $new_user, ':p' => $hash, ':c' => $curr]);

            $_SESSION['admin_user'] = $new_user;
            log_system_action('ADMIN_CREDENTIALS_UPDATED', "User: $new_user");
            flash('ok', 'Yönetici bilgileri başarıyla güncellendi!');
        }
    }
    redirect('admin.php?tab=security');
}

// UPDATE ADMIN THEME PREFERENCE
if ($action === 'update_theme' && is_logged_in()) {
    if (csrf_valid()) {
        $theme = $_POST['theme'] ?? 'dark';
        $_SESSION['admin_theme'] = $theme;
        if ($pdo) {
            $stmt = $pdo->prepare('UPDATE admin_users SET theme_pref = :t WHERE username = :u');
            $stmt->execute([':t' => $theme, ':u' => $_SESSION['admin_user']]);
        }
        flash('ok', 'Admin paneli teması güncellendi!');
    }
    redirect('admin.php');
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
            log_system_action('YOUTUBE_VIDEO_ADDED', "Title: $title");
            flash('ok', 'Yeni YouTube videosu eklendi ve Hub üzerinde yayınlandı!');
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
        log_system_action('YOUTUBE_VIDEO_DELETED', "ID: $id");
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
        $provider = trim($_POST['provider'] ?? 'Skyline / YouTube');

        if (empty($title) || empty($embed)) {
            flash('err', 'Başlık ve Embed URL zorunludur.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO live_cams (title, location, embed_url, provider) VALUES (:t, :l, :e, :p)');
            $stmt->execute([':t' => $title, ':l' => $location, ':e' => $embed, ':p' => $provider]);
            log_system_action('LIVE_CAM_ADDED', "Title: $title");
            flash('ok', 'Canlı kamera akışı başarıyla eklendi!');
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
        log_system_action('LIVE_CAM_DELETED', "ID: $id");
        flash('ok', 'Kamera silindi.');
    }
    redirect('admin.php?tab=cameras');
}

// ---------------------------------------------------------------------------
// VIEW CONTROLLER
// ---------------------------------------------------------------------------
if (!is_logged_in()) {
    ?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş — DOVYE Enterprise Admin Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body style="display:grid; place-items:center; min-height:100vh;">
<div class="cyber-grid-bg"></div>

<div class="cyber-card" style="width:100%; max-width:400px;">
    <div style="display:flex; align-items:center; gap:0.5rem; font-weight:800; color:var(--primary-cyan); margin-bottom:1.5rem;">
        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
        <span>DOVYE ENTERPRISE OS v6.0</span>
    </div>

    <h2>Yönetici Girişi</h2>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;">Sistem kontrol paneline erişim.</p>

    <?php if ($errors): ?>
        <div style="color:var(--accent-pink); margin-bottom:1rem; font-size:0.85rem;"><?= e($errors[0]) ?></div>
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
        <button type="submit" class="cyber-btn btn-cyan" style="width:100%;">Giriş Yap →</button>
    </form>
</div>
</body>
</html>
    <?php
    exit;
}

$activeTab = $_GET['tab'] ?? 'dashboard';
$adminTheme = $_SESSION['admin_theme'] ?? 'dark';

$yt_videos = $pdo ? $pdo->query('SELECT * FROM youtube_videos ORDER BY id DESC')->fetchAll() : [];
$cams      = $pdo ? $pdo->query('SELECT * FROM live_cams ORDER BY id DESC')->fetchAll() : [];
$nodes     = $pdo ? $pdo->query('SELECT * FROM cluster_nodes ORDER BY id DESC')->fetchAll() : [];
$logs      = $pdo ? $pdo->query('SELECT * FROM system_logs ORDER BY id DESC LIMIT 50')->fetchAll() : [];

$flash = flash();
?>
<!DOCTYPE html>
<html lang="tr" data-theme="<?= e($adminTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enterprise Admin OS v6.0 — DOVYE Studio</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div style="padding:1.5rem; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:0.75rem;">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
            <div>
                <strong style="display:block;">DOVYE<span>CORE</span></strong>
                <small style="color:var(--text-muted); font-family:'JetBrains Mono',monospace;">ENTERPRISE OS v6.0</small>
            </div>
        </div>

        <nav style="padding:1rem 0; flex:1;">
            <a href="admin.php?tab=dashboard" class="admin-nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">Genel Bakış</a>
            <a href="admin.php?tab=youtube" class="admin-nav-item <?= $activeTab === 'youtube' ? 'active' : '' ?>">YouTube Video Hub</a>
            <a href="admin.php?tab=cameras" class="admin-nav-item <?= $activeTab === 'cameras' ? 'active' : '' ?>">Canlı Kameralar</a>
            <a href="admin.php?tab=nodes" class="admin-nav-item <?= $activeTab === 'nodes' ? 'active' : '' ?>">Cluster Nodes</a>
            <a href="admin.php?tab=logs" class="admin-nav-item <?= $activeTab === 'logs' ? 'active' : '' ?>">Sistem Logları</a>
            <a href="admin.php?tab=security" class="admin-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>">Şifre & Güvenlik</a>
        </nav>

        <div style="padding:1.25rem; border-top:1px solid var(--border-color);">
            <form method="post" action="admin.php" style="margin-bottom:0.75rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_theme">
                <input type="hidden" name="theme" value="<?= $adminTheme === 'dark' ? 'light' : 'dark' ?>">
                <button type="submit" class="cyber-btn btn-ghost btn-sm" style="width:100%;">
                    Tema Değiştir (<?= $adminTheme === 'dark' ? 'Açık Moda Geç' : 'Koyu Moda Geç' ?>)
                </button>
            </form>

            <form method="post" action="admin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="cyber-btn btn-ghost btn-sm" style="width:100%;">Çıkış Yap</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <?php if ($flash): ?>
            <div style="padding:1rem; background:var(--bg-card); border:1px solid var(--primary-cyan); margin-bottom:1.5rem;">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'dashboard'): ?>
            <h2>Genel Bakış Merkezi</h2>
            <p style="color:var(--text-muted); margin-bottom:2rem;">DOVYE Studio Enterprise OS v6.0 Sistem Telemetrisi</p>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem;">
                <div class="cyber-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">YouTube Videoları</span>
                    <div style="font-size:2.2rem; font-weight:800; color:var(--primary-cyan);"><?= count($yt_videos) ?></div>
                </div>
                <div class="cyber-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">Canlı Kameralar</span>
                    <div style="font-size:2.2rem; font-weight:800; color:var(--accent-pink);"><?= count($cams) ?></div>
                </div>
                <div class="cyber-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">Cluster Sunucu Düğümleri</span>
                    <div style="font-size:2.2rem; font-weight:800; color:var(--accent-emerald);"><?= count($nodes) ?></div>
                </div>
                <div class="cyber-card">
                    <span style="color:var(--text-muted); font-size:0.8rem;">Sistem Audit Kayıtları</span>
                    <div style="font-size:2.2rem; font-weight:800; color:var(--accent-amber);"><?= count($logs) ?></div>
                </div>
            </div>

        <?php elseif ($activeTab === 'youtube'): ?>
            <h2>YouTube Video Yönetimi</h2>
            <div class="cyber-card" style="margin-bottom:2rem;">
                <h3>Yeni YouTube Videosu Ekle</h3>
                <form method="post" action="admin.php?tab=youtube" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_video">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
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
                        <input type="text" name="category" placeholder="Lo-Fi / Cyberpunk / City Walk">
                    </div>
                    <button type="submit" class="cyber-btn btn-cyan">Videoyu Ekle ve Yayınla →</button>
                </form>
            </div>

            <div class="cyber-card">
                <h3>Yayınlanan Videolar (<?= count($yt_videos) ?>)</h3>
                <table class="cyber-table">
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
            <h2>Canlı Kameralar Yönetimi</h2>
            <div class="cyber-card" style="margin-bottom:2rem;">
                <h3>Yeni Canlı Kamera Ekle</h3>
                <form method="post" action="admin.php?tab=cameras" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_cam">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="cyber-field">
                            <label>Kamera Başlığı</label>
                            <input type="text" name="title" required placeholder="Duomo di Milano Cam">
                        </div>
                        <div class="cyber-field">
                            <label>Lokasyon</label>
                            <input type="text" name="location" placeholder="Milano, Italia">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem;">
                        <div class="cyber-field">
                            <label>Embed / Akış URL (Skyline / YouTube Embed)</label>
                            <input type="url" name="embed_url" required placeholder="https://www.skylinewebcams.com/en/webcam/italia/lombardia/milano/duomo-milano.html">
                        </div>
                        <div class="cyber-field">
                            <label>Sağlayıcı</label>
                            <input type="text" name="provider" placeholder="SkylineWebcams">
                        </div>
                    </div>
                    <button type="submit" class="cyber-btn btn-cyan">Kamerayı Ekle →</button>
                </form>
            </div>

            <div class="cyber-card">
                <h3>Aktif Canlı Kameralar (<?= count($cams) ?>)</h3>
                <table class="cyber-table">
                    <thead><tr><th>ID</th><th>Başlık</th><th>Lokasyon</th><th>Sağlayıcı</th><th>İşlem</th></tr></thead>
                    <tbody>
                        <?php foreach ($cams as $c): ?>
                            <tr>
                                <td>#<?= $c['id'] ?></td>
                                <td><strong><?= e($c['title']) ?></strong></td>
                                <td><?= e($c['location']) ?></td>
                                <td><?= e($c['provider']) ?></td>
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

        <?php elseif ($activeTab === 'nodes'): ?>
            <h2>Cluster Sunucu Düğümleri (Telemetri)</h2>
            <div class="cyber-card">
                <table class="cyber-table">
                    <thead><tr><th>ID</th><th>Sunucu Adı</th><th>IP Adresi</th><th>Rol</th><th>CPU</th><th>RAM</th><th>Durum</th></tr></thead>
                    <tbody>
                        <?php foreach ($nodes as $n): ?>
                            <tr>
                                <td>#<?= $n['id'] ?></td>
                                <td><strong><?= e($n['node_name']) ?></strong></td>
                                <td><code><?= e($n['ip_address']) ?></code></td>
                                <td><?= e($n['role']) ?></td>
                                <td><?= $n['cpu_usage'] ?>%</td>
                                <td><?= $n['ram_usage'] ?>%</td>
                                <td><span style="color:var(--accent-emerald); font-weight:700;"><?= e($n['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($activeTab === 'security'): ?>
            <h2>Şifre ve Yönetici Güvenliği</h2>
            <div class="cyber-card" style="max-width:500px;">
                <h3>Giriş Bilgilerini Güncelle</h3>
                <form method="post" action="admin.php?tab=security" style="margin-top:1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_security">
                    <div class="cyber-field">
                        <label>Yeni Kullanıcı Adı</label>
                        <input type="text" name="new_username" required value="<?= e($_SESSION['admin_user']) ?>">
                    </div>
                    <div class="cyber-field">
                        <label>Yeni Şifre</label>
                        <input type="password" name="new_password" required placeholder="En az 6 karakter">
                    </div>
                    <button type="submit" class="cyber-btn btn-cyan" style="width:100%;">Güncelle →</button>
                </form>
            </div>

        <?php elseif ($activeTab === 'logs'): ?>
            <h2>Sistem Audit Logları</h2>
            <div class="cyber-card">
                <table class="cyber-table">
                    <thead><tr><th>ID</th><th>Aksiyon</th><th>Detay</th><th>IP</th><th>Tarih</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td>#<?= $l['id'] ?></td>
                                <td><code><?= e($l['action']) ?></code></td>
                                <td><?= e($l['details']) ?></td>
                                <td><code><?= e($l['ip_address']) ?></code></td>
                                <td><small><?= e($l['created_at']) ?></small></td>
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
