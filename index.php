<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$settings = get_game_settings();
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KRAL AGARİO — Live Online Multiplayer Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="cyber-bg-grid"></div>

<!-- ADMIN CONFIG PASSED TO JS -->
<script>
window.GAME_CONFIG = {
    speed: <?= (float)($settings['game_speed'] ?? 1.8) ?>,
    maxSplitCells: <?= (int)($settings['max_split_cells'] ?? 16) ?>,
    mapSize: <?= (int)($settings['map_size'] ?? 8000) ?>,
    remergeSec: <?= (int)($settings['remerge_time_sec'] ?? 10) ?>,
    rgbSpeed: <?= (float)($settings['rgb_speed'] ?? 2) ?>
};
</script>

<!-- START NICK & CUSTOMIZATION OVERLAY -->
<div class="nick-overlay" id="nickOverlay">
    <div class="nick-card">
        <h1 style="font-size:2rem; color:var(--primary-cyan); margin-bottom:0.2rem;">KRAL AGARİO</h1>
        <p style="color:var(--text-muted); font-size:0.85rem;">Arkadaşlarınızla Aynı Haritada Canlı Kapışmak İçin Karakterinizi Seçin:</p>

        <input type="text" id="playerNickInput" class="nick-input" placeholder="Oyuncu Nick / Takma Ad" maxlength="15" autofocus value="DGRWEB_Kral">

        <div style="margin: 0.8rem 0; text-align:left;">
            <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">RGB Desen & Karakter Kaplama Stiliniz:</label>
            <select id="skinPatternSelect" class="skin-select">
                <option value="rgb_neon">🌈 Rainbow RGB Neon Flow</option>
                <option value="rgb_pulse">⚡ Fast RGB Laser Pulse</option>
                <option value="gold_cyber">✨ Metallic Cyber Gold</option>
                <option value="emerald_glow">🟢 Emerald Quantum Glow</option>
                <option value="pink_plasma">💖 Pink Plasma Pulse</option>
            </select>
        </div>

        <div style="margin-bottom:1.2rem;">
            <label style="display:block; font-size:0.8rem; color:var(--text-muted); margin-bottom:0.4rem;">Özel Profil Resmi (Yuvarlağı Kaplar):</label>
            <input type="file" id="avatarFileInput" accept="image/*" style="display:none;" onchange="handleAvatarUpload(event)">
            <button type="button" class="cyber-btn btn-pink btn-sm" onclick="document.getElementById('avatarFileInput').click()">📷 Fotoğraf Yükle</button>
            <div id="avatarPreviewBox" style="margin-top:0.5rem; font-size:0.75rem; color:var(--accent-emerald);">Seçilen Desen Kaplaması Aktif</div>
        </div>

        <button type="button" class="cyber-btn" style="width:100%; font-size:1.1rem;" onclick="startPlatform()">CANLI MULTIPLAYER ARENAYA GİRİŞ YAP →</button>
    </div>
</div>

<!-- FLOATING TOP HEADER NAVBAR -->
<header class="main-header">
    <div class="logo-text">KRAL <span>AGARİO</span> (DGRWEB)</div>
    <div style="display:flex; align-items:center; gap:1rem;">
        <span id="activeUserBadge" style="font-family:'JetBrains Mono',monospace; font-weight:700; color:var(--primary-cyan); font-size:0.85rem;">Oyuncu: -</span>
        <button type="button" class="cyber-btn btn-sm" onclick="location.reload()">⚙️ Karakter Özelleştir</button>
        <button type="button" class="cyber-btn btn-sm" id="themeToggleBtn">🌓 Tema</button>
        <a href="admin.php" target="_blank" class="cyber-btn btn-pink btn-sm">Admin Panel</a>
    </div>
</header>

<!-- MAIN GAME ARENA WRAPPER -->
<div class="arena-wrapper" id="arenaWrapper">
    <canvas id="agarioCanvas"></canvas>

    <!-- GLASSMORPHISM MINIMAP (BOTTOM LEFT) -->
    <div class="glass-minimap">
        <canvas id="miniCanvas" width="210" height="210"></canvas>
        <div id="radarMapText" style="position:absolute; top:4px; left:6px; font-size:0.65rem; color:var(--primary-cyan); font-family:'JetBrains Mono',monospace; font-weight:700;">RADAR (8000px)</div>
    </div>

    <!-- LIVE TOP 10 LEADERBOARD (TOP RIGHT) -->
    <div class="live-leaderboard">
        <div style="font-weight:800; color:var(--accent-pink); border-bottom:1px solid var(--border-color); padding-bottom:0.3rem; margin-bottom:0.4rem;">TOP 10 CANLI LİSTE</div>
        <div id="leaderList">
            <div class="leader-item"><span>1. Loading...</span><span>0</span></div>
        </div>
    </div>

    <!-- CONTROLS GUIDE BAR -->
    <div class="controls-guide-bar">
        <strong>KONTROLLER:</strong> MOUSE (Yön) | W (Yem At) | SPACE (Bölün - Min 30 Skor)
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
