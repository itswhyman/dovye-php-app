<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = db();

// Fetch YouTube Videos
$videos = [];
if ($pdo) {
    try {
        $videos = $pdo->query('SELECT * FROM youtube_videos ORDER BY id DESC')->fetchAll();
    } catch (Throwable $t) {}
}

// Fetch Live Cameras
$liveCams = [];
if ($pdo) {
    try {
        $liveCams = $pdo->query('SELECT * FROM live_cams ORDER BY id DESC')->fetchAll();
    } catch (Throwable $t) {}
}

$firstVideoId = $videos[0]['video_id'] ?? 'Leed38_x0_0';
$firstVideoTitle = $videos[0]['title'] ?? 'Cyberpunk 2077 — Night City 4K Ambience';
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DOVYE STUDIO — Mega Cyber Portal v4.0</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="cyber-grid-bg"></div>
<div class="ambient-orb orb-1"></div>
<div class="ambient-orb orb-2"></div>

<!-- UNUSUAL BAKLAVA FLOATING NAVBAR (Floating top-left, polygon cuts) -->
<nav class="floating-baklava-nav">
    <div class="baklava-brand">
        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
        <span>DOVYE</span>.STUDIO
    </div>
    <div class="baklava-menu">
        <a href="#youtube-theatre" class="baklava-link active">YouTube Hub</a>
        <a href="#live-cameras" class="baklava-link">Canlı Kameralar</a>
        <a href="#weather-exchange" class="baklava-link">Hava & Döviz</a>
        <a href="#hosting-data" class="baklava-link">Data Center</a>
        <a href="#arcade-zone" class="baklava-link">Cyber Arcade</a>
        
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <span id="themeBtnText">Tema</span>
        </button>

        <a href="admin.php" class="cyber-btn btn-cyan btn-sm" target="_blank">Admin Panel →</a>
    </div>
</nav>

<div class="mega-container">

    <!-- PAGE 1 / MODULE 1: YOUTUBE THEATRE VIEW (Full Screen 100vh Layout) -->
    <section class="youtube-theatre-section" id="youtube-theatre">
        <div class="section-title-box">
            <div>
                <h2>YouTube Theatre & Video Player Hub</h2>
                <p style="color:var(--text-muted); font-size:0.875rem;">Solda seçilen ana video oynatılır, sağda tüm playlist sıralanır.</p>
            </div>
            <span class="section-tag">FULL SCREEN THEATRE MODE</span>
        </div>

        <div class="theatre-layout">
            <div class="main-player-box">
                <div class="video-screen">
                    <iframe id="mainYoutubeFrame" src="https://www.youtube.com/embed/<?= e($firstVideoId) ?>?autoplay=0" allowfullscreen></iframe>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 id="mainVideoTitle" style="font-size:1.15rem;"><?= e($firstVideoTitle) ?></h3>
                    <span style="font-family:'JetBrains Mono',monospace; color:var(--primary-cyan); font-size:0.8rem;">4K ULTRA HD STREAM</span>
                </div>
            </div>

            <div class="playlist-sidebar">
                <h4 style="font-size:0.9rem; margin-bottom:0.5rem; text-transform:uppercase; color:var(--text-muted);">Videolar (<?= count($videos) ?>)</h4>
                <?php foreach ($videos as $idx => $v): ?>
                    <div class="playlist-item <?= $idx === 0 ? 'active' : '' ?>" onclick="switchVideo('<?= e($v['video_id']) ?>', '<?= e(addslashes($v['title'])) ?>', this)">
                        <div class="mini-thumb" style="background-image: url('https://img.youtube.com/vi/<?= e($v['video_id']) ?>/mqdefault.jpg');"></div>
                        <div class="mini-info">
                            <div class="mini-title"><?= e($v['title']) ?></div>
                            <span class="mini-cat"><?= e($v['category']) ?> • <?= number_format((int)$v['views_count']) ?> views</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- PAGE 2 / MODULE 2: LIVE WORLD CAMS (SkylineWebcams & YouTube Live Embeds) -->
    <section id="live-cameras">
        <div class="section-title-box">
            <div>
                <h2>Dünya Canlı Kameralar (Skyline & Live Streams)</h2>
                <p style="color:var(--text-muted); font-size:0.875rem;">Milano Duomo, Tokyo Shibuya, Times Square ve canlı dünya kameraları.</p>
            </div>
            <span class="section-tag">LIVE WORLD TELEMETRY</span>
        </div>

        <div class="cams-grid">
            <?php foreach ($liveCams as $cam): ?>
                <div class="cam-card">
                    <div class="cam-viewport">
                        <iframe src="<?= e($cam['embed_url']) ?>" allowfullscreen></iframe>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="display:block; font-size:0.95rem;"><?= e($cam['title']) ?></strong>
                            <small style="color:var(--text-muted); font-size:0.775rem;"><?= e($cam['location']) ?> • <?= e($cam['provider']) ?></small>
                        </div>
                        <span class="badge-green"><?= e($cam['status']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- PAGE 3 & 4 / MODULE 3 & 4: WEATHER FORECAST & LIVE CURRENCY EXCHANGE -->
    <section id="weather-exchange">
        <div class="section-title-box">
            <div>
                <h2>Canlı Hava Durumu & Finans / Döviz Kurları</h2>
                <p style="color:var(--text-muted); font-size:0.875rem;">Anlık küresel hava verileri ve piyasa kuru takibi.</p>
            </div>
            <span class="section-tag">REALTIME DATA FEED</span>
        </div>

        <div class="modules-3col">
            <!-- Weather Module -->
            <div class="cyber-card">
                <h3>İstanbul & Dünya Hava Durumu</h3>
                <ul class="data-list">
                    <li class="data-row"><span>İstanbul</span> <span class="badge-green">19°C • Parçalı Bulutlu</span></li>
                    <li class="data-row"><span>Milano</span> <span class="badge-green">16°C • Açık Güneşli</span></li>
                    <li class="data-row"><span>Tokyo</span> <span class="badge-green">22°C • Yağmurlu</span></li>
                    <li class="data-row"><span>New York</span> <span class="badge-green">18°C • Rüzgarlı</span></li>
                    <li class="data-row"><span>Londra</span> <span class="badge-green">14°C • Sisli</span></li>
                </ul>
            </div>

            <!-- Currency Module -->
            <div class="cyber-card">
                <h3>Piyasa Döviz Kurları & Altın</h3>
                <ul class="data-list">
                    <li class="data-row"><span>USD / TRY</span> <span class="badge-pink">34.82 ₺ ▲ %0.12</span></li>
                    <li class="data-row"><span>EUR / TRY</span> <span class="badge-pink">38.15 ₺ ▲ %0.08</span></li>
                    <li class="data-row"><span>GBP / TRY</span> <span class="badge-pink">45.40 ₺ ▲ %0.15</span></li>
                    <li class="data-row"><span>Gram Altın</span> <span class="badge-green">2.980 ₺ ▲ %0.45</span></li>
                    <li class="data-row"><span>Bitcoin (BTC)</span> <span class="badge-green">$67,450 ▲ %2.10</span></li>
                </ul>
            </div>

            <!-- Global Tech News Module -->
            <div class="cyber-card">
                <h3>Teknoloji & AI Gelişmeleri</h3>
                <ul class="data-list">
                    <li class="data-row"><span>Docker v26 Cluster</span> <span class="badge-green">ONLINE</span></li>
                    <li class="data-row"><span>PHP 8.3 JIT Compiler</span> <span class="badge-green">ENABLED</span></li>
                    <li class="data-row"><span>Traefik SSL Cert</span> <span class="badge-green">VALID 90 DAYS</span></li>
                    <li class="data-row"><span>MariaDB Query Time</span> <span class="badge-green">0.42 ms</span></li>
                </ul>
            </div>
        </div>
    </section>

    <!-- PAGE 5 & 6 / MODULE 5 & 6: HOSTING PROVIDERS & DATA CENTER MONITORS -->
    <section id="hosting-data">
        <div class="section-title-box">
            <div>
                <h2>Hosting Sağlayıcıları & Data Center Altyapısı</h2>
                <p style="color:var(--text-muted); font-size:0.875rem;">Bulut sunucu sağlayıcılarının performans karşılaştırması ve sunucu durumları.</p>
            </div>
            <span class="section-tag">INFRASTRUCTURE BENCHMARK</span>
        </div>

        <div class="modules-3col">
            <div class="cyber-card">
                <h3>Bulut Hosting Sağlayıcıları</h3>
                <ul class="data-list">
                    <li class="data-row"><span>Clouding.io (Ubuntu VPS)</span> <span class="badge-green">99.99% Uptime</span></li>
                    <li class="data-row"><span>Hetzner Dedicated</span> <span class="badge-green">10 Gbps Uplink</span></li>
                    <li class="data-row"><span>DigitalOcean Droplets</span> <span class="badge-green">NVMe Storage</span></li>
                    <li class="data-row"><span>AWS EC2 Multi-AZ</span> <span class="badge-green">Enterprise</span></li>
                </ul>
            </div>

            <div class="cyber-card">
                <h3>Data Center Sunucu Odası Metrikleri</h3>
                <ul class="data-list">
                    <li class="data-row"><span>Ortam Sıcaklığı</span> <span class="badge-green">18.2 °C (Ideal)</span></li>
                    <li class="data-row"><span>Nem Oranı</span> <span class="badge-green">%42 Nem</span></li>
                    <li class="data-row"><span>Güç Tüketimi (PUE)</span> <span class="badge-green">1.12 PUE</span></li>
                    <li class="data-row"><span>Jeneratör & UPS</span> <span class="badge-green">STANDBY 100%</span></li>
                </ul>
            </div>
        </div>
    </section>

    <!-- PAGE 7, 8, 9 / MODULE 7-9: FUN & ENTERTAINMENT AREA (CYBER RETRO ARCADE AGARIO GAME) -->
    <section id="arcade-zone">
        <div class="section-title-box">
            <div>
                <h2>Eğlence Alanı — Cyber Agario / Retro Arcade Game</h2>
                <p style="color:var(--text-muted); font-size:0.875rem;">Fare hareketlerinizle siber parçacıkları toplayın ve büyüyün!</p>
            </div>
            <span class="section-tag">INTERACTIVE CANVAS GAME</span>
        </div>

        <div class="game-wrapper">
            <h3>Cyber Blob Collector (Agario Retro)</h3>
            <p style="color:var(--text-muted); font-size:0.85rem;">Fareyi hareket ettirerek siber hücrenizi yönetin ve neon orbları toplayarak puan kazanın!</p>
            <canvas id="cyberCanvas" width="800" height="400"></canvas>
            <div style="margin-top:0.75rem; font-family:'JetBrains Mono',monospace; color:var(--primary-cyan); font-weight:700;">
                Skorunuz: <span id="gameScore">0</span> Puan
            </div>
        </div>
    </section>

</div>

<!-- FOOTER ("DGRWEB KİM YAAAAA") -->
<footer class="cyber-footer">
    <div class="footer-content">
        <div class="dgrweb-signature">DGRWEB KİM YAAAAA?!</div>
        <p style="color:var(--text-muted); max-width:600px; font-size:0.9rem;">DOVYE Studio — Bütünsel mühendislik, yüksek hızlı medya oynatma motorları ve baklava kesim modern UI ekosistemi.</p>
        <div style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:var(--primary-cyan);">
            DOVYE Studio Multi-Domain Platform v4.0 • All Systems Nominal
        </div>
    </div>
</footer>

<script>
// Video Switcher
function switchVideo(id, title, elem) {
    document.getElementById('mainYoutubeFrame').src = 'https://www.youtube.com/embed/' + id + '?autoplay=1';
    document.getElementById('mainVideoTitle').textContent = title;
    
    document.querySelectorAll('.playlist-item').forEach(el => el.classList.remove('active'));
    elem.classList.add('active');
}

// Theme Switcher Logic
const themeBtn = document.getElementById('themeToggleBtn');
const themeText = document.getElementById('themeBtnText');

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('dovye_theme', theme);
    themeText.textContent = theme === 'light' ? 'Açık Tema' : 'Koyu Tema';
}

themeBtn.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// Load saved theme
const savedTheme = localStorage.getItem('dovye_theme') || 'dark';
setTheme(savedTheme);

// Cyber Agario Retro Game Logic
const canvas = document.getElementById('cyberCanvas');
const ctx = canvas.getContext('2d');
const scoreElem = document.getElementById('gameScore');

let player = { x: 400, y: 200, r: 15, color: '#00f2fe' };
let score = 0;
let foods = [];

for (let i = 0; i < 35; i++) {
    foods.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: 5,
        color: ['#ff007f', '#00f5a0', '#38bdf8', '#f59e0b'][Math.floor(Math.random() * 4)]
    });
}

canvas.addEventListener('mousemove', (e) => {
    const rect = canvas.getBoundingClientRect();
    player.x = e.clientX - rect.left;
    player.y = e.clientY - rect.top;
});

function gameLoop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw foods
    foods.forEach((f, idx) => {
        ctx.beginPath();
        ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
        ctx.fillStyle = f.color;
        ctx.fill();

        // Check collision
        let dist = Math.hypot(player.x - f.x, player.y - f.y);
        if (dist < player.r + f.r) {
            score += 10;
            player.r = Math.min(45, player.r + 0.5);
            scoreElem.textContent = score;
            foods[idx] = {
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                r: 5,
                color: ['#ff007f', '#00f5a0', '#38bdf8', '#f59e0b'][Math.floor(Math.random() * 4)]
            };
        }
    });

    // Draw player
    ctx.beginPath();
    ctx.arc(player.x, player.y, player.r, 0, Math.PI * 2);
    ctx.fillStyle = player.color;
    ctx.shadowBlur = 15;
    ctx.shadowColor = player.color;
    ctx.fill();
    ctx.shadowBlur = 0;

    requestAnimationFrame(gameLoop);
}
gameLoop();
</script>
</body>
</html>
