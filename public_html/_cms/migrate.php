<?php
/**
 * Eski panelin içeriğini (data/content_{lang}.json) override sistemine taşır (terminalden).
 * Normalde gerekmez: overrides dosyası yoksa ilk açılışta otomatik yapılır.
 *
 *   php public_html/_cms/migrate.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/cms.php';
foreach (CMS_LANGS as $lang) {
    echo "[{$lang}] " . cms_migrate_legacy($lang, cms_base($lang)) . " değişiklik taşındı\n";
}
