<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$pages  = admin_pages();
$flat   = admin_pages_flat();
$pageId = (string) ($_GET['page'] ?? 'home');
$showAll = !empty($_GET['all']);
if (!isset($flat[$pageId])) $pageId = 'home';
$page = $flat[$pageId];

$pageTitle = 'Sayfa İçerikleri';
$active = 'content';

$base = ['tr' => cms_base('tr'), 'en' => cms_base('en')];
$meta = cms_read_json(CMS_DATA . '/static_meta.json');   // sabit sayfaların bölüm/alan adları
$over = ['tr' => cms_overrides('tr'), 'en' => cms_overrides('en')];

// Sayfalardaki değişiklik sayıları (sol listede rozet)
$changedPerPage = [];
$visiblePages = [];
foreach ($flat as $id => $p) {
    $visiblePages[$id] = $showAll || admin_page_keys($p) !== [];
    $n = 0;
    foreach (admin_page_keys($p, true) as $k) {
        if (isset($over['tr'][$k]) || isset($over['en'][$k])) $n++;
    }
    $changedPerPage[$id] = $n;
}

// Alanları bölümlere ayır
const SECTION_TITLES = [
    'hero' => 'Üst alan (Hero)', 'cta' => 'Çağrı alanı (CTA)', 'stats' => 'İstatistikler',
    'solutions' => 'Çözümler bölümü', 'products' => 'Ürünler bölümü', 'technology' => 'Teknoloji bölümü',
    'specs' => 'Teknik özellikler', 'features' => 'Özellikler', 'faq' => 'Sık sorulan sorular',
    'gallery' => 'Galeri', 'sections' => 'Bölümler', 'form' => 'Form', 'info' => 'İletişim bilgileri',
    'links' => 'Linkler', 'benefits' => 'Faydalar', 'process' => 'Süreç', 'modules' => 'Modüller',
    'integration' => 'Entegrasyon', 'applications' => 'Uygulamalar', 'comparison' => 'Karşılaştırma',
    '_' => 'Genel',
];

function humanize(string $s): string
{
    $s = preg_replace('/([a-z])([A-Z0-9])/', '$1 $2', $s);
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

$sections = [];
$pageKeys = admin_page_keys($page, $showAll);
$hiddenCount = count(admin_page_keys($page, true)) - count(admin_page_keys($page));
foreach ($pageKeys as $key) {
    $parts = explode('.', $key);
    if (count($page['prefixes']) === 1) array_shift($parts);    // tek önekli sayfada öneki gösterme
    $section = count($parts) > 1 ? array_shift($parts) : '_';
    $label = $meta[$key] ?? implode(' › ', array_map('humanize', $parts));
    $sections[$section][] = ['key' => $key, 'label' => $label];
}

$preview = rtrim($page['url'], '/') . '/';
?>
<?php require __DIR__ . '/partials/header.php'; ?>

<div class="editor" data-editor>
    <aside class="pagelist card">
        <?php foreach ($pages as $group => $items): ?>
            <div class="pagelist-group"><?= e($group) ?></div>
            <?php foreach ($items as $id => $p): if (!$visiblePages[$id]) continue; ?>
                <a href="?page=<?= e($id) ?><?= $showAll ? '&amp;all=1' : '' ?>" class="pagelist-item<?= $id === $pageId ? ' is-active' : '' ?>">
                    <span><?= e($p['title']) ?></span>
                    <?php if ($changedPerPage[$id]): ?><em title="Değiştirilmiş metin"><?= $changedPerPage[$id] ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </aside>

    <section class="editor-main">
        <div class="editor-bar card">
            <div class="editor-title">
                <h2><?= e($page['title']) ?></h2>
                <span class="muted"><?= count($pageKeys) ?> metin</span>
            </div>
            <label class="search"><?= icon('search', 16) ?><input type="search" placeholder="Bu sayfada ara…" data-search></label>
            <label class="check"><input type="checkbox" data-only-changed><span>Sadece değişenler</span></label>
            <div class="editor-actions">
                <a class="btn btn-ghost" href="/tr<?= e($preview === '//' ? '/' : $preview) ?>" target="_blank" rel="noopener"><?= icon('external', 16) ?>TR</a>
                <a class="btn btn-ghost" href="/en<?= e($preview === '//' ? '/' : $preview) ?>" target="_blank" rel="noopener"><?= icon('external', 16) ?>EN</a>
                <button class="btn btn-primary" type="button" data-save disabled><?= icon('save', 16) ?><span>Kaydet</span><em data-dirty-count></em></button>
            </div>
        </div>

        <?php if ($hiddenCount > 0): ?>
            <div class="notice">
                <?= icon('alert', 16) ?>
                <?php if ($showAll): ?>
                    <span>Sitede görünmeyen <?= $hiddenCount ?> metin de listeleniyor. Bunları değiştirmek sitede bir şey değiştirmez.</span>
                    <a href="?page=<?= e($pageId) ?>">Gizle</a>
                <?php else: ?>
                    <span>Bu sayfada sitede kullanılmayan <?= $hiddenCount ?> eski metin gizlendi.</span>
                    <a href="?page=<?= e($pageId) ?>&amp;all=1">Göster</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($sections as $sec => $fields): ?>
            <details class="card section" open>
                <summary>
                    <span><?= e($meta[$page['prefixes'][0] . '.' . $sec] ?? SECTION_TITLES[$sec] ?? humanize($sec)) ?></span>
                    <small class="muted"><?= count($fields) ?></small>
                    <?= icon('chevron', 16) ?>
                </summary>
                <div class="fields">
                    <?php foreach ($fields as $f): $k = $f['key']; ?>
                        <div class="field-row" data-row>
                            <div class="field-meta">
                                <b><?= e($f['label'] ?: humanize($sec)) ?></b>
                                <code><?= e($k) ?></code>
                            </div>
                            <?php foreach (CMS_LANGS as $l):
                                $orig = $base[$l][$k] ?? '';
                                $cur  = $over[$l][$k] ?? $orig;
                                $long = mb_strlen($orig) > 70 || str_contains($orig, "\n");
                            ?>
                                <div class="field-input<?= isset($over[$l][$k]) ? ' is-changed' : '' ?>" data-field
                                     data-key="<?= e($k) ?>" data-lang="<?= $l ?>">
                                    <div class="field-head">
                                        <span class="lang"><?= strtoupper($l) ?></span>
                                        <span class="changed-tag">Değişti</span>
                                        <button type="button" class="reset" data-reset title="Orijinal metne dön"><?= icon('undo', 13) ?></button>
                                    </div>
                                    <?php if ($long): ?>
                                        <textarea rows="2" data-input data-original="<?= e($orig) ?>" data-initial="<?= e($cur) ?>"><?= e($cur) ?></textarea>
                                    <?php else: ?>
                                        <input type="text" data-input data-original="<?= e($orig) ?>" data-initial="<?= e($cur) ?>" value="<?= e($cur) ?>">
                                    <?php endif; ?>
                                    <div class="orig">Orijinal: <span><?= e($orig) ?></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>

        <p class="empty muted" data-empty hidden>Aramayla eşleşen metin yok.</p>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
