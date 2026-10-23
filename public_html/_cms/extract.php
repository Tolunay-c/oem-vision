<?php
/**
 * Orijinal metinleri statik sayfalardan çıkarır.
 * data/base_tr.json ve data/base_en.json dosyalarını üretir.
 *
 * Çalıştırma (oem-vision klasöründe):
 *   php public_html/_cms/extract.php
 *
 * Not: Bu dosyalar Next.js çıktısı değişmediği sürece bir kez üretilir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/cms.php';

if (!is_dir(CMS_DATA)) {
    fwrite(STDERR, "data klasörü bulunamadı: " . CMS_DATA . "\n");
    exit(1);
}

foreach (CMS_LANGS as $lang) {
    $file = CMS_PUBLIC . "/{$lang}/index.html";
    $messages = is_file($file) ? cms_extract_messages((string) file_get_contents($file)) : null;
    if (!$messages) {
        fwrite(STDERR, "[{$lang}] sözlük bulunamadı: {$file}\n");
        exit(1);
    }
    $flat = cms_flatten($messages);
    file_put_contents(
        CMS_DATA . "/base_{$lang}.json",
        json_encode($flat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    // Aynı metnin birden fazla anahtarda geçtiği durumlar (panelde uyarı için)
    $dupes = array_filter(array_count_values($flat), fn($n) => $n > 1);

    echo "[{$lang}] " . count($flat) . " metin çıkarıldı -> data/base_{$lang}.json"
       . " (" . count($dupes) . " metin birden fazla yerde geçiyor)\n";

    if (!is_file(CMS_DATA . "/overrides_{$lang}.json")) {
        file_put_contents(CMS_DATA . "/overrides_{$lang}.json", "{}\n");
    }
}
