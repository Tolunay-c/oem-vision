<?php
/**
 * Local geliştirme yönlendiricisi (PHP'nin dahili sunucusu için)
 *
 * Çalıştırma (oem-vision klasöründe):
 *   php -S localhost:8000 -t public_html public_html/_cms/router.php
 */

declare(strict_types=1);

require __DIR__ . '/cms.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// _cms klasörü dışarıdan çağrılamasın
if (str_starts_with($path, '/_cms/')) {
    http_response_code(404);
    exit;
}

if (cms_serve($path)) {
    return true;
}

// Var olan dosya ve klasörleri (css, js, görsel, php, /admin/) sunucu kendisi versin
if (is_file(CMS_PUBLIC . $path) || is_dir(CMS_PUBLIC . $path)) {
    return false;
}

cms_not_found($path);
