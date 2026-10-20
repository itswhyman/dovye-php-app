<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'service' => '', 'message' => ''];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Oturum süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif (!$pdo) {
        $errors[] = 'Veritabanı bağlantısı henüz kurulmadı. Lütfen yöneticiniz ile iletişime geçin.';
    } else {
        foreach (['name', 'email', 'service', 'message'] as $key) {
            $old[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        }

        $old['name']    = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $old['name']) ?? '';
        $old['email']   = preg_replace('/[\x00-\x1F\x7F]+/u', '', $old['email']) ?? '';
        $old['message'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $old['message'])) ?? '';

        $honeypot = $_POST['website'] ?? '';
        $renderedAt = (int) ($_SESSION['form_rendered_at'] ?? 0);
        $tooFast = $renderedAt > 0 && (time() - $renderedAt) < 2;

        if ($honeypot !== '' || $tooFast) {
            flash('ok', 'Talebiniz başarıyla alındı. En kısa sürede sizinle iletişime geçeceğiz.');
            redirect('index.php');
        }

        $now = time();
        $recent = array_filter($_SESSION['submits'] ?? [], static fn (int $t): bool => $t > $now - 3600);
        if (count($recent) >= 5) {
            $errors[] = 'Çok fazla mesaj gönderdiniz. Lütfen birkaç dakika bekleyin.';
        }

        $nameLen = mb_strlen($old['name']);
        if ($nameLen < 2 || $nameLen > 100) {
            $errors[] = 'Ad soyad 2 ile 100 karakter arasında olmalıdır.';
        }
        if ($old['email'] === '' || mb_strlen($old['email']) > 190 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi giriniz.';
        }
        if (!array_key_exists($old['service'], SERVICES)) {
            $errors[] = 'Lütfen listeden bir hizmet seçiniz.';
        }
        $msgLen = mb_strlen($old['message']);
        if ($msgLen < 10 || $msgLen > 2000) {
            $errors[] = 'Proje detayı 10 ile 2000 karakter arasında olmalıdır.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO leads (name, email, service, message, ip_address)
                     VALUES (:name, :email, :service, :message, :ip)'
                );
                $stmt->execute([
                    ':name'    => $old['name'],
                    ':email'   => $old['email'],
                    ':service' => $old['service'],
                    ':message' => $old['message'],
                    ':ip'      => substr(client_ip(), 0, 45) ?: null,
                ]);

                $recent[] = $now;
                $_SESSION['submits'] = array_values($recent);
                flash('ok', 'Mesajınız başarıyla iletildi. 24 saat içerisinde teklifimizle dönüş yapacağız.');
                redirect('index.php');
            } catch (PDOException $e) {
                error_log('Lead kaydı hatası: ' . $e->getMessage());
                $errors[] = 'Mesajınız iletilirken teknik bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}

$_SESSION['form_rendered_at'] = time();
$flash = flash();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DOVYE Studio — Next-Gen Technology Studio</title>
    <meta name="description" content="Geleceğin dijital deneyimlerini tasarlıyor ve inşa ediyoruz.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="ambient-glow">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
</div>

<header class="public-nav">
    <div class="nav-container">
        <a href="index.php" class="brand-logo">
            <svg class="svg-icon logo-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
            <span class="logo-title">DOVYE<span>STUDIO</span></span>
        </a>
        <div class="nav-actions">
            <a href="admin.php" class="btn btn--ghost btn--sm">
                <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Yönetim Paneli</span>
            </a>
        </div>
    </div>
</header>

<main class="public-grid">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-badge">
            <span class="pulse-dot"></span>
            <span>DOVYE Studio Multi-Domain Platform v2.5</span>
        </div>

        <h1 class="hero-title">Yüksek Başarı Odaklı <span class="gradient-text">Dijital Mimariler</span></h1>
        <p class="hero-subtitle">Bütünsel mühendislik, modern tasarım sistemleri ve ölçeklenebilir bulut altyapıları ile fikrinizi canlıya alıyoruz.</p>

        <!-- Dynamic Tech Card -->
        <div class="code-card">
            <div class="code-header">
                <div class="window-buttons">
                    <span class="btn-dot close"></span>
                    <span class="btn-dot min"></span>
                    <span class="btn-dot max"></span>
                </div>
                <span class="code-filename">dovye.config.ts</span>
            </div>
            <div class="code-body">
                <div class="cline"><span class="k-kw">export const</span> <span class="k-obj">DOVYE_STACK</span> = {</div>
                <div class="cline indent">&nbsp;&nbsp;architecture: <span class="k-str">'Docker Multi-Tier Engine'</span>,</div>
                <div class="cline indent">&nbsp;&nbsp;proxy: <span class="k-str">'Traefik SSL Proxy'</span>,</div>
                <div class="cline indent">&nbsp;&nbsp;database: <span class="k-str">'MariaDB Enterprise'</span>,</div>
                <div class="cline indent">&nbsp;&nbsp;status: <span class="k-str">'Operational 99.99%'</span>,</div>
                <div class="cline">&nbsp;&nbsp;security: [<span class="k-str">'PDO'</span>, <span class="k-str">'CSRF Shield'</span>, <span class="k-str">'Honeypot'</span>]</div>
                <div class="cline">};</div>
            </div>
        </div>

        <div class="features-list">
            <div class="feature-item">
                <div class="feature-icon">
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div>
                    <h4>Ultra Yüksek Performans</h4>
                    <p>Milisaniyeler mertebesinde yanıt veren hafif ve güçlü kod yapısı.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <h4>Enterprise Güvenlik</h4>
                    <p>Tam izole konteyner yapısı ve şifrelenmiş veri iletimi.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Form Section -->
    <section class="form-section">
        <div class="glass-card">
            <div class="card-title-group">
                <h3>Projenizi Başlatın</h3>
                <p>Formu doldurun, uzman ekibimiz 24 saat içinde sizinle iletişime geçsin.</p>
            </div>

            <?php if (!$pdo): ?>
                <div class="alert alert--err" role="alert">
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <div>Veritabanı bağlantısı bekleniyor. Form gönderimi için MariaDB yapılandırmasını tamamlayınız.</div>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status">
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <div><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert--err" role="alert">
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>
                        <strong>Lütfen aşağıdaki hataları düzeltiniz:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php" id="contactForm">
                <?= csrf_field() ?>

                <div class="hp" aria-hidden="true">
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="name">Ad Soyad *</label>
                        <input type="text" id="name" name="name" maxlength="100" required placeholder="Ahmet Yılmaz" value="<?= e($old['name']) ?>">
                    </div>
                    <div class="field">
                        <label for="email">E-Posta Adresi *</label>
                        <input type="email" id="email" name="email" maxlength="190" required placeholder="ahmet@sirket.com" value="<?= e($old['email']) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="service">Hizmet Türü *</label>
                    <select id="service" name="service" required>
                        <option value="" disabled <?= $old['service'] === '' ? 'selected' : '' ?>>Seçiniz...</option>
                        <?php foreach (SERVICES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $old['service'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <div class="field-header">
                        <label for="message">Proje Detayları *</label>
                        <span class="char-counter" id="charCounter">0 / 2000</span>
                    </div>
                    <textarea id="message" name="message" maxlength="2000" required placeholder="Projenizin hedeflerini, isterlerini ve zaman çizelgenizi özetleyin..."><?= e($old['message']) ?></textarea>
                </div>

                <button type="submit" class="btn btn--primary btn--full" id="submitBtn">
                    <span>Teklif İsteği Gönder</span>
                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const msg = document.getElementById('message');
    const counter = document.getElementById('charCounter');
    if (msg && counter) {
        msg.addEventListener('input', () => {
            counter.textContent = `${msg.value.length} / 2000`;
        });
    }
});
</script>
</body>
</html>
