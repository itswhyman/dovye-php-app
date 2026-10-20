<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'service' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Oturum süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        foreach (['name', 'email', 'service', 'message'] as $key) {
            $old[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        }
        
        $old['name']    = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $old['name']) ?? '';
        $old['email']   = preg_replace('/[\x00-\x1F\x7F]+/u', '', $old['email']) ?? '';
        $old['message'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $old['message'])) ?? '';

        // Honeypot & speed check
        $honeypot = $_POST['website'] ?? '';
        $renderedAt = (int) ($_SESSION['form_rendered_at'] ?? 0);
        $tooFast = $renderedAt > 0 && (time() - $renderedAt) < 2;

        if ($honeypot !== '' || $tooFast) {
            flash('ok', 'Talebiniz başarıyla alındı. En kısa sürede sizinle iletişime geçeceğiz.');
            redirect('index.php');
        }

        // Rate limit: 5 submissions per hour per session
        $now = time();
        $recent = array_filter($_SESSION['submits'] ?? [], static fn (int $t): bool => $t > $now - 3600);
        if (count($recent) >= 5) {
            $errors[] = 'Kısa süre içinde çok fazla mesaj gönderdiniz. Lütfen birkaç dakika bekleyin.';
        }

        // Validation
        $nameLen = mb_strlen($old['name']);
        if ($nameLen < 2 || $nameLen > 100) {
            $errors[] = 'Ad soyad alanı 2 ile 100 karakter arasında olmalıdır.';
        }
        if ($old['email'] === '' || mb_strlen($old['email']) > 190 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Lütfen geçerli bir e-posta adresi girin.';
        }
        if (!array_key_exists($old['service'], SERVICES)) {
            $errors[] = 'Lütfen listeden geçerli bir hizmet türü seçin.';
        }
        $msgLen = mb_strlen($old['message']);
        if ($msgLen < 10 || $msgLen > 2000) {
            $errors[] = 'Proje detayınız en az 10, en fazla 2000 karakter olmalıdır.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare(
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
                flash('ok', 'Harika! Mesajınız ekibimize ulaştı. 24 saat içinde detaylı teklifimizle dönüş yapacağız.');
                redirect('index.php');
            } catch (PDOException $e) {
                error_log('Lead kaydı başarısız: ' . $e->getMessage());
                $errors[] = 'Mesajınız kaydedilirken teknik bir aksaklık oluştu. Lütfen tekrar deneyin.';
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
    <title>DOVYE Studio — Creative Technology Studio</title>
    <meta name="description" content="Geleceğin dijital ürünlerini tasarlıyor ve inşa ediyoruz. Web, Mobil, Cloud ve UI/UX çözümleri.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="glow-bg">
    <div class="glow-orb glow-1"></div>
    <div class="glow-orb glow-2"></div>
</div>

<main class="public">
    <!-- Sol Taraf: Marka & Dinamik Sunum -->
    <section class="public__intro">
        <div class="brand">
            <div class="brand__logo">
                <span class="logo-icon">◇</span>
                <span class="logo-text">DOVYE<span>STUDIO</span></span>
            </div>
            <div class="status-badge">
                <span class="status-dot"></span>
                <span>2026 Q4 Proje Kabulü Açık</span>
            </div>
        </div>

        <div class="hero-content">
            <h1>Fikirlerinizi ölçeklenebilir <span class="gradient-text">dijital ürünlere</span> dönüştürüyoruz.</h1>
            <p>Sıradan sistemleri unutun. High-performance mimariler, kesintisiz kullanıcı deneyimi ve göz alıcı estetikle projenizi canlıya alıyoruz.</p>
        </div>

        <div class="interactive-preview card">
            <div class="preview-header">
                <span class="dot red"></span>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
                <span class="preview-title">Dovye Architecture Stack</span>
            </div>
            <div class="preview-body">
                <div class="code-line"><span class="c-purple">const</span> <span class="c-blue">studio</span> = <span class="c-purple">new</span> <span class="c-yellow">DOVYE</span>({</div>
                <div class="code-line indent">&nbsp;&nbsp;performance: <span class="c-green">'100/100'</span>,</div>
                <div class="code-line indent">&nbsp;&nbsp;security: <span class="c-green">'Enterprise Grade'</span>,</div>
                <div class="code-line indent">&nbsp;&nbsp;techStack: [<span class="c-green">'PHP 8.3'</span>, <span class="c-green">'Next.js'</span>, <span class="c-green">'Docker'</span>, <span class="c-green">'MariaDB'</span>],</div>
                <div class="code-line">&nbsp;&nbsp;support: <span class="c-purple">true</span></div>
                <div class="code-line">});</div>
            </div>
        </div>

        <ul class="public__points">
            <li>
                <div class="point-icon">⚡</div>
                <div>
                    <strong>24 Saatte Hızlı Dönüş</strong>
                    <span>Projenizi inceliyor, kapsam ve bütçe taslağını ilk günden sunuyoruz.</span>
                </div>
            </li>
            <li>
                <div class="point-icon">🛡️</div>
                <div>
                    <strong>Maksimum Güvenlik & Hız</strong>
                    <span>Ultra optimize kod yapısı, Docker konteyner izolasyonu ve SSL koruması.</span>
                </div>
            </li>
        </ul>
    </section>

    <!-- Sağ Taraf: Etkileşimli İletişim Formu -->
    <section class="public__form">
        <div class="card form-card">
            <div class="card-header">
                <h2>Proje Başlatın</h2>
                <p>Aşağıdaki formu doldurun, hayalinizdeki projeyi birlikte inşa edelim.</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status">
                    <span class="alert-icon"><?= $flash['type'] === 'ok' ? '✨' : '⚠️' ?></span>
                    <div><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert--err" role="alert">
                    <span class="alert-icon">⚠️</span>
                    <div>
                        <strong>Lütfen aşağıdaki alanları kontrol edin:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php" novalidate id="contactForm">
                <?= csrf_field() ?>

                <div class="hp" aria-hidden="true">
                    <label for="website">Boş bırakın</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="name">Ad Soyad <span class="req">*</span></label>
                        <input type="text" id="name" name="name" maxlength="100" required
                               placeholder="Ahmet Yılmaz" autocomplete="name" value="<?= e($old['name']) ?>">
                    </div>

                    <div class="field">
                        <label for="email">E-Posta Adresi <span class="req">*</span></label>
                        <input type="email" id="email" name="email" maxlength="190" required
                               placeholder="ahmet@sirketiniz.com" autocomplete="email" value="<?= e($old['email']) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="service">İhtiyacınız Olan Hizmet <span class="req">*</span></label>
                    <div class="select-wrapper">
                        <select id="service" name="service" required>
                            <option value="" disabled <?= $old['service'] === '' ? 'selected' : '' ?>>Bir hizmet kategorisi seçin…</option>
                            <?php foreach (SERVICES as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $old['service'] === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <div class="label-row">
                        <label for="message">Proje Detayları <span class="req">*</span></label>
                        <span class="char-count" id="charCount">0 / 2000</span>
                    </div>
                    <textarea id="message" name="message" maxlength="2000" required
                              placeholder="Projenizin hedeflerinden, beklenen özelliklerden ve zaman planınızdan bahsedin…"><?= e($old['message']) ?></textarea>
                </div>

                <button type="submit" class="btn btn--primary btn--full" id="submitBtn">
                    <span>Teklif Alın</span>
                    <svg class="btn-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </form>
            
            <div class="form-footer">
                <span>🔒 Bilgileriniz Gizlilik Sözleşmesi kapsamında %100 güvendedir.</span>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    const form = document.getElementById('contactForm');
    const btn = document.getElementById('submitBtn');

    if (textarea && charCount) {
        const updateCount = () => {
            const len = textarea.value.length;
            charCount.textContent = `${len} / 2000`;
            if (len > 1800) charCount.style.color = '#f87171';
            else charCount.style.color = 'var(--text-muted)';
        };
        textarea.addEventListener('input', updateCount);
        updateCount();
    }

    if (form && btn) {
        form.addEventListener('submit', () => {
            btn.classList.add('loading');
            btn.innerHTML = `<span>Gönderiliyor...</span><div class="spinner"></div>`;
        });
    }
});
</script>
</body>
</html>
