<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = db();
$errors = [];

// Form submission logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    if (!csrf_valid()) {
        $errors[] = 'Oturum doğrulanamadı. Lütfen sayfayı yenileyiniz.';
    } elseif (!$pdo) {
        $errors[] = 'Veritabanı bağlantısı yok.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $service = trim($_POST['service'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (mb_strlen($name) < 2) $errors[] = 'Ad soyad en az 2 karakter olmalıdır.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta giriniz.';
        if (!array_key_exists($service, SERVICES)) $errors[] = 'Lütfen listeden bir hizmet seçiniz.';
        if (mb_strlen($message) < 10) $errors[] = 'Mesajınız en az 10 karakter olmalıdır.';

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('INSERT INTO leads (name, email, service, message, ip_address) VALUES (:n, :e, :s, :m, :ip)');
                $stmt->execute([':n' => $name, ':e' => $email, ':s' => $service, ':m' => $message, ':ip' => client_ip()]);
                log_system_action('NEW_LEAD_SUBMITTED', "Name: $name, Email: $email");
                flash('ok', 'Talebiniz kaydedildi. Ekibimiz 24 saat içinde dönüş yapacaktır.');
                redirect('index.php#module-20');
            } catch (PDOException $ex) {
                $errors[] = 'Kayıt sırasında bir hata oluştu.';
            }
        }
    }
}

// Fetch YouTube Videos
$videos = [];
if ($pdo) {
    try {
        $videos = $pdo->query('SELECT * FROM youtube_videos ORDER BY is_featured DESC, id DESC LIMIT 12')->fetchAll();
    } catch (Throwable $t) {}
}

// Fetch Live Cameras
$liveCams = [];
if ($pdo) {
    try {
        $liveCams = $pdo->query('SELECT * FROM live_cams ORDER BY id DESC LIMIT 6')->fetchAll();
    } catch (Throwable $t) {}
}

$flash = flash();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DOVYE STUDIO — Next-Gen Cyber Ecosystem</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="cyber-grid-bg"></div>
<div class="ambient-orb orb-cyan"></div>
<div class="ambient-orb orb-pink"></div>

<!-- UNUSUAL BAKLAVA FLOATING NAVBAR (Top-Left, floating, not stuck) -->
<nav class="floating-baklava-nav">
    <div class="baklava-brand">
        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
        <span>DOVYE</span>.STUDIO
    </div>
    <div class="baklava-links">
        <a href="#module-1" class="baklava-link active">Ecosystem</a>
        <a href="#module-3" class="baklava-link">YouTube Vault</a>
        <a href="#module-4" class="baklava-link">World Cams</a>
        <a href="#module-20" class="baklava-link">İletişim</a>
        <a href="admin.php" class="cyber-btn btn-cyan btn-sm" target="_blank">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Yönetici Paneli</span>
        </a>
    </div>
</nav>

<div class="public-cyber-container">
    <div class="modules-masonry">

        <!-- MODULE 1: Cyber Hero & Matrix Intro -->
        <div class="baklava-card module-card" id="module-1">
            <div>
                <div class="module-header">
                    <span class="module-num">MOD_01 // CORE</span>
                    <span class="cam-status"><span class="pulse-red"></span> SYSTEM ONLINE</span>
                </div>
                <h2>Geleceğin Dijital Ekosistemi</h2>
                <p style="color: var(--text-sub); margin-top: 0.75rem;">DOVYE Studio, yüksek hızlı mikro-servis mimarileri ve interaktif medya akış motorları üreten bağımsız teknoloji stüdyosudur.</p>
            </div>
            <div style="margin-top: 1.5rem;">
                <a href="#module-3" class="cyber-btn btn-cyan btn-full">YouTube & Medya Motoruna Git →</a>
            </div>
        </div>

        <!-- MODULE 2: Dynamic Live Telemetry -->
        <div class="baklava-card module-card" id="module-2">
            <div>
                <div class="module-header">
                    <span class="module-num">MOD_02 // TELEMETRY</span>
                    <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--neon-cyan);">PING: 12ms</span>
                </div>
                <h3>Sistem Performans Metrikleri</h3>
                <div style="margin-top: 1rem; display: grid; gap: 0.75rem; font-family: var(--font-mono); font-size: 0.85rem;">
                    <div style="display:flex; justify-content:space-between;"><span>Proxy Engine:</span> <span style="color: var(--neon-emerald);">Traefik SSL 2.10</span></div>
                    <div style="display:flex; justify-content:space-between;"><span>Database Cluster:</span> <span style="color: var(--neon-cyan);">MariaDB 11.2</span></div>
                    <div style="display:flex; justify-content:space-between;"><span>Memory Efficiency:</span> <span style="color: var(--neon-pink);">18.4 MB / 512 MB</span></div>
                </div>
            </div>
        </div>

        <!-- MODULE 3: YOUTUBE VAULT (3rd Main Page/Module - YouTube Engine) -->
        <div class="baklava-card module-card" id="module-3" style="grid-column: 1 / -1;">
            <div>
                <div class="module-header">
                    <span class="module-num">MOD_03 // YOUTUBE HUB</span>
                    <span style="color: var(--neon-pink); font-family: var(--font-mono); font-size: 0.8rem;">YENİ VİDEOLAR AKTI</span>
                </div>
                <h2>YouTube Video Hub & Medya Oynatıcı</h2>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Yönetim panelinden eklenen tüm YouTube videoları anında burada yayınlanır.</p>

                <div class="youtube-grid">
                    <?php if (!$videos): ?>
                        <p style="color: var(--text-muted);">Henüz eklenmiş video yok. Admin panelinden YouTube URL'leri ekleyin.</p>
                    <?php else: ?>
                        <?php foreach ($videos as $v): ?>
                            <div class="video-card">
                                <div class="video-thumb">
                                    <iframe src="https://www.youtube.com/embed/<?= e($v['video_id']) ?>" allowfullscreen></iframe>
                                </div>
                                <div class="video-info">
                                    <div class="video-title"><?= e($v['title']) ?></div>
                                    <div class="video-meta">
                                        <span>Kategori: <?= e($v['category']) ?></span>
                                        <span><?= number_format((int)$v['views_count']) ?> izlenme</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MODULE 4: LIVE WORLD CAMERAS (Global Live Stream Engine) -->
        <div class="baklava-card module-card" id="module-4" style="grid-column: 1 / -1;">
            <div>
                <div class="module-header">
                    <span class="module-num">MOD_04 // WORLD CAMS</span>
                    <span class="cam-status"><span class="pulse-red"></span> CANLI AKIŞ KANALLARI</span>
                </div>
                <h2>Dünya Canlı Kamera Yayınları</h2>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Admin panelinden yönetilen canlı şehir ve uzay kameraları.</p>

                <div class="cam-grid">
                    <?php if (!$liveCams): ?>
                        <p style="color: var(--text-muted);">Canlı kamera yayını eklenmemiş.</p>
                    <?php else: ?>
                        <?php foreach ($liveCams as $cam): ?>
                            <div class="cam-card">
                                <div class="cam-frame">
                                    <iframe src="<?= e($cam['embed_url']) ?>" allowfullscreen></iframe>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="display:block; font-size: 0.9rem;"><?= e($cam['title']) ?></strong>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;"><?= e($cam['location']) ?></small>
                                    </div>
                                    <span class="cam-status"><span class="pulse-red"></span> <?= e($cam['status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MODULE 5: Interactive Cyber Terminal -->
        <div class="baklava-card module-card" id="module-5">
            <div>
                <div class="module-header"><span class="module-num">MOD_05 // TERMINAL</span></div>
                <h3>Sanal CLI Konsolu</h3>
                <div style="background: #000; padding: 1rem; font-family: var(--font-mono); font-size: 0.8rem; border-radius: 6px; margin-top: 1rem; color: #4ade80;">
                    <div>$ dovye --status</div>
                    <div style="color: #94a3b8;">[OK] Docker Container Stack initialized</div>
                    <div style="color: #94a3b8;">[OK] Traefik SSL certificate validated</div>
                    <div>$ dovye --version</div>
                    <div style="color: var(--neon-cyan);">DOVYE Studio Core v3.0.4</div>
                </div>
            </div>
        </div>

        <!-- MODULE 6: Tech Stack Radar -->
        <div class="baklava-card module-card" id="module-6">
            <div>
                <div class="module-header"><span class="module-num">MOD_06 // TECH RADAR</span></div>
                <h3>Kullanılan Teknolojiler</h3>
                <ul style="list-style:none; padding:0; margin-top:1rem; display:grid; gap:0.5rem; font-size:0.875rem;">
                    <li>🔹 <strong>PHP 8.3 & PDO:</strong> Güvenli arka plan altyapısı</li>
                    <li>🔹 <strong>MariaDB 11:</strong> İlişkisel veri deposu</li>
                    <li>🔹 <strong>Docker & Traefik:</strong> Konteynerleştirilmiş yayın</li>
                </ul>
            </div>
        </div>

        <!-- MODULE 7 to 19: Extra Cyber Modules -->
        <div class="baklava-card module-card" id="module-7">
            <div class="module-header"><span class="module-num">MOD_07 // SHADER LAB</span></div>
            <h3>Görsel Deneyler</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Canvas tabanlı ışık parçacık simülatörü aktif.</p>
        </div>

        <div class="baklava-card module-card" id="module-8">
            <div class="module-header"><span class="module-num">MOD_08 // AUDIO SYNTH</span></div>
            <h3>Ambient Ses Motoru</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Odaklanma için Lo-Fi ve Synthwave ses üretici.</p>
        </div>

        <div class="baklava-card module-card" id="module-9">
            <div class="module-header"><span class="module-num">MOD_09 // SECURITY AUDIT</span></div>
            <h3>CSRF & Injection Shield</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Girdiler kaçış süzgecinden geçirilir.</p>
        </div>

        <div class="baklava-card module-card" id="module-10">
            <div class="module-header"><span class="module-num">MOD_10 // WORLD CLOCKS</span></div>
            <h3>Küresel Zamanlar</h3>
            <div style="font-family: var(--font-mono); font-size:0.85rem; margin-top:0.5rem;">
                Tokyo: <?= date('H:i', time() + 21600) ?> | London: <?= date('H:i', time() - 10800) ?> | NYC: <?= date('H:i', time() - 25200) ?>
            </div>
        </div>

        <!-- MODULE 20: Reimagined Futuristic Lead Form -->
        <div class="baklava-card module-card" id="module-20" style="grid-column: 1 / -1;">
            <div>
                <div class="module-header">
                    <span class="module-num">MOD_20 // CONTACT MATRIX</span>
                    <span style="color: var(--neon-cyan);">PROJE BAŞLATIN</span>
                </div>
                <h2>Bizimle İletişime Geçin</h2>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Projenizin detaylarını iletin, en kısa sürede dönüş yapalım.</p>

                <?php if ($flash): ?>
                    <div class="cyber-alert alert-ok"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="cyber-alert alert-err">
                        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php#module-20">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="contact">

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="cyber-field">
                            <label>Ad Soyad *</label>
                            <input type="text" name="name" required placeholder="Ahmet Yılmaz">
                        </div>
                        <div class="cyber-field">
                            <label>E-Posta *</label>
                            <input type="email" name="email" required placeholder="ahmet@sirket.com">
                        </div>
                    </div>

                    <div class="cyber-field">
                        <label>Hizmet Kategorisi *</label>
                        <select name="service" required>
                            <option value="">Seçiniz...</option>
                            <?php foreach (SERVICES as $k => $v): ?>
                                <option value="<?= e($k) ?>"><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cyber-field">
                        <label>Mesaj / Proje Notu *</label>
                        <textarea name="message" required placeholder="Projenizi özetleyin..."></textarea>
                    </div>

                    <button type="submit" class="cyber-btn btn-cyan btn-full">Gönder →</button>
                </form>
            </div>
        </div>

    </div>
</div>

</body>
</html>
