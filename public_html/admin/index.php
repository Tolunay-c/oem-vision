<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$pageTitle = 'Genel Bakış';
$active = 'dashboard';

$messages = array_values(array_filter(cms_read_json(CMS_DATA . '/messages.json'), 'is_array'));
$unread = count(array_filter($messages, fn($m) => ($m['status'] ?? '') === 'unread'));
$changedTr = count(cms_overrides('tr'));
$changedEn = count(cms_overrides('en'));
$totalTexts = count(array_filter(cms_base('tr'), fn($v) => $v !== 'true' && $v !== 'false'));
$backups = cms_read_json(ADMIN_BACKUP_DIR . '/index.json');
uasort($backups, fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
$lastBackup = reset($backups) ?: null;

require __DIR__ . '/partials/header.php';
?>
<section class="stats">
    <div class="stat"><span class="stat-ico"><?= icon('inbox') ?></span><div><b><?= $unread ?></b><small>Okunmamış form</small></div></div>
    <div class="stat"><span class="stat-ico"><?= icon('file-text') ?></span><div><b><?= $totalTexts ?></b><small>Düzenlenebilir metin</small></div></div>
    <div class="stat"><span class="stat-ico"><?= icon('pencil') ?></span><div><b><?= $changedTr ?> / <?= $changedEn ?></b><small>Değiştirilen metin (TR / EN)</small></div></div>
    <div class="stat"><span class="stat-ico"><?= icon('history') ?></span><div><b><?= $lastBackup ? date('d.m.Y H:i', (int) $lastBackup['time']) : '—' ?></b><small>Son kayıt</small></div></div>
</section>

<section class="card">
    <div class="card-head"><h2>Hızlı erişim</h2></div>
    <div class="quick">
        <a class="quick-item" href="content.php?page=home"><?= icon('file-text') ?><span>Ana sayfa metinleri</span></a>
        <a class="quick-item" href="content.php?page=tunaPage"><?= icon('file-text') ?><span>TUNA LogScan sayfası</span></a>
        <a class="quick-item" href="content.php?page=contactPage"><?= icon('file-text') ?><span>İletişim sayfası</span></a>
        <a class="quick-item" href="content.php?page=footer"><?= icon('file-text') ?><span>Footer</span></a>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>Son gelen formlar</h2></div>
    <?php if (!$messages): ?>
        <p class="muted pad">Henüz form gelmedi.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Gönderen</th><th>Şirket</th><th>Konu</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($messages, 0, 5) as $m): ?>
                    <tr>
                        <td class="nowrap muted"><?= e((string) ($m['date'] ?? $m['created_at'] ?? '')) ?></td>
                        <td><b><?= e((string) ($m['name'] ?? '')) ?></b></td>
                        <td><?= e((string) ($m['company'] ?? '')) ?></td>
                        <td><?= e((string) ($m['subject'] ?? '')) ?></td>
                        <td><?= ($m['status'] ?? '') === 'unread'
                                ? '<span class="pill pill-green">Yeni</span>'
                                : '<span class="pill">Okundu</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
