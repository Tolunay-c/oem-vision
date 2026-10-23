<?php
/**
 * OEM Vision — Public Settings & Content API
 * 
 * Provides live settings and content overrides to the frontend.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$lang = isset($_GET['lang']) && in_array(strtolower($_GET['lang']), ['en', 'tr'], true)
    ? strtolower($_GET['lang'])
    : 'tr';

$outsideData = dirname(__DIR__, 2) . '/data';
$dataDir = is_dir($outsideData) ? $outsideData : __DIR__ . '/../data';
$settingsFile = $dataDir . '/settings.json';
$contentFile  = $dataDir . '/content_' . $lang . '.json';


$response = [
    'success' => true,
    'lang' => $lang,
    'general' => [],
    'social' => [],
    'docs' => [],
    'content' => [],
    'updated_at' => null,
];

if (file_exists($settingsFile)) {
    $settingsRaw = @file_get_contents($settingsFile);
    if ($settingsRaw) {
        $settings = json_decode($settingsRaw, true);
        if (is_array($settings)) {
            $response['general']    = $settings['general'] ?? [];
            $response['social']     = $settings['social'] ?? [];
            $response['docs']       = $settings['docs'] ?? [];
            $response['updated_at'] = $settings['updated_at'] ?? null;
        }
    }
}

$isPreview = isset($_GET['preview']) && $_GET['preview'] === '1';
$draftFile = $dataDir . '/content_' . $lang . '_draft.json';

if ($isPreview && file_exists($draftFile)) {
    $contentRaw = @file_get_contents($draftFile);
    if ($contentRaw) {
        $content = json_decode($contentRaw, true);
        if (is_array($content)) {
            $response['content'] = $content;
            $response['is_preview'] = true;
        }
    }
} elseif (file_exists($contentFile)) {
    $contentRaw = @file_get_contents($contentFile);
    if ($contentRaw) {
        $content = json_decode($contentRaw, true);
        if (is_array($content)) {
            $response['content'] = $content;
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

