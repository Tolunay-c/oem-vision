<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$pageTitle = 'Gelen Formlar';
$active = 'messages';
$filter = (string) ($_GET['f'] ?? 'all');

$all = array_values(array_filter(cms_read_json(CMS_DATA . '/messages.json'), 'is_array'));
usort($all, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
$counts = ['all' => count($all), 'unread' => 0, 'quote' => 0, 'contact' => 0];
foreach ($all as $m) {
    if (($m['status'] ?? '') === 'unread') $counts['unread']++;
    $counts[($m['type'] ?? '') === 'quote' ? 'quote' : 'contact']++;
}
$list = array_filter($all, fn($m) => match ($filter) {
    'unread'  => ($m['status'] ?? '') === 'unread',
    'quote'   => ($m['type'] ?? '') === 'quote',
    'contact' => ($m['type'] ?? '') !== 'quote',
    default   => true,
});

$fields = ['name' => 'Ad soyad', 'company' => 'Şirket', 'email' => 'E-posta', 'phone' => 'Telefon', 'country' => 'Ülke',
           'product' => 'İlgilendiği ürün', 'subject' => 'Konu', 'lang' => 'Dil', 'ip' => 'IP adresi'];

require __DIR__ . '/partials/header.php';
?>
<div class="inbox-bar">
    <nav class="segmented" aria-label="Filtre">
        <?php foreach (['all' => 'Tümü', 'unread' => 'Okunmamış', 'quote' => 'Teklif talepleri', 'contact' => 'İletişim'] as $k => $l): ?>
            <a href="?f=<?= $k ?>" class="seg<?= $filter === $k ? ' is-active' : '' ?>"><?= $l ?><em><?= $counts[$k] ?></em></a>
        <?php endforeach; ?>
    </nav>
    <span class="muted small">Ayrıntıları görmek için mesajın üzerine tıklayın</span>
</div>

<section class="card" data-messages>
    <?php if (!$list): ?>
        <p class="pad muted">Bu filtrede mesaj yok.</p>
    <?php endif; ?>
    <?php foreach ($list as $m): $unread = ($m['status'] ?? '') === 'unread'; ?>
        <details class="msg<?= $unread ? ' is-unread' : '' ?>" data-id="<?= e((string) ($m['id'] ?? '')) ?>">
            <summary>
                <span class="msg-dot"></span>
                <span class="msg-from"><b><?= e((string) ($m['name'] ?? '—')) ?></b><small class="muted"><?= e((string) ($m['company'] ?? '')) ?></small></span>
                <span class="msg-subject"><?= e((string) ($m['subject'] ?? '')) ?></span>
                <span class="pill<?= ($m['type'] ?? '') === 'quote' ? ' pill-green' : '' ?>"><?= ($m['type'] ?? '') === 'quote' ? 'Teklif' : 'İletişim' ?></span>
                <span class="msg-date muted"><?= e((string) ($m['date'] ?? '')) ?></span>
                <span class="msg-open"><span class="when-closed">Aç</span><span class="when-open">Kapat</span><?= icon('chevron', 16) ?></span>
            </summary>
            <div class="msg-body">
                <dl>
                    <?php foreach ($fields as $k => $label): if (empty($m[$k])) continue; ?>
                        <dt><?= $label ?></dt>
                        <dd><?php if ($k === 'email'): ?><a href="mailto:<?= e((string) $m[$k]) ?>"><?= e((string) $m[$k]) ?></a>
                            <?php elseif ($k === 'phone'): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $m[$k])) ?>"><?= e((string) $m[$k]) ?></a>
                            <?php else: ?><?= e((string) $m[$k]) ?><?php endif; ?></dd>
                    <?php endforeach; ?>
                </dl>
                <?php if (!empty($m['message'])): ?><div class="msg-text"><?= nl2br(e((string) $m['message'])) ?></div><?php endif; ?>
                <div class="msg-actions">
                    <?php if (!empty($m['email'])): ?>
                        <a class="btn btn-primary btn-sm" href="mailto:<?= e((string) $m['email']) ?>?subject=<?= rawurlencode('Re: ' . (string) ($m['subject'] ?? 'OEM Vision')) ?>">Yanıtla</a>
                    <?php endif; ?>
                    <button class="btn btn-ghost btn-sm" type="button" data-toggle-read><?= $unread ? 'Okundu işaretle' : 'Okunmadı işaretle' ?></button>
                    <button class="btn btn-ghost btn-sm danger" type="button" data-delete>Sil</button>
                </div>
            </div>
        </details>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
