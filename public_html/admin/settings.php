<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_require_login();

$pageTitle = 'Firma Bilgileri';
$active = 'settings';

$fields = admin_company_fields();
$settings = cms_read_json(CMS_DATA . '/settings.json');
$notify = (string) ($settings['general']['notification_email'] ?? '');

require __DIR__ . '/partials/header.php';
?>
<form class="card narrow-wide" data-settings>
    <div class="card-head">
        <h2>İletişim bilgileri</h2>
        <button class="btn btn-primary" type="submit"><?= icon('save', 16) ?>Kaydet</button>
    </div>
    <p class="pad muted small-note">
        Buradaki bilgiler sitenin her yerinde (footer, iletişim sayfası, gizlilik politikası) birlikte güncellenir.
        Telefon ve e-posta değişince tıklanınca arama/e-posta açan linkler de yeni bilgiye yönlenir.
    </p>
    <div class="settings-grid">
        <?php foreach ($fields as $id => $f):
            $vals = admin_company_value($f);
        ?>
            <div class="setting<?= $f['multiline'] ?? false ? ' is-wide' : '' ?>">
                <div class="setting-label"><b><?= e($f['label']) ?></b><?php if (!empty($f['hint'])): ?><small class="muted"><?= e($f['hint']) ?></small><?php endif; ?></div>
                <div class="setting-inputs<?= $f['per_lang'] ? ' two' : '' ?>">
                    <?php foreach ($f['per_lang'] ? CMS_LANGS : ['tr'] as $l): ?>
                        <label>
                            <?php if ($f['per_lang']): ?><span class="lang"><?= strtoupper($l) ?></span><?php endif; ?>
                            <?php if ($f['multiline'] ?? false): ?>
                                <textarea name="<?= e($id) ?>[<?= $l ?>]" rows="3"><?= e($vals[$l]) ?></textarea>
                            <?php else: ?>
                                <input type="<?= $f['type'] ?? 'text' ?>" name="<?= e($id) ?>[<?= $l ?>]" value="<?= e($vals[$l]) ?>">
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card-head card-head-sub"><h2>Form bildirimleri</h2></div>
    <div class="settings-grid">
        <div class="setting">
            <div class="setting-label"><b>Bildirim e-postası</b><small class="muted">İletişim ve teklif formları bu adrese gönderilir</small></div>
            <div class="setting-inputs"><label><input type="email" name="notification_email" value="<?= e($notify) ?>" placeholder="info@oemvision.com.tr"></label></div>
        </div>
    </div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
