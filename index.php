<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$pdo = db();
$leaderboard = [];
if ($pdo) {
    try {
        $leaderboard = $pdo->query("SELECT * FROM game_leaderboards WHERE game_key = 'dgrwebio' ORDER BY high_score DESC LIMIT 5")->fetchAll();
    } catch (Throwable $t) {}
}
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TurkBil Oyun Fuarı — DGRWEB.IO Master Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="cyber-bg-grid"></div>

<!-- FIRST ENTRY NICKNAME & PROFILE SELECTION MODAL -->
<div class="nick-overlay" id="nickOverlay">
    <div class="nick-card">
        <h2 style="font-size:1.6rem; color:var(--primary-cyan);">TURKBİL OYUN FUARI</h2>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.4rem;">Lütfen oyuna başlamak için bir Nickname girin ve profil resminizi seçin:</p>

        <input type="text" id="playerNickInput" class="nick-input" placeholder="Kullanıcı Nick / Takma Ad" maxlength="15" autofocus>

        <div style="margin-bottom:1.2rem;">
            <label style="display:block; font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Profil Fotoğrafı / Avatar:</label>
            <input type="file" id="avatarFileInput" accept="image/*" style="display:none;" onchange="handleAvatarUpload(event)">
            <button type="button" class="cyber-btn btn-pink btn-sm" onclick="document.getElementById('avatarFileInput').click()">📷 Fotoğraf Yükle</button>
            <div id="avatarPreviewBox" style="margin-top:0.6rem; font-size:0.75rem; color:var(--accent-emerald);">Varsayılan Siber Avatar Seçili</div>
        </div>

        <button type="button" class="cyber-btn" style="width:100%;" onclick="startPlatform()">FUARA VE OYUNLARA GİRİŞ YAP →</button>
    </div>
</div>

<!-- FLOATING TOP HEADER NAVBAR -->
<header class="main-header">
    <div class="logo-text">TURKBİL <span>OYUN FUARI</span></div>
    <div style="display:flex; align-items:center; gap:1rem;">
        <span id="activeUserBadge" style="font-family:'JetBrains Mono',monospace; font-weight:700; color:var(--primary-cyan); font-size:0.85rem;">Oyuncu: -</span>
        <button type="button" class="cyber-btn btn-sm" onclick="location.reload()">🎮 Ana Menü</button>
        <button type="button" class="cyber-btn btn-sm" id="themeToggleBtn">🌓 Tema</button>
        <a href="admin.php" target="_blank" class="cyber-btn btn-pink btn-sm">Admin Panel</a>
    </div>
</header>

<!-- MAIN GAME SELECTION MENU -->
<main class="menu-container" id="menuContainer">
    <div style="text-align:center; margin-bottom:2.5rem;">
        <h1 style="font-size:2.2rem; letter-spacing:-0.03em;">SİBER OYUN STANDLARI (5 AKTİF OYUN)</h1>
        <p style="color:var(--text-muted);">Aşağıdan oynamak istediğiniz oyunu seçin veya DGRWEB.IO arena arenasına katılın!</p>
    </div>

    <div class="games-grid">
        <!-- GAME 1: DGRWEB.IO (AGARIO EDITION) -->
        <div class="game-card" onclick="launchGame('dgrwebio')" style="border-color:var(--primary-cyan); grid-column: 1 / -1;">
            <div>
                <span style="background:var(--primary-cyan); color:#000; font-family:'JetBrains Mono',monospace; font-size:0.7rem; font-weight:800; padding:0.2rem 0.6rem; clip-path:polygon(4px 0,100% 0,calc(100%-4px) 100%,0 100%);">1. OYUN — ANA STAND (FLAGSHIP)</span>
                <h2 style="font-size:1.8rem; margin-top:0.5rem; color:var(--primary-cyan);">dgrweb.io (DGRWEB Agario Engine)</h2>
                <p style="color:var(--text-muted); font-size:0.9rem; margin-top:0.4rem;">
                    6000px dev haritada 3 çeşit yem topla (Mavi:1, Sarı:2, Yeşil:4), fotoğraflı özel dairenle büyü! Kırmızı çarklardan kaçın veya bölün, V ile alev topu atarak rakibi ikiye böl, B ile sprint yap, C ile anında birleş!
                </p>
            </div>
            <div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-family:'JetBrains Mono',monospace; color:var(--accent-emerald);">● CANLI ONLİNE ARENA READY</span>
                <button class="cyber-btn">ARENAYA BASSIN →</button>
            </div>
        </div>

        <!-- GAME 2: CYBER SNAKE NEON -->
        <div class="game-card" onclick="launchMiniGame('snake')">
            <div>
                <span style="color:var(--accent-pink); font-family:'JetBrains Mono',monospace; font-size:0.75rem;">2. OYUN</span>
                <h3>Cyber Snake 2077</h3>
                <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.4rem;">Retro neon yılan oyunu. Yemleri yiyerek büyüyün, duvarlara çarpmayın!</p>
            </div>
            <button class="cyber-btn btn-sm" style="margin-top:1rem;">Oyna →</button>
        </div>

        <!-- GAME 3: FLAPPY BLOB ARCADE -->
        <div class="game-card" onclick="launchMiniGame('flappy')">
            <div>
                <span style="color:var(--accent-amber); font-family:'JetBrains Mono',monospace; font-size:0.75rem;">3. OYUN</span>
                <h3>Flappy Blob Arcadius</h3>
                <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.4rem;">Engellerin arasından zıplayarak skorunuzu katlayın!</p>
            </div>
            <button class="cyber-btn btn-sm" style="margin-top:1rem;">Oyna →</button>
        </div>

        <!-- GAME 4: NEON PONG 2077 -->
        <div class="game-card" onclick="launchMiniGame('pong')">
            <div>
                <span style="color:var(--accent-emerald); font-family:'JetBrains Mono',monospace; font-size:0.75rem;">4. OYUN</span>
                <h3>Neon Pong vs AI</h3>
                <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.4rem;">Gelişmiş AI rakibe karşı siber tenis maçı!</p>
            </div>
            <button class="cyber-btn btn-sm" style="margin-top:1rem;">Oyna →</button>
        </div>

        <!-- GAME 5: CYBER BRICK BREAKER -->
        <div class="game-card" onclick="launchMiniGame('breaker')">
            <div>
                <span style="color:var(--primary-cyan); font-family:'JetBrains Mono',monospace; font-size:0.75rem;">5. OYUN</span>
                <h3>Cyber Brick Breaker</h3>
                <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.4rem;">Tüm siber tuğlaları kırın ve seviyeleri geçin!</p>
            </div>
            <button class="cyber-btn btn-sm" style="margin-top:1rem;">Oyna →</button>
        </div>
    </div>
</main>

<!-- GAME ARENA SCREEN (HIDDEN UNTIL LAUNCHED) -->
<div class="menu-container" id="arenaContainer" style="display:none; margin-top:90px;">
    <div class="arena-wrapper" id="arenaWrapper">
        <canvas id="agarioCanvas"></canvas>

        <!-- GLASSMORPHISM MINIMAP (BOTTOM LEFT) -->
        <div class="glass-minimap">
            <canvas id="miniCanvas" width="180" height="180"></canvas>
            <div style="position:absolute; top:4px; left:6px; font-size:0.65rem; color:var(--primary-cyan); font-family:'JetBrains Mono',monospace; font-weight:700;">RADAR (6000px)</div>
        </div>

        <!-- LIVE TOP 5 LEADERBOARD (TOP RIGHT) -->
        <div class="live-leaderboard">
            <div style="font-weight:800; color:var(--accent-pink); border-bottom:1px solid var(--border-color); padding-bottom:0.3rem; margin-bottom:0.4rem;">TOP 5 CANLI SKOR</div>
            <div id="leaderList">
                <div class="leader-item"><span>1. Loading...</span><span>0</span></div>
            </div>
        </div>

        <!-- CONTROLS GUIDE BAR (BOTTOM RIGHT) -->
        <div class="controls-guide-bar">
            <strong>KONTROLLER:</strong> MOUSE (Yön) | SPACE (Bölünme) | W (Yem At) | V (Alev Topu) | B (Sprint 2x) | C (Gizli Anında Birleş)
        </div>
    </div>
</div>

<script src="js/dgrwebio.js"></script>
<script>
// Theme Switcher
const themeBtn = document.getElementById('themeToggleBtn');
themeBtn.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('turkbil_theme', next);
});
if (localStorage.getItem('turkbil_theme')) {
    document.documentElement.setAttribute('data-theme', localStorage.getItem('turkbil_theme'));
}
</script>
</body>
</html>
