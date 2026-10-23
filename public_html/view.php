<?php
/**
 * OEM Vision — Universal Dynamic Page & Product Renderer
 * 
 * Dynamically renders newly added products, solutions, software and custom pages
 * with the exact same visual design, styling and typography as the Next.js static pages.
 */

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';

// Dil tespiti
$lang = 'tr';
if (str_starts_with($path, '/en/') || $path === '/en' || (isset($_GET['lang']) && $_GET['lang'] === 'en')) {
    $lang = 'en';
}

// Parametreleri al
$type = $_GET['type'] ?? '';
$slug = $_GET['slug'] ?? '';

if (empty($type) && empty($slug)) {
    // URL'den otomatik çözümle
    $trimmed = trim($path, '/');
    if ($lang === 'en' && str_starts_with($trimmed, 'en/')) {
        $trimmed = substr($trimmed, 3);
    }
    $parts = explode('/', $trimmed);
    if (count($parts) >= 2) {
        $type = $parts[0];
        $slug = $parts[1];
    } elseif (count($parts) === 1 && !empty($parts[0])) {
        $type = 'page';
        $slug = $parts[0];
    }
}

// Tip normalizasyonu
if (in_array($type, ['urunler', 'products'], true)) $type = 'products';
if (in_array($type, ['cozumler', 'solutions'], true)) $type = 'solutions';
if (in_array($type, ['yazilim', 'software'], true)) $type = 'software';
if (in_array($type, ['sayfa', 'pages', 'p'], true)) $type = 'page';

$outsideData = dirname(__DIR__) . '/data';
$dataDir = is_dir($outsideData) ? $outsideData : __DIR__ . '/data';
$contentFile = $dataDir . '/content_' . $lang . '.json';
$settingsFile = $dataDir . '/settings.json';


$content = file_exists($contentFile) ? json_decode((string)file_get_contents($contentFile), true) : [];
$settings = file_exists($settingsFile) ? json_decode((string)file_get_contents($settingsFile), true) : [];

$general = $settings['general'] ?? [];
$phone = $general['phone'] ?? '+90 (212) 577 25 67';
$email = $general['email'] ?? 'info@oemvision.com.tr';

// İçeriği bul
$item = null;
$itemType = $type;

if ($type === 'products') {
    $list = $content['products']['list'] ?? [];
    foreach ($list as $p) {
        if (($p['slug'] ?? '') === $slug || ($p['id'] ?? '') === $slug) {
            $item = $p;
            break;
        }
    }
} elseif ($type === 'solutions') {
    $list = $content['solutions']['list'] ?? [];
    foreach ($list as $s) {
        if (($s['slug'] ?? '') === $slug || ($s['id'] ?? '') === $slug) {
            $item = $s;
            break;
        }
    }
} elseif ($type === 'software') {
    $list = $content['software']['list'] ?? [];
    foreach ($list as $sw) {
        if (($sw['slug'] ?? '') === $slug || ($sw['id'] ?? '') === $slug) {
            $item = $sw;
            break;
        }
    }
} elseif ($type === 'page') {
    $list = $content['custom_pages'] ?? [];
    foreach ($list as $cp) {
        if (($cp['slug'] ?? '') === $slug || ($cp['id'] ?? '') === $slug) {
            $item = $cp;
            break;
        }
    }
}

// 404 durumu
if (!$item || (isset($item['active']) && !$item['active'])) {
    http_response_code(404);
    $pageTitle = $lang === 'en' ? 'Page Not Found — OEM Vision' : 'Sayfa Bulunamadı — OEM Vision';
    ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($pageTitle) ?></title>
        <link rel="stylesheet" href="/_next/static/chunks/0x_wqmpbzhq42.css">
        <link rel="stylesheet" href="/_next/static/chunks/0evq-u-z9jz4t.css">
    </head>
    <body class="bg-[#080c0d] text-gray-200 min-h-screen flex items-center justify-center p-6 font-sans">
        <div class="max-w-md text-center">
            <h1 class="text-6xl font-bold text-emerald-500 mb-4">404</h1>
            <h2 class="text-2xl font-bold text-white mb-2"><?= $lang === 'en' ? 'Page Not Found' : 'Sayfa Bulunamadı' ?></h2>
            <p class="text-gray-400 mb-8"><?= $lang === 'en' ? 'The requested page or product is currently unavailable or being updated.' : 'Aradığınız sayfa veya ürün şu anda yayında değil veya güncelleniyor.' ?></p>
            <a href="<?= $lang === 'en' ? '/en' : '/' ?>" class="inline-flex items-center px-6 py-3 rounded-xl bg-emerald-500 text-black font-semibold hover:bg-emerald-400 transition-colors">
                <?= $lang === 'en' ? 'Back to Home' : 'Ana Sayfaya Dön' ?>
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Sayfa Başlığı ve Meta
$title = $item['name'] ?? $item['title'] ?? 'OEM Vision';
$fullTitle = htmlspecialchars($title) . ' | OEM Vision';
$shortDesc = $item['short_desc'] ?? $item['subtitle'] ?? $item['meta_desc'] ?? '';
$menuItems = $content['menu'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $fullTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($shortDesc) ?>">
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/_next/static/chunks/0x_wqmpbzhq42.css">
    <link rel="stylesheet" href="/_next/static/chunks/0evq-u-z9jz4t.css">
    <style>
        :root {
            --oem-green: #10b981;
            --oem-dark: #080c0d;
            --oem-card: #0f1618;
            --oem-border: #1a2528;
        }
        body {
            background-color: #080c0d;
            color: #e5e7eb;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden;
        }
        .glow-text {
            text-shadow: 0 0 30px rgba(16, 185, 129, 0.4);
        }
        .glass-card {
            background: rgba(15, 22, 24, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card:hover {
            border-color: rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body class="selection:bg-emerald-500 selection:text-black">

    <!-- Header / Navbar -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-[#080c0d]/80 backdrop-blur-md border-b border-[#1a2528]">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="<?= $lang === 'en' ? '/en' : '/' ?>" class="flex items-center gap-3">
                <span class="text-xl font-bold tracking-wider text-white">OEM <span class="text-emerald-400">VISION</span></span>
            </a>

            <!-- Nav Links -->
            <nav class="hidden md:flex items-center gap-8">
                <?php foreach ($menuItems as $m): ?>
                    <?php if (!empty($m['visible'])): ?>
                        <a href="<?= htmlspecialchars($m['url']) ?>" class="text-sm font-medium text-gray-300 hover:text-emerald-400 transition-colors">
                            <?= htmlspecialchars($m['label']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <!-- Lang Switcher -->
                <div class="text-xs font-semibold px-2 py-1 rounded bg-[#151e21] border border-[#233136] text-gray-300">
                    <a href="/tr/<?= $type ?>/<?= $slug ?>" class="<?= $lang === 'tr' ? 'text-emerald-400' : 'hover:text-white' ?>">TR</a> | 
                    <a href="/en/<?= $type ?>/<?= $slug ?>" class="<?= $lang === 'en' ? 'text-emerald-400' : 'hover:text-white' ?>">EN</a>
                </div>
                <a href="<?= $lang === 'en' ? '/en/contact' : '/contact' ?>" class="hidden sm:inline-flex items-center px-4 py-2 rounded-xl bg-emerald-500 text-black text-sm font-semibold hover:bg-emerald-400 transition-all shadow-[0_0_20px_rgba(16,185,129,0.25)]">
                    <?= $lang === 'en' ? 'Get a Quote' : 'Teklif İsteyin' ?>
                </a>
            </div>
        </div>
    </header>

    <main class="pt-32 pb-24">
        <div class="max-w-7xl mx-auto px-6">

            <?php if ($type === 'products'): ?>
                <!-- PRODUCT PAGE TEMPLATE -->

                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-xs text-gray-400 mb-8">
                    <a href="<?= $lang === 'en' ? '/en' : '/' ?>" class="hover:text-emerald-400"><?= $lang === 'en' ? 'Home' : 'Ana Sayfa' ?></a>
                    <span>/</span>
                    <a href="<?= $lang === 'en' ? '/en/products' : '/products' ?>" class="hover:text-emerald-400"><?= $lang === 'en' ? 'Products' : 'Ürünler' ?></a>
                    <span>/</span>
                    <span class="text-emerald-400"><?= htmlspecialchars($item['name']) ?></span>
                </div>

                <!-- Hero Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-24">
                    <div>
                        <?php if (!empty($item['badge'])): ?>
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold mb-6">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <?= htmlspecialchars($item['badge']) ?>
                            </div>
                        <?php endif; ?>

                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6 leading-tight">
                            <?= htmlspecialchars($item['name']) ?>
                        </h1>

                        <p class="text-lg text-gray-300 leading-relaxed mb-8">
                            <?= nl2br(htmlspecialchars($item['full_desc'] ?? $item['short_desc'] ?? '')) ?>
                        </p>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="<?= $lang === 'en' ? '/en/contact?product=' . urlencode($item['name']) : '/contact?product=' . urlencode($item['name']) ?>" class="px-6 py-3.5 rounded-xl bg-emerald-500 text-black font-bold text-sm hover:bg-emerald-400 transition-all shadow-[0_0_25px_rgba(16,185,129,0.3)]">
                                <?= $lang === 'en' ? 'Request Consultation & Quote' : 'Teklif & Danışmanlık Alın' ?>
                            </a>

                            <?php 
                            $pdfLink = $lang === 'en' ? ($item['pdf_en'] ?? $item['pdf_tr'] ?? '') : ($item['pdf_tr'] ?? '');
                            if (!empty($pdfLink)): ?>
                                <a href="<?= htmlspecialchars($pdfLink) ?>?v=<?= time() ?>" target="_blank" class="px-6 py-3.5 rounded-xl bg-[#131b1d] border border-[#233136] text-white font-medium text-sm hover:border-emerald-500/40 hover:text-emerald-400 transition-all flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <?= $lang === 'en' ? 'Download Datasheet (PDF)' : 'Teknik Föyü İndir (PDF)' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Image Showcase -->
                    <div class="relative rounded-3xl border border-emerald-500/20 bg-[#0c1214] overflow-hidden p-8 flex items-center justify-center shadow-[0_0_80px_rgba(16,185,129,0.15)]">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-transparent to-transparent pointer-events-none"></div>
                        <img src="<?= htmlspecialchars($item['hero_image'] ?? '/images/hero-banner.webp') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="max-h-[420px] w-auto object-contain relative z-10 transition-transform duration-500 hover:scale-105">
                    </div>
                </div>

                <!-- 4 Metrics Stats -->
                <?php if (!empty($item['stats'])): ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-24">
                        <?php foreach ($item['stats'] as $st): ?>
                            <div class="p-6 rounded-2xl bg-[#0e1518] border border-[#1b2629] text-center">
                                <div class="text-3xl sm:text-4xl font-extrabold text-emerald-400 mb-2 glow-text">
                                    <?= htmlspecialchars($st['value'] ?? '') ?>
                                </div>
                                <div class="text-xs uppercase tracking-wider text-gray-400 font-semibold">
                                    <?= htmlspecialchars($st['label'] ?? '') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Features Grid -->
                <?php if (!empty($item['features'])): ?>
                    <div class="mb-24">
                        <div class="text-center max-w-2xl mx-auto mb-16">
                            <h2 class="text-3xl font-bold text-white mb-4"><?= $lang === 'en' ? 'Key Features & Capabilities' : 'Öne Çıkan Özellikler & Yetenekler' ?></h2>
                            <p class="text-gray-400"><?= $lang === 'en' ? 'Engineered for highest accuracy and minimum sawmill downtime.' : 'En yüksek ölçüm doğruluğu ve sıfır fabrika duruşu için tasarlandı.' ?></p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <?php foreach ($item['features'] as $f): ?>
                                <div class="p-8 rounded-2xl bg-[#0f1719] border border-[#1c282c] hover:border-emerald-500/30 transition-all flex items-start gap-5">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 font-bold">
                                        ✓
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white mb-2"><?= htmlspecialchars($f['title'] ?? '') ?></h3>
                                        <p class="text-sm text-gray-400 leading-relaxed"><?= htmlspecialchars($f['desc'] ?? '') ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Technical Specifications Table -->
                <?php if (!empty($item['specs'])): ?>
                    <div class="mb-24">
                        <div class="text-center max-w-2xl mx-auto mb-12">
                            <h2 class="text-3xl font-bold text-white mb-4"><?= $lang === 'en' ? 'Technical Specifications' : 'Teknik Özellikler' ?></h2>
                            <p class="text-gray-400"><?= $lang === 'en' ? 'Detailed mechanical, optical, and communication parameters.' : 'Detaylı mekanik, optik ve haberleşme parametreleri.' ?></p>
                        </div>

                        <div class="max-w-4xl mx-auto overflow-hidden rounded-2xl border border-[#1c282c] bg-[#0c1214]">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="bg-[#121b1e] border-b border-[#1c282c] text-emerald-400 text-xs uppercase tracking-wider">
                                        <th class="py-4 px-6 font-semibold"><?= $lang === 'en' ? 'Group / Category' : 'Grup / Kategori' ?></th>
                                        <th class="py-4 px-6 font-semibold"><?= $lang === 'en' ? 'Parameter' : 'Parametre' ?></th>
                                        <th class="py-4 px-6 font-semibold"><?= $lang === 'en' ? 'Specification Value' : 'Değer / Özellik' ?></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#182326]">
                                    <?php foreach ($item['specs'] as $spec): ?>
                                        <tr class="hover:bg-[#11191c] transition-colors">
                                            <td class="py-4 px-6 text-gray-400 font-medium"><?= htmlspecialchars($spec['group'] ?? 'Genel') ?></td>
                                            <td class="py-4 px-6 text-white font-semibold"><?= htmlspecialchars($spec['label'] ?? '') ?></td>
                                            <td class="py-4 px-6 text-emerald-400 font-mono"><?= htmlspecialchars($spec['value'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($type === 'solutions'): ?>
                <!-- SOLUTION PAGE TEMPLATE -->
                <div class="max-w-4xl mx-auto mb-20 text-center">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold mb-6">
                        <?= htmlspecialchars($item['speed'] ?? '180 m/dk') ?>
                    </div>
                    <h1 class="text-4xl sm:text-5xl font-extrabold text-white mb-6"><?= htmlspecialchars($item['title'] ?? '') ?></h1>
                    <p class="text-xl text-emerald-400 mb-8 font-medium"><?= htmlspecialchars($item['subtitle'] ?? '') ?></p>
                    <p class="text-lg text-gray-300 leading-relaxed mb-10"><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></p>
                    <?php if (!empty($item['image'])): ?>
                        <div class="rounded-3xl border border-[#1d292d] overflow-hidden bg-[#0c1214] mb-12">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-auto object-cover max-h-[500px]">
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($type === 'software'): ?>
                <!-- SOFTWARE PAGE TEMPLATE -->
                <div class="max-w-4xl mx-auto mb-20 text-center">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold mb-6">
                        <?= htmlspecialchars($item['badge'] ?? 'Software Engine') ?>
                    </div>
                    <h1 class="text-4xl sm:text-5xl font-extrabold text-white mb-6"><?= htmlspecialchars($item['title'] ?? '') ?></h1>
                    <p class="text-lg text-gray-300 leading-relaxed mb-10"><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></p>
                    <?php if (!empty($item['image'])): ?>
                        <div class="rounded-3xl border border-[#1d292d] overflow-hidden bg-[#0c1214] mb-12">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-auto object-cover max-h-[500px]">
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($type === 'page'): ?>
                <!-- CUSTOM CONTENT PAGE TEMPLATE -->
                <div class="max-w-3xl mx-auto mb-20">
                    <h1 class="text-4xl font-extrabold text-white mb-6"><?= htmlspecialchars($item['title'] ?? '') ?></h1>
                    <?php if (!empty($item['banner_image'])): ?>
                        <div class="rounded-2xl border border-[#1d292d] overflow-hidden mb-8">
                            <img src="<?= htmlspecialchars($item['banner_image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-auto">
                        </div>
                    <?php endif; ?>
                    <div class="prose prose-invert max-w-none text-gray-300 leading-relaxed">
                        <?= $item['body_html'] ?? '' ?>
                    </div>
                </div>

            <?php endif; ?>

            <!-- CTA SECTION -->
            <div class="rounded-3xl bg-gradient-to-br from-[#0f181a] to-[#080d0e] border border-emerald-500/30 p-10 sm:p-16 text-center relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4"><?= $lang === 'en' ? 'Transform Your Plant with OEM Vision' : 'Kereste Fabrikanızı OEM Vision ile Dönüştürün' ?></h2>
                <p class="text-gray-400 max-w-xl mx-auto mb-8"><?= $lang === 'en' ? 'Contact our technical experts for custom scanning solutions and ROI analysis.' : 'Özel tarama çözümleri ve yatırım geri dönüş analizi için mühendislik ekibimizle iletişime geçin.' ?></p>
                <a href="<?= $lang === 'en' ? '/en/contact' : '/contact' ?>" class="inline-flex items-center px-8 py-4 rounded-xl bg-emerald-500 text-black font-bold text-base hover:bg-emerald-400 transition-all shadow-[0_0_30px_rgba(16,185,129,0.35)]">
                    <?= $lang === 'en' ? 'Contact Our Team' : 'İletişime Geçin' ?>
                </a>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-[#050809] border-t border-[#141d20] py-16 text-gray-400 text-sm">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
            <div class="md:col-span-2">
                <span class="text-xl font-bold text-white tracking-wider">OEM <span class="text-emerald-400">VISION</span></span>
                <p class="mt-4 text-gray-400 max-w-sm leading-relaxed"><?= htmlspecialchars($content['footer']['about'] ?? '') ?></p>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4"><?= $lang === 'en' ? 'Contact' : 'İletişim' ?></h4>
                <p class="mb-2"><?= htmlspecialchars($phone) ?></p>
                <p class="mb-2"><?= htmlspecialchars($email) ?></p>
            </div>
            <div>
                <h4 class="text-white font-bold mb-4"><?= $lang === 'en' ? 'Legal' : 'Kurumsal' ?></h4>
                <p>© 2026 OEM Vision.</p>
                <p class="mt-1"><?= $lang === 'en' ? 'All rights reserved.' : 'Tüm hakları saklıdır.' ?></p>
            </div>
        </div>
    </footer>

    <script src="/js/site-sync.js" defer></script>
</body>
</html>
