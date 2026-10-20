<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

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
    <head><title>Admin Giriş — TurkBil</title><link rel="stylesheet" href="assets/style.css"></head>
    <body style="display:grid; place-items:center; min-height:100vh;">
    <div class="nick-card">
        <h2>TurkBil Admin OS v8.0</h2>
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

$pdo = db();
$scores = $pdo ? $pdo->query("SELECT * FROM game_leaderboards ORDER BY high_score DESC LIMIT 10")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>TurkBil Oyun Fuarı — Admin Panel</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="padding:2rem;">
    <h2>TurkBil Oyun Fuarı Yönetim Paneli</h2>
    <p style="color:var(--text-muted);">Canlı Skorlar ve Top 10 Oyuncu Sıralaması</p>

    <div style="margin-top:2rem; background:var(--bg-card); padding:1.5rem; border:1px solid var(--primary-cyan);">
        <h3>Top 10 Oyuncu Lider Tablosu</h3>
        <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid var(--border-color);">
                    <th>ID</th><th>Oyuncu Nick</th><th>Oyun</th><th>Yüksek Skor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scores as $s): ?>
                    <tr style="border-bottom:1px solid var(--border-color); height:40px;">
                        <td>#<?= $s['id'] ?></td>
                        <td><strong><?= e($s['player_nick']) ?></strong></td>
                        <td><?= e($s['game_key']) ?></td>
                        <td><span style="color:var(--accent-emerald); font-weight:700;"><?= number_format($s['high_score']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
