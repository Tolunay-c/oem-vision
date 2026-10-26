<?php
/**
 * Canlı sunucu giriş noktası.
 * .htaccess, /tr/ ve /en/ altındaki sayfa isteklerini buraya yönlendirir;
 * sayfa, paneldeki değişiklikler uygulanarak sunulur.
 */
declare(strict_types=1);

require __DIR__ . '/cms.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!cms_serve($path)) {
    cms_not_found($path);
}
