<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$pageTitle = 'Güvenlik';
$active = 'security';
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = cms_read_json(ADMIN_AUTH_FILE);
    $cur  = (string) ($_POST['current'] ?? '');
    $new  = (string) ($_POST['new'] ?? '');
    $new2 = (string) ($_POST['new2'] ?? '');
    $user = trim((string) ($_POST['username'] ?? ''));

    if (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        $err = 'Oturum süresi doldu, sayfayı yenileyin.';
    } elseif (!password_verify($cur, (string) ($auth['password_hash'] ?? ''))) {
        $err = 'Mevcut şifre hatalı.';
    } elseif (!preg_match('/^[A-Za-z0-9._@-]{3,40}$/', $user)) {
        $err = 'Kullanıcı adı 3-40 karakter olmalı; harf, rakam ve . _ @ - kullanılabilir.';
    } elseif ($new !== '' && mb_strlen($new) < 10) {
        $err = 'Yeni şifre en az 10 karakter olmalı.';
    } elseif ($new !== $new2) {
        $err = 'Yeni şifreler eşleşmiyor.';
    } elseif ($new !== '' && in_array(mb_strtolower($new), ['oemvision2026!', 'admin12345', '1234567890'], true)) {
        $err = 'Bu şifre çok kolay tahmin edilir, başka bir şifre seçin.';
    } else {
        $auth['username'] = $user;
        if ($new !== '') $auth['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        $auth['updated_at'] = date('c');
        if (admin_json_write(ADMIN_AUTH_FILE, $auth)) {
            $_SESSION['admin_user'] = $user;
            session_regenerate_id(true);
            $msg = $new !== '' ? 'Bilgiler güncellendi. Bir sonraki girişte yeni şifrenizi kullanın.' : 'Kullanıcı adı güncellendi.';
        } else {
            $err = 'Kaydedilemedi. data klasörünün yazma iznini kontrol edin.';
        }
    }
}

$auth = cms_read_json(ADMIN_AUTH_FILE);
$isDefault = password_verify('oemvision2026!', (string) ($auth['password_hash'] ?? ''));

require __DIR__ . '/partials/header.php';
?>
<?php if ($isDefault): ?>
    <div class="alert alert-error"><?= icon('alert') ?><span><b>Varsayılan şifre kullanılıyor.</b> Bu şifre tahmin edilebilir; lütfen hemen değiştirin.</span></div>
<?php endif; ?>

<section class="card narrow">
    <div class="card-head"><h2>Giriş bilgileri</h2></div>
    <form method="post" class="pad form-stack" autocomplete="off">
        <?php if ($msg): ?><div class="alert alert-ok"><?= icon('check') ?><span><?= e($msg) ?></span></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= e(admin_csrf()) ?>">
        <label class="field"><span>Kullanıcı adı</span><input name="username" required value="<?= e($auth['username'] ?? 'admin') ?>"></label>
        <label class="field"><span>Mevcut şifre</span><input type="password" name="current" required autocomplete="current-password"></label>
        <label class="field"><span>Yeni şifre <small class="muted">(en az 10 karakter, değiştirmeyecekseniz boş bırakın)</small></span><input type="password" name="new" autocomplete="new-password"></label>
        <label class="field"><span>Yeni şifre (tekrar)</span><input type="password" name="new2" autocomplete="new-password"></label>
        <div><button class="btn btn-primary" type="submit"><?= icon('save', 16) ?>Kaydet</button></div>
    </form>
</section>

<section class="card narrow">
    <div class="card-head"><h2>Oturum güvenliği</h2></div>
    <ul class="pad bullet muted">
        <li>5 hatalı giriş denemesinden sonra o IP adresi 15 dakika kilitlenir.</li>
        <li>2 saat işlem yapılmazsa oturum otomatik kapanır.</li>
        <li>Son şifre değişikliği: <b><?= !empty($auth['updated_at']) ? e(date('d.m.Y H:i', strtotime((string) $auth['updated_at']))) : 'bilinmiyor' ?></b></li>
    </ul>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
