<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$type = ($_GET['type'] ?? 'images') === 'docs' ? 'docs' : 'images';
$isImg = $type === 'images';
$pageTitle = $isImg ? 'Görseller' : 'Dokümanlar';
$active = $isImg ? 'media' : 'docs';
$showAll = !empty($_GET['all']);

$files = admin_media_files($type);
$usage = admin_media_usage(array_keys($files));
$limit = min(admin_upload_limit(), ADMIN_MEDIA_TYPES[$type]['max']);

$items = [];
$hidden = 0;
foreach ($files as $path => $abs) {
    $used = $usage[$path] ?? [];
    if (!$used && !$showAll) { $hidden++; continue; }
    $info = $isImg ? @getimagesize($abs) : false;
    $items[] = [
        'path'     => $path,
        'name'     => basename($path),
        'title'    => admin_media_title($path),
        'folder'   => trim(dirname($path), '/') ?: 'Site ikonu',
        'size'     => (int) filesize($abs),
        'mtime'    => (int) filemtime($abs),
        'w'        => $info[0] ?? null,
        'h'        => $info[1] ?? null,
        'used'     => $used,
        'replaced' => is_file(admin_media_original($path)),
        'lang'     => preg_match('/-(TR|EN)\.pdf$/i', $path, $m) ? strtoupper($m[1]) : null,
    ];
}

require __DIR__ . '/partials/header.php';
?>
<div class="card media-head">
    <div>
        <h2><?= $isImg ? 'Sitedeki görseller' : 'İndirilebilir dokümanlar (PDF)' ?></h2>
        <p class="muted">
            <?= $isImg
                ? 'Bir görseli değiştirdiğinizde, kullanıldığı her yerde yenisi görünür. En iyi sonuç için orijinaliyle aynı en-boy oranında görsel yükleyin.'
                : 'Bir dokümanı değiştirdiğinizde sitedeki tüm indirme linkleri yeni dosyayı verir.' ?>
            Yükleme sınırı: <b><?= admin_bytes($limit) ?></b>.
        </p>
    </div>
    <?php if ($hidden || $showAll): ?>
        <a class="btn btn-ghost" href="?type=<?= $type ?><?= $showAll ? '' : '&amp;all=1' ?>">
            <?= $showAll ? 'Kullanılmayanları gizle' : "Kullanılmayan {$hidden} dosyayı göster" ?>
        </a>
    <?php endif; ?>
</div>

<?php if ($isImg): ?>
<div class="media-grid" data-media data-type="images" data-limit="<?= $limit ?>">
    <?php foreach ($items as $it): ?>
        <article class="card media-card" data-path="<?= e($it['path']) ?>">
            <a class="media-thumb" href="<?= e($it['path']) ?>?v=<?= $it['mtime'] ?>" target="_blank" rel="noopener">
                <img src="<?= e($it['path']) ?>?v=<?= $it['mtime'] ?>" alt="" loading="lazy">
                <?php if ($it['replaced']): ?><span class="pill pill-warn">Değiştirildi</span><?php endif; ?>
            </a>
            <div class="media-body">
                <b title="<?= e($it['path']) ?>"><?= e($it['title']) ?></b>
                <small class="muted"><?= e($it['name']) ?> · <?= $it['w'] ? "{$it['w']}×{$it['h']} · " : '' ?><?= admin_bytes($it['size']) ?></small>
                <small class="used"><?= $it['used'] ? e(implode(', ', $it['used'])) : '<span class="muted">Sitede kullanılmıyor</span>' ?></small>
            </div>
            <div class="media-actions">
                <label class="btn btn-primary btn-sm">
                    <?= icon('image', 15) ?><span>Değiştir</span>
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-upload hidden>
                </label>
                <?php if ($it['replaced']): ?>
                    <button class="btn btn-ghost btn-sm" type="button" data-restore title="Orijinal görsele dön"><?= icon('undo', 15) ?></button>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
<section class="card" data-media data-type="docs" data-limit="<?= $limit ?>">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Doküman</th><th>Dil</th><th>Boyut</th><th>Son değişiklik</th><th>Kullanıldığı sayfalar</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr data-path="<?= e($it['path']) ?>">
                    <td>
                        <a class="doc-name" href="<?= e($it['path']) ?>?v=<?= $it['mtime'] ?>" target="_blank" rel="noopener"><?= icon('file-down', 16) ?><span><?= e($it['name']) ?></span></a>
                        <?php if ($it['replaced']): ?><span class="pill pill-warn">Değiştirildi</span><?php endif; ?>
                    </td>
                    <td><?= $it['lang'] ? '<span class="lang">' . $it['lang'] . '</span>' : '—' ?></td>
                    <td class="nowrap muted"><?= admin_bytes($it['size']) ?></td>
                    <td class="nowrap muted"><?= date('d.m.Y H:i', $it['mtime']) ?></td>
                    <td class="small"><?= $it['used'] ? e(implode(', ', $it['used'])) : '<span class="muted">Linki yok</span>' ?></td>
                    <td class="nowrap right">
                        <label class="btn btn-primary btn-sm">
                            <span>Değiştir</span>
                            <input type="file" accept="application/pdf,.pdf" data-upload hidden>
                        </label>
                        <?php if ($it['replaced']): ?>
                            <button class="btn btn-ghost btn-sm" type="button" data-restore title="Orijinal dosyaya dön"><?= icon('undo', 15) ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
