<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$pageTitle = 'Sürüm Geçmişi';
$active = 'history';

$index = cms_read_json(ADMIN_BACKUP_DIR . '/index.json');
$rows = [];
foreach ($index as $name => $meta) {
    $file = ADMIN_BACKUP_DIR . '/' . $name;
    if (!is_array($meta) || !is_file($file)) continue;
    $rows[] = $meta + ['name' => $name, 'count' => count(cms_read_json($file))];
}
usort($rows, fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));

$media = array_slice(cms_read_json(ADMIN_MEDIA_LOG), 0, 30);

require __DIR__ . '/partials/header.php';
?>
<section class="card" data-history>
    <div class="card-head">
        <h2>Metin kayıtları</h2>
        <span class="muted small">Her kayıttan önce o anki hâl otomatik yedeklenir · dil başına son <?= ADMIN_KEEP_BACKUPS ?> kayıt saklanır</span>
    </div>
    <?php if (!$rows): ?>
        <p class="pad muted">Henüz kayıt yok. Panelden ilk değişiklik kaydedildiğinde burada görünecek.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tarih</th><th>Dil</th><th>Kullanıcı</th><th>Açıklama</th><th>O andaki değişiklik sayısı</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-name="<?= e($r['name']) ?>">
                    <td class="nowrap"><?= date('d.m.Y H:i:s', (int) $r['time']) ?></td>
                    <td><span class="lang"><?= strtoupper((string) $r['lang']) ?></span></td>
                    <td><?= e((string) ($r['user'] ?? '')) ?></td>
                    <td class="small muted">Bu kayıttan <b>önceki</b> hâl · <?= e((string) ($r['note'] ?? '')) ?></td>
                    <td><?= (int) $r['count'] ?> metin</td>
                    <td class="right"><button class="btn btn-ghost btn-sm" type="button" data-restore-backup><?= icon('undo', 15) ?>Bu hâle dön</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>Görsel ve doküman işlemleri</h2></div>
    <?php if (!$media): ?>
        <p class="pad muted">Henüz görsel veya doküman değiştirilmedi.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tarih</th><th>İşlem</th><th>Dosya</th><th>Kullanıcı</th></tr></thead>
            <tbody>
            <?php foreach ($media as $m): ?>
                <tr>
                    <td class="nowrap"><?= date('d.m.Y H:i', (int) ($m['time'] ?? 0)) ?></td>
                    <td><?= ($m['action'] ?? '') === 'restore' ? '<span class="pill">Orijinale dönüldü</span>' : '<span class="pill pill-warn">Değiştirildi</span>' ?></td>
                    <td class="small"><?= e((string) ($m['path'] ?? '')) ?><?= !empty($m['name']) ? ' <span class="muted">← ' . e((string) $m['name']) . '</span>' : '' ?></td>
                    <td><?= e((string) ($m['user'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="pad muted small-note">Değiştirilen görsel ve dokümanlar, kendi sayfalarındaki ↺ butonuyla orijinaline döndürülebilir.</p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
