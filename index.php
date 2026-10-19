<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'service' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.';
    } else {
        // Girdileri al (dizi gönderilirse boş say)
        foreach (['name', 'email', 'service', 'message'] as $key) {
            $old[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        }
        // Satır sonu ve kontrol karakterlerini temizle (mesaj hariç satır sonu korunur)
        $old['name']  = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $old['name']) ?? '';
        $old['email'] = preg_replace('/[\x00-\x1F\x7F]+/u', '', $old['email']) ?? '';
        $old['message'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $old['message'])) ?? '';

        // Bot kontrolleri: honeypot dolu ya da form çok hızlı gönderildi → sessizce başarılı göster
        $honeypot = $_POST['website'] ?? '';
        $renderedAt = (int) ($_SESSION['form_rendered_at'] ?? 0);
        $tooFast = $renderedAt > 0 && (time() - $renderedAt) < 3;
        if ($honeypot !== '' || $tooFast) {
            flash('ok', 'Mesajınız alındı. En kısa sürede dönüş yapacağız.');
            redirect('index.php');
        }

        // Basit oturum bazlı hız sınırı: saatte en fazla 5 gönderim
        $now = time();
        $recent = array_filter($_SESSION['submits'] ?? [], static fn (int $t): bool => $t > $now - 3600);
        if (count($recent) >= 5) {
            $errors[] = 'Çok fazla gönderim yaptınız. Lütfen daha sonra tekrar deneyin.';
        }

        // Doğrulama
        $nameLen = mb_strlen($old['name']);
        if ($nameLen < 2 || $nameLen > 100) {
            $errors[] = 'Ad soyad 2 ile 100 karakter arasında olmalı.';
        }
        if ($old['email'] === '' || mb_strlen($old['email']) > 190 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }
        if (!array_key_exists($old['service'], SERVICES)) {
            $errors[] = 'Listeden bir hizmet türü seçin.';
        }
        $msgLen = mb_strlen($old['message']);
        if ($msgLen < 10 || $msgLen > 2000) {
            $errors[] = 'Mesaj 10 ile 2000 karakter arasında olmalı.';
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
                    ':ip'      => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                ]);

                $recent[] = $now;
                $_SESSION['submits'] = array_values($recent);
                flash('ok', 'Teşekkürler! Mesajınız alındı, en kısa sürede size dönüş yapacağız.');
                redirect('index.php'); // PRG: yenilemede tekrar kayıt oluşmasın
            } catch (PDOException $e) {
                error_log('Lead kaydı başarısız: ' . $e->getMessage());
                $errors[] = 'Mesajınız kaydedilemedi. Lütfen biraz sonra tekrar deneyin.';
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
    <title>Bize Ulaşın</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="public">
    <section class="public__intro">
        <h1>Projenizi birlikte hayata geçirelim.</h1>
        <p>Formu doldurun, ihtiyacınızı birkaç cümleyle anlatın. İş günlerinde 24 saat içinde size döneriz.</p>
        <ul class="public__points">
            <li>İlk görüşme ve ihtiyaç analizi ücretsiz</li>
            <li>Net kapsam, net fiyat, net takvim</li>
            <li>Bilgileriniz yalnızca size dönüş için kullanılır</li>
        </ul>
    </section>

    <section class="public__form">
        <div class="card">
            <h2>İletişim formu</h2>

            <?php if ($flash): ?>
                <div class="alert alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert--err" role="alert">
                    Formda düzeltilmesi gerekenler var:
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php" novalidate>
                <?= csrf_field() ?>

                <div class="hp" aria-hidden="true">
                    <label for="website">Bu alanı boş bırakın</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="field">
                    <label for="name">Ad soyad</label>
                    <input type="text" id="name" name="name" maxlength="100" required
                           autocomplete="name" value="<?= e($old['name']) ?>">
                </div>

                <div class="field">
                    <label for="email">E-posta</label>
                    <input type="email" id="email" name="email" maxlength="190" required
                           autocomplete="email" value="<?= e($old['email']) ?>">
                </div>

                <div class="field">
                    <label for="service">Hizmet türü</label>
                    <select id="service" name="service" required>
                        <option value="" disabled <?= $old['service'] === '' ? 'selected' : '' ?>>Seçin…</option>
                        <?php foreach (SERVICES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $old['service'] === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="message">Mesajınız</label>
                    <textarea id="message" name="message" maxlength="2000" required><?= e($old['message']) ?></textarea>
                    <small>En az 10, en fazla 2000 karakter.</small>
                </div>

                <button type="submit" class="btn">Gönder</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
