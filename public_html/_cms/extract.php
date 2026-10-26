<?php
/**
 * Orijinal metinleri statik sayfalardan yeniden çıkarır (terminalden).
 * Normalde gerekmez: base dosyası yoksa site ilk açılışta kendisi üretir.
 * Next.js çıktısı değiştiğinde (yeni build yüklendiğinde) çalıştırın.
 *
 *   php public_html/_cms/extract.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/cms.php';
if (!is_dir(CMS_DATA)) { fwrite(STDERR, "data klasörü bulunamadı: " . CMS_DATA . "\n"); exit(1); }
foreach (cms_build_base() as $line) echo $line, "\n";
