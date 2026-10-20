<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = db();

$videos = [];
if ($pdo) {
    try {
        $videos = $pdo->query('SELECT * FROM youtube_videos ORDER BY id DESC')->fetchAll();
    } catch (Throwable $t) {}
}

$liveCams = [];
if ($pdo) {
    try {
        $liveCams = $pdo->query('SELECT * FROM live_cams ORDER BY id DESC')->fetchAll();
    } catch (Throwable $t) {}
}

$firstVideoId = $videos[0]['video_id'] ?? 'Leed38_x0_0';
$firstVideoTitle = $videos[0]['title'] ?? 'Cyberpunk 2077 — Night City Ambient Drive 4K';
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DOVYE STUDIO — Mega Cyber Portal v5.0</title>
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

<!-- UNUSUAL FLOATING BAKLAVA NAVBAR (Sticky, Smooth Tracking) -->
<nav class="floating-baklava-nav">
    <div class="baklava-brand">
        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 17 22 12"/></svg>
        <span>DOVYE</span>.STUDIO
    </div>
    <div class="baklava-menu">
        <a href="#page-youtube" class="baklava-link active">1. YouTube Theatre</a>
        <a href="#page-cams" class="baklava-link">2. Canlı Kameralar</a>
        <a href="#page-weather" class="baklava-link">3. Hava Durumu</a>
        <a href="#page-currency" class="baklava-link">4. Döviz & Piyasa</a>
        <a href="#page-hosting" class="baklava-link">5. Data Center</a>
        <a href="#page-arcade" class="baklava-link">6. Cyber Arcade</a>
        
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn">
            <span id="themeBtnText">Tema</span>
        </button>

        <a href="admin.php" class="cyber-btn btn-cyan btn-sm" target="_blank">Admin OS →</a>
    </div>
</nav>

<!-- PERSISTENT CUTE CYBER CAT WIDGET (TOP-RIGHT) -->
<div class="cyber-cat-widget" id="cyberCatWidget">
    <div class="cat-avatar">🐱</div>
    <div class="cat-speech" id="catSpeechText">Miyav! DOVYE Studio'ya hoşgeldin!</div>
</div>

<!-- PAGE 1: YOUTUBE THEATRE & CUSTOM OUTSIDE CONTROLS (100vh Full Page) -->
<section class="full-page-section" id="page-youtube">
    <div class="section-header-bar">
        <div>
            <h2>1. YouTube Theatre & External Control Hub</h2>
            <p style="color:var(--text-muted); font-size:0.875rem;">Solda dikey kontrol barları, ortada geniş video oynatıcısı, sağda sıralı playlist.</p>
        </div>
        <span class="section-tag">FULL SCREEN THEATRE MODE</span>
    </div>

    <div class="custom-theatre-layout">
        <!-- Outside Vertical Control Bar -->
        <div class="vertical-control-bar">
            <button type="button" class="v-btn" title="Oynat / Durdur" onclick="togglePlayPause()">⏯</button>
            <button type="button" class="v-btn" title="Sesi Kapat / Aç" onclick="toggleMute()">🔇</button>
            <button type="button" class="v-btn" title="Yeniden Başlat" onclick="restartVideo()">🔄</button>
            <button type="button" class="v-btn" title="HD Kalite" onclick="setHD()">4K</button>
        </div>

        <!-- Main Video Screen -->
        <div>
            <div class="video-screen-box">
                <iframe id="mainYoutubeFrame" src="https://www.youtube.com/embed/<?= e($firstVideoId) ?>?enablejsapi=1&autoplay=0" allowfullscreen></iframe>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                <h3 id="mainVideoTitle" style="font-size:1.2rem;"><?= e($firstVideoTitle) ?></h3>
                <span style="font-family:'JetBrains Mono',monospace; color:var(--primary-cyan); font-weight:700;">4K ULTRA HD STREAM</span>
            </div>
        </div>

        <!-- Right Playlist -->
        <div class="playlist-sidebar">
            <h4 style="font-size:0.9rem; margin-bottom:0.5rem; color:var(--text-muted); text-transform:uppercase;">Videolar (<?= count($videos) ?>)</h4>
            <?php foreach ($videos as $idx => $v): ?>
                <div class="playlist-card <?= $idx === 0 ? 'active' : '' ?>" onclick="switchVideo('<?= e($v['video_id']) ?>', '<?= e(addslashes($v['title'])) ?>', this)">
                    <div class="mini-thumb" style="background-image: url('https://img.youtube.com/vi/<?= e($v['video_id']) ?>/mqdefault.jpg');"></div>
                    <div>
                        <div style="font-size:0.85rem; font-weight:700;"><?= e($v['title']) ?></div>
                        <small style="color:var(--text-muted); font-family:'JetBrains Mono',monospace;"><?= e($v['category']) ?> • <?= number_format((int)$v['views_count']) ?> izlenme</small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PAGE 2: LIVE WORLD CAMERAS (Skyline & Live Streams) -->
<section class="full-page-section" id="page-cams">
    <div class="section-header-bar">
        <div>
            <h2>2. Dünya Canlı Kameralar (SkylineWebcams & Live Streams)</h2>
            <p style="color:var(--text-muted); font-size:0.875rem;">Milano Duomo, Tokyo Shibuya, Times Square ve canlı dünya kameraları.</p>
        </div>
        <span class="section-tag">LIVE WORLD TELEMETRY</span>
    </div>

    <div class="cams-full-grid">
        <?php foreach ($liveCams as $cam): ?>
            <div class="cam-box">
                <div class="cam-viewport">
                    <iframe src="<?= e($cam['embed_url']) ?>" allowfullscreen></iframe>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-size:1rem; display:block;"><?= e($cam['title']) ?></strong>
                        <small style="color:var(--text-muted);"><?= e($cam['location']) ?> • <?= e($cam['provider']) ?></small>
                    </div>
                    <span style="color:var(--accent-emerald); font-weight:700; font-family:'JetBrains Mono',monospace;"><?= e($cam['status']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- PAGE 3: WEATHER VISUALIZER PAGE -->
<section class="full-page-section" id="page-weather">
    <div class="section-header-bar">
        <div>
            <h2>3. Canlı Hava Durumu & Uydu Haritası</h2>
            <p style="color:var(--text-muted); font-size:0.875rem;">Türkiye ve dünya genelinde bölgesel temsil haritası ve hava raporları.</p>
        </div>
        <span class="section-tag">METEOROLOGY MATRIX</span>
    </div>

    <div class="weather-layout">
        <div class="weather-map-card">
            <h3>Türkiye Bölgesel Radar Haritası</h3>
            <div class="turkey-svg-map">
                <div style="text-align:center;">
                    <div style="font-size:3rem;">🌩️ ☀️ 🌧️</div>
                    <div style="font-family:'JetBrains Mono',monospace; color:var(--primary-cyan); margin-top:0.5rem;">İSTANBUL: 19°C | ANKARA: 15°C | İZMİR: 22°C | ANTALYA: 25°C</div>
                </div>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:1rem;">
            <div class="cam-box">
                <h4>Milano</h4>
                <div style="font-size:2rem; font-weight:800; color:var(--primary-cyan);">16 °C</div>
                <small style="color:var(--text-muted);">Açık Güneşli • Nem %45</small>
            </div>
            <div class="cam-box">
                <h4>Tokyo</h4>
                <div style="font-size:2rem; font-weight:800; color:var(--accent-pink);">22 °C</div>
                <small style="color:var(--text-muted);">Hafif Yağmurlu • Nem %70</small>
            </div>
            <div class="cam-box">
                <h4>New York</h4>
                <div style="font-size:2rem; font-weight:800; color:var(--accent-amber);">18 °C</div>
                <small style="color:var(--text-muted);">Rüzgarlı • Nem %50</small>
            </div>
        </div>
    </div>
</section>

<!-- PAGE 4: CURRENCY & CRYPTO MARKET PAGE -->
<section class="full-page-section" id="page-currency">
    <div class="section-header-bar">
        <div>
            <h2>4. Piyasa Döviz Kurları, Altın & Kripto</h2>
            <p style="color:var(--text-muted); font-size:0.875rem;">Anlık borsa ve finansal veri grafikleri.</p>
        </div>
        <span class="section-tag">FINANCIAL TICKER</span>
    </div>

    <div class="currency-table-box">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem;">
            <div class="cam-box">
                <span style="color:var(--text-muted); font-size:0.85rem;">USD / TRY</span>
                <div style="font-size:2.2rem; font-weight:800; color:var(--accent-emerald);">34.82 ₺</div>
                <small style="color:var(--accent-emerald);">▲ %0.12 Yükselişte</small>
            </div>
            <div class="cam-box">
                <span style="color:var(--text-muted); font-size:0.85rem;">EUR / TRY</span>
                <div style="font-size:2.2rem; font-weight:800; color:var(--accent-emerald);">38.15 ₺</div>
                <small style="color:var(--accent-emerald);">▲ %0.08 Yükselişte</small>
            </div>
            <div class="cam-box">
                <span style="color:var(--text-muted); font-size:0.85rem;">Gram Altın</span>
                <div style="font-size:2.2rem; font-weight:800; color:var(--accent-amber);">2.980 ₺</div>
                <small style="color:var(--accent-amber);">▲ %0.45 Yükselişte</small>
            </div>
            <div class="cam-box">
                <span style="color:var(--text-muted); font-size:0.85rem;">Bitcoin (BTC)</span>
                <div style="font-size:2.2rem; font-weight:800; color:var(--primary-cyan);">$67,450</div>
                <small style="color:var(--accent-emerald);">▲ %2.10 Boğa Piyasası</small>
            </div>
        </div>
    </div>
</section>

<!-- PAGE 5: HOSTING & DATA CENTER INFRASTRUCTURE -->
<section class="full-page-section" id="page-hosting">
    <div class="section-header-bar">
        <div>
            <h2>5. Bulut Hosting & Data Center Monitörü</h2>
            <p style="color:var(--text-muted); font-size:0.875rem;">Sunucu odası sıcaklık, güç ve uplink performansları.</p>
        </div>
        <span class="section-tag">HARDWARE TELEMETRY</span>
    </div>

    <div class="currency-table-box">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
            <div>
                <h3>Bulut Sunucular</h3>
                <div style="margin-top:1rem; display:flex; flex-direction:column; gap:1rem;">
                    <div class="cam-box"><strong>Clouding.io (Ubuntu VPS)</strong> — 99.99% Uptime</div>
                    <div class="cam-box"><strong>Hetzner Dedicated AX102</strong> — 10 Gbps Uplink</div>
                    <div class="cam-box"><strong>DigitalOcean Droplets</strong> — NVMe SSD</div>
                </div>
            </div>
            <div>
                <h3>Data Center Odası Metrikleri</h3>
                <div style="margin-top:1rem; display:flex; flex-direction:column; gap:1rem;">
                    <div class="cam-box"><strong>Ortam Sıcaklığı:</strong> 18.2 °C (Optimal)</div>
                    <div class="cam-box"><strong>Nem Oranı:</strong> %42</div>
                    <div class="cam-box"><strong>PUE Verimlilik:</strong> 1.12</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PAGE 6: CYBER ARCADE ZONE -->
<section class="full-page-section" id="page-arcade">
    <div class="section-header-bar">
        <div>
            <h2>6. Eğlence Alanı — Cyber Agario / Retro Arcade</h2>
            <p style="color:var(--text-muted); font-size:0.875rem;">Fare hareketlerinizle neon hücrenizi yönetin ve orbları toplayarak büyüyün!</p>
        </div>
        <span class="section-tag">INTERACTIVE GAME</span>
    </div>

    <div class="game-container-card">
        <h3>Cyber Blob Collector (Agario Edition)</h3>
        <p style="color:var(--text-muted); font-size:0.85rem;">Siber orbları toplayarak skorunuzu artırın!</p>
        <canvas id="cyberCanvas" width="900" height="420"></canvas>
        <div style="margin-top:1rem; font-family:'JetBrains Mono',monospace; color:var(--primary-cyan); font-size:1.1rem; font-weight:800;">
            Skorunuz: <span id="gameScore">0</span> Puan
        </div>
    </div>
</section>

<!-- FOOTER ("DGRWEB KİM YAAAAA") -->
<footer class="cyber-footer">
    <div class="dgrweb-title">DGRWEB KİM YAAAAA?!</div>
    <p style="color:var(--text-muted); margin-top:1rem;">DOVYE Studio — Master Cyber Portal OS v5.0</p>
</footer>

<script>
// Video Switcher
function switchVideo(id, title, elem) {
    document.getElementById('mainYoutubeFrame').src = 'https://www.youtube.com/embed/' + id + '?enablejsapi=1&autoplay=1';
    document.getElementById('mainVideoTitle').textContent = title;
    
    document.querySelectorAll('.playlist-card').forEach(el => el.classList.remove('active'));
    elem.classList.add('active');
}

// Custom Outside Controls Logic
function togglePlayPause() {
    const iframe = document.getElementById('mainYoutubeFrame');
    iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
}
function toggleMute() {
    const iframe = document.getElementById('mainYoutubeFrame');
    iframe.contentWindow.postMessage('{"event":"command","func":"mute","args":""}', '*');
}
function restartVideo() {
    const iframe = document.getElementById('mainYoutubeFrame');
    iframe.contentWindow.postMessage('{"event":"command","func":"seekTo","args":[0, true]}', '*');
}
function setHD() {
    alert('4K Ultra HD akış aktif!');
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

const savedTheme = localStorage.getItem('dovye_theme') || 'dark';
setTheme(savedTheme);

// Cyber Cat Interactivity
const catWidget = document.getElementById('cyberCatWidget');
const catSpeech = document.getElementById('catSpeechText');

const catQuotes = [
    "Miyav! Kodlar terbiye edildi!",
    "Skyline kameraları aktif!",
    "DGRWEB kim yaaa?!",
    "Siber dünya seni izliyor!",
    "DOVYE Studio v5.0 Hazır!"
];

catWidget.addEventListener('click', () => {
    const quote = catQuotes[Math.floor(Math.random() * catQuotes.length)];
    catSpeech.textContent = quote;
});

// Cyber Agario Canvas Game Logic
const canvas = document.getElementById('cyberCanvas');
const ctx = canvas.getContext('2d');
const scoreElem = document.getElementById('gameScore');

let player = { x: 450, y: 210, r: 16, color: '#00f2fe' };
let score = 0;
let foods = [];

for (let i = 0; i < 40; i++) {
    foods.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: 6,
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

    foods.forEach((f, idx) => {
        ctx.beginPath();
        ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
        ctx.fillStyle = f.color;
        ctx.fill();

        let dist = Math.hypot(player.x - f.x, player.y - f.y);
        if (dist < player.r + f.r) {
            score += 10;
            player.r = Math.min(50, player.r + 0.4);
            scoreElem.textContent = score;
            foods[idx] = {
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                r: 6,
                color: ['#ff007f', '#00f5a0', '#38bdf8', '#f59e0b'][Math.floor(Math.random() * 4)]
            };
        }
    });

    ctx.beginPath();
    ctx.arc(player.x, player.y, player.r, 0, Math.PI * 2);
    ctx.fillStyle = player.color;
    ctx.shadowBlur = 18;
    ctx.shadowColor = player.color;
    ctx.fill();
    ctx.shadowBlur = 0;

    requestAnimationFrame(gameLoop);
}
gameLoop();
</script>
</body>
</html>
