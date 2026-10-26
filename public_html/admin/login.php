<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$auth = cms_read_json(ADMIN_AUTH_FILE);
$configured = !empty($auth['username']) && !empty($auth['password_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    $lock = admin_lock_remaining();
    if ($lock > 0) {
        $error = 'Çok fazla hatalı deneme. ' . ceil($lock / 60) . ' dakika sonra tekrar deneyin.';
    } elseif (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $user = trim((string) ($_POST['username'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        if (hash_equals((string) $auth['username'], $user) && password_verify($pass, (string) $auth['password_hash'])) {
            admin_attempt_clear();
            session_regenerate_id(true);
            $_SESSION['admin_user'] = $auth['username'];
            $_SESSION['admin_seen'] = time();
            unset($_SESSION['csrf']);
            header('Location: index.php');
            exit;
        }
        admin_attempt_failed();
        usleep(400000); // kaba kuvvet denemelerini yavaşlat
        $error = 'Kullanıcı adı veya şifre hatalı.';
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Giriş · OEM Vision Panel</title>
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-body">
<main class="login-card">
    <div class="login-brand">
        <img src="/icon.png" alt="" width="40" height="40">
        <div>
            <strong>OEM <span>VISION</span></strong>
            <small>Yönetim Paneli</small>
        </div>
    </div>

    <?php if (!$configured): ?>
        <div class="alert alert-warn"><?= icon('alert') ?>
            <span>Panel kullanıcısı tanımlı değil. Sunucuda <code>php public_html/_cms/set-password.php</code> komutuyla oluşturun.</span>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(admin_csrf()) ?>">
            <label class="field">
                <span>Kullanıcı adı</span>
                <div class="input-icon"><?= icon('user', 16) ?><input name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></div>
            </label>
            <label class="field">
                <span>Şifre</span>
                <div class="input-icon"><?= icon('lock', 16) ?><input type="password" name="password" required autocomplete="current-password"></div>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Giriş yap</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
