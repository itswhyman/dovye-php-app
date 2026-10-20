<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$pdo = db();
$msg = '';

if (!empty($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin123') {
        $_SESSION['admin_logged'] = true;
        redirect('admin.php');
    }
}

if (!($_SESSION['admin_logged'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head><title>Admin Giriş — KRAL AGARİO</title><link rel="stylesheet" href="assets/style.css"></head>
    <body style="display:grid; place-items:center; min-height:100vh;">
    <div class="nick-card">
        <h2>KRAL AGARİO Admin OS v9.0</h2>
        <form method="post" style="margin-top:1rem;">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" class="nick-input" placeholder="admin" required>
            <input type="password" name="password" class="nick-input" placeholder="admin123" required>
            <button type="submit" class="cyber-btn" style="width:100%;">Giriş Yap →</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// SAVE GAME SETTINGS
if (!empty($_POST['action']) && $_POST['action'] === 'save_settings' && $pdo) {
    if (csrf_valid()) {
        $speed     = $_POST['game_speed'] ?? '1.6';
        $max_split = $_POST['max_split_cells'] ?? '16';
        $map_size  = $_POST['map_size'] ?? '8000';
        $remerge   = $_POST['remerge_time_sec'] ?? '10';
        $rgb_spd   = $_POST['rgb_speed'] ?? '2';

        $stmt = $pdo->prepare("INSERT INTO game_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
        $stmt->execute([':k' => 'game_speed', ':v' => $speed, ':v2' => $speed]);
        $stmt->execute([':k' => 'max_split_cells', ':v' => $max_split, ':v2' => $max_split]);
        $stmt->execute([':k' => 'map_size', ':v' => $map_size, ':v2' => $map_size]);
        $stmt->execute([':k' => 'remerge_time_sec', ':v' => $remerge, ':v2' => $remerge]);
        $stmt->execute([':k' => 'rgb_speed', ':v' => $rgb_spd, ':v2' => $rgb_spd]);

        $msg = 'Ayar parametreleri başarıyla güncellendi!';
    }
}

$settings = get_game_settings();
$scores = $pdo ? $pdo->query("SELECT * FROM game_leaderboards ORDER BY high_score DESC LIMIT 10")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>KRAL AGARİO — Admin Kontrol Paneli</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="padding:2rem; overflow-y:auto;">
    <h2>KRAL AGARİO Admin Kontrol Paneli</h2>
    <p style="color:var(--text-muted);">Hız, Bölünme Sınırı, Remerge Süresi ve RGB Özelleştirme Ayarları</p>

    <?php if ($msg): ?>
        <div style="padding:1rem; background:var(--bg-card); border:1px solid var(--primary-cyan); margin-bottom:1.5rem;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">
        <!-- GAME PARAMETERS FORM -->
        <div style="background:var(--bg-card); padding:1.5rem; border:1px solid var(--primary-cyan);">
            <h3>🎮 Oyun Motoru Hassas Ayarları</h3>
            <form method="post" style="margin-top:1rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">

                <div style="margin-bottom:1rem;">
                    <label style="display:block; color:var(--text-muted); font-size:0.85rem;">Oyun İlerleme & İvme Hızı Multiplier (Varsayılan: 1.6):</label>
                    <input type="text" name="game_speed" class="nick-input" style="text-align:left;" value="<?= e($settings['game_speed']) ?>">
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; color:var(--text-muted); font-size:0.85rem;">Maksimum Bölünme Parçası Sınırı (Max Split Cells):</label>
                    <input type="number" name="max_split_cells" class="nick-input" style="text-align:left;" value="<?= e($settings['max_split_cells']) ?>">
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; color:var(--text-muted); font-size:0.85rem;">Geri Birleşme Süresi (Saniye):</label>
                    <input type="number" name="remerge_time_sec" class="nick-input" style="text-align:left;" value="<?= e($settings['remerge_time_sec']) ?>">
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; color:var(--text-muted); font-size:0.85rem;">RGB Neon Renk Dönüşüm Hızı (1-5):</label>
                    <input type="text" name="rgb_speed" class="nick-input" style="text-align:left;" value="<?= e($settings['rgb_speed']) ?>">
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; color:var(--text-muted); font-size:0.85rem;">Harita Genişliği (Map Size px):</label>
                    <input type="number" name="map_size" class="nick-input" style="text-align:left;" value="<?= e($settings['map_size']) ?>">
                </div>

                <button type="submit" class="cyber-btn" style="width:100%;">Değişiklikleri Kaydet →</button>
            </form>
        </div>

        <!-- LEADERBOARD TABLE -->
        <div style="background:var(--bg-card); padding:1.5rem; border:1px solid var(--accent-pink);">
            <h3>🏆 Top 10 Yüksek Skorlar</h3>
            <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--border-color);">
                        <th>ID</th><th>Oyuncu Nick</th><th>Yüksek Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $s): ?>
                        <tr style="border-bottom:1px solid var(--border-color); height:40px;">
                            <td>#<?= $s['id'] ?></td>
                            <td><strong><?= e($s['player_nick']) ?></strong></td>
                            <td><span style="color:var(--accent-emerald); font-weight:700;"><?= number_format($s['high_score']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
