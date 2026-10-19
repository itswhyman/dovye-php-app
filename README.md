# Müşteri & Analytics Paneli (Saf PHP 8 + MySQL)

Framework yok, Tailwind yok. Yalnızca PHP 8.1+, PDO (pdo_mysql), mbstring ve saf CSS.

## Kurulum
1. `mysql -u root -p < schema.sql` ile veritabanı ve tabloyu oluşturun. Dosyanın sonundaki yorum satırlarıyla uygulama kullanıcısını tanımlayın (yalnızca SELECT/INSERT/UPDATE).
2. `config.php` içindeki DB bilgilerini girin ya da `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ortam değişkenlerini tanımlayın.
3. Yönetici şifresini değiştirin (varsayılan: `admin` / `ChangeMe!2026`):
   `php -r "echo password_hash('yeni-sifre', PASSWORD_DEFAULT), PHP_EOL;"`
   Çıkan hash'i `ADMIN_PASSWORD_HASH` olarak ayarlayın.
4. Yerelde çalıştırmak için: `php -S localhost:8000` ve `http://localhost:8000/` (form), `/admin.php` (panel).

## Güvenlik özeti
- SQL Injection: tüm sorgular gerçek prepared statement (`ATTR_EMULATE_PREPARES=false`), durum ve hizmet değerleri whitelist ile doğrulanır.
- XSS: tüm çıktılar `e()` (`htmlspecialchars`) ile kaçışlanır; CSP başlığı satır içi script/stile izin vermez.
- CSRF: her POST formunda oturuma bağlı token.
- Oturum: HttpOnly + SameSite=Strict çerez, girişte `session_regenerate_id`, 30 dk hareketsizlik zaman aşımı, 5 hatalı denemede 5 dk kilit.
- Form: honeypot, minimum doldurma süresi, saatte 5 gönderim sınırı.
- Canlıda HTTPS kullanın (çerez `secure` bayrağı HTTPS'te otomatik açılır).
