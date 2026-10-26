<?php
/**
 * OEM Vision Yönetim Paneli — ortak başlangıç dosyası
 * Her panel sayfası ve API bunu ilk satırda çağırır.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/_cms/cms.php';

const ADMIN_AUTH_FILE     = CMS_DATA . '/admin_auth.json';
const ADMIN_ATTEMPTS_FILE = CMS_DATA . '/login_attempts.json';
const ADMIN_BACKUP_DIR    = CMS_DATA . '/backups';
const ADMIN_SESSION_TTL   = 7200;   // 2 saat hareketsizlikte oturum düşer
const ADMIN_MAX_ATTEMPTS  = 5;      // bu kadar hatalı denemeden sonra...
const ADMIN_LOCK_SECONDS  = 900;    // ...15 dakika kilit
const ADMIN_KEEP_BACKUPS  = 50;     // dil başına saklanan yedek sayısı

/* ---------------- Oturum ---------------- */

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('oemv_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function admin_is_logged_in(): bool
{
    if (empty($_SESSION['admin_user'])) return false;
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > ADMIN_SESSION_TTL) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_seen'] = time();
    return true;
}

/** Sayfalar için: giriş yoksa login'e gönder. */
function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------- CSRF ---------------- */

function admin_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function admin_csrf_ok(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------------- Giriş denemesi sınırı ---------------- */

function admin_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function admin_attempts_read(): array
{
    $all = cms_read_json(ADMIN_ATTEMPTS_FILE);
    $now = time();
    // Süresi geçmiş kayıtları temizle
    return array_filter($all, fn($r) => is_array($r) && ($now - (int) ($r['last'] ?? 0)) < ADMIN_LOCK_SECONDS);
}

/** Kilitliyse kalan saniyeyi, değilse 0 döndürür. */
function admin_lock_remaining(): int
{
    $r = admin_attempts_read()[admin_client_ip()] ?? null;
    if (!$r || (int) $r['count'] < ADMIN_MAX_ATTEMPTS) return 0;
    return max(0, ADMIN_LOCK_SECONDS - (time() - (int) $r['last']));
}

function admin_attempt_failed(): void
{
    $all = admin_attempts_read();
    $ip = admin_client_ip();
    $all[$ip] = ['count' => (int) ($all[$ip]['count'] ?? 0) + 1, 'last' => time()];
    admin_json_write(ADMIN_ATTEMPTS_FILE, $all);
}

function admin_attempt_clear(): void
{
    $all = admin_attempts_read();
    unset($all[admin_client_ip()]);
    admin_json_write(ADMIN_ATTEMPTS_FILE, $all);
}

/* ---------------- Dosya yazma ---------------- */

/** JSON dosyasını kilitli ve atomik olarak yazar. */
function admin_json_write(string $file, array $data): bool
{
    $json = json_encode($data ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

/** Override dosyasının yedeğini alır, eski yedekleri budar. */
function admin_backup_overrides(string $lang, string $note = ''): void
{
    $src = CMS_DATA . "/overrides_{$lang}.json";
    if (!is_file($src)) return;
    if (!is_dir(ADMIN_BACKUP_DIR)) mkdir(ADMIN_BACKUP_DIR, 0755, true);

    $name = sprintf('overrides_%s_%s.json', $lang, date('Ymd-His'));
    copy($src, ADMIN_BACKUP_DIR . '/' . $name);

    $meta = cms_read_json(ADMIN_BACKUP_DIR . '/index.json');
    $meta[$name] = ['lang' => $lang, 'time' => time(), 'user' => $_SESSION['admin_user'] ?? '', 'note' => $note];

    $files = glob(ADMIN_BACKUP_DIR . "/overrides_{$lang}_*.json") ?: [];
    rsort($files);
    foreach (array_slice($files, ADMIN_KEEP_BACKUPS) as $old) {
        unset($meta[basename($old)]);
        @unlink($old);
    }
    admin_json_write(ADMIN_BACKUP_DIR . '/index.json', $meta);
}

/* ---------------- Yardımcılar ---------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Lucide ikonları (https://lucide.dev, ISC lisansı) */
function icon(string $name, int $size = 18): string
{
    static $paths = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'inbox'     => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'image'     => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'file-down' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/>',
        'building'  => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
        'history'   => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'shield'    => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'log-out'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'external'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'undo'      => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'save'      => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
        'alert'     => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'user'      => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'lock'      => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'menu'      => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'pencil'    => '<path d="M21.17 6.81a1 1 0 0 0-3.99-3.99L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.32a2 2 0 0 0 .83-.5z"/><path d="m15 5 4 4"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="ico" xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
         . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $p . '</svg>';
}

/* ---------------- Sayfa haritası ---------------- */

/**
 * Sözlükteki anahtar önekleri => paneldeki sayfa.
 * url: önizleme linki (dil öneki eklenir).
 */
function admin_pages(): array
{
    return [
        'Genel' => [
            'nav'    => ['title' => 'Menü',   'prefixes' => ['nav'],    'url' => '/'],
            'footer' => ['title' => 'Footer', 'prefixes' => ['footer'], 'url' => '/'],
        ],
        'Ana Sayfa' => [
            'home' => ['title' => 'Ana Sayfa', 'prefixes' => ['hero', 'solutions', 'products', 'technology', 'stats', 'cta'], 'url' => '/'],
        ],
        'Ürünler' => [
            'productsPage' => ['title' => 'Ürünler (genel)', 'prefixes' => ['productsPage'], 'url' => '/products/'],
            'tunaPage'     => ['title' => 'TUNA LogScan',    'prefixes' => ['tunaPage'],     'url' => '/products/tuna/'],
            'altayPage'    => ['title' => 'ALTAY LogScan',   'prefixes' => ['altayPage'],    'url' => '/products/altay/'],
            'arasLPage'    => ['title' => 'ARAS-L BoardScan','prefixes' => ['arasLPage'],    'url' => '/products/aras-l/'],
            'arasTPage'    => ['title' => 'ARAS-T BoardScan','prefixes' => ['arasTPage'],    'url' => '/products/aras-t/'],
        ],
        'Çözümler' => [
            'solutionsPage'          => ['title' => 'Çözümler (genel)', 'prefixes' => ['solutionsPage'],          'url' => '/solutions/'],
            'logyardPage'            => ['title' => 'Tomruk Sahası',    'prefixes' => ['logyardPage'],            'url' => '/solutions/logyard/'],
            'primaryBreakdownPage'   => ['title' => 'Birincil Kesim',   'prefixes' => ['primaryBreakdownPage'],   'url' => '/solutions/primary-breakdown/'],
            'secondaryBreakdownPage' => ['title' => 'İkincil Kesim',    'prefixes' => ['secondaryBreakdownPage'], 'url' => '/solutions/secondary-breakdown/'],
        ],
        'Yazılım' => [
            'softwarePage' => ['title' => 'Yazılım (genel)', 'prefixes' => ['softwarePage'], 'url' => '/software/'],
            'woodscanPage' => ['title' => 'WoodScan',        'prefixes' => ['woodscanPage'], 'url' => '/software/woodscan/'],
            'optisoftPage' => ['title' => 'Optisoft',        'prefixes' => ['optisoftPage'], 'url' => '/software/optisoft/'],
            'wooderpPage'  => ['title' => 'WoodERP',         'prefixes' => ['wooderpPage'],  'url' => '/wooderp/'],
        ],
        'Diğer Sayfalar' => [
            'technologyPage'  => ['title' => 'Teknoloji',  'prefixes' => ['technologyPage'],  'url' => '/technology/'],
            'contactPage'     => ['title' => 'İletişim',   'prefixes' => ['contactPage'],     'url' => '/contact/'],
            'privacyPage'     => ['title' => 'Gizlilik Politikası (KVKK)', 'prefixes' => ['privacyPage'], 'url' => '/privacy/'],
        ],
    ];
}

/** Tek boyutlu sayfa listesi: id => tanım */
function admin_pages_flat(): array
{
    $out = [];
    foreach (admin_pages() as $group => $pages) {
        foreach ($pages as $id => $p) $out[$id] = $p + ['group' => $group];
    }
    return $out;
}

/** Sitede hiç görünmeyen anahtarlar (iki dilde de bulunamayanlar). */
function admin_unused_keys(): array
{
    static $set = null;
    return $set ??= array_flip(array_intersect(cms_unused_keys('tr'), cms_unused_keys('en')));
}

/**
 * Bir sayfaya ait sözlük anahtarları.
 * Teknik değerler (true/false) hiç gösterilmez; sitede görünmeyen metinler
 * $all = true değilse gizlenir.
 */
function admin_page_keys(array $page, bool $all = false): array
{
    $unused = $all ? [] : admin_unused_keys();
    $keys = [];
    foreach (cms_base('tr') as $key => $val) {
        $top = strstr($key, '.', true) ?: $key;
        if (!in_array($top, $page['prefixes'], true)) continue;
        if ($val === 'true' || $val === 'false') continue;
        if (isset($unused[$key])) continue;
        $keys[] = $key;
    }
    return $keys;
}

/* ---------------- Görseller ve dokümanlar ---------------- */

const ADMIN_MEDIA_ORIGINALS = CMS_DATA . '/media_originals';
const ADMIN_MEDIA_LOG       = CMS_DATA . '/media_log.json';
const ADMIN_MEDIA_TYPES = [
    'images' => ['exts' => ['png', 'jpg', 'jpeg', 'webp', 'gif'], 'max' => 10 * 1024 * 1024],
    'docs'   => ['exts' => ['pdf'],                               'max' => 30 * 1024 * 1024],
];

/** Değiştirilebilir dosyaların listesi (site köküne göre yollar: /images/..., /docs/...). */
function admin_media_files(string $type): array
{
    $files = [];
    $add = function (string $abs) use (&$files) {
        $files['/' . ltrim(str_replace('\\', '/', substr($abs, strlen(CMS_PUBLIC))), '/')] = $abs;
    };
    if ($type === 'images') {
        foreach (['/icon.png', '/apple-icon.png'] as $f) {
            if (is_file(CMS_PUBLIC . $f)) $add(CMS_PUBLIC . $f);
        }
    }
    $dir = CMS_PUBLIC . '/' . $type;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (in_array(strtolower($f->getExtension()), ADMIN_MEDIA_TYPES[$type]['exts'], true)) $add($f->getPathname());
        }
    }
    ksort($files);
    return $files;
}

/** Hangi dosya sitenin hangi sayfalarında kullanılıyor? [yol => [sayfa adı, ...]] */
function admin_media_usage(array $paths): array
{
    $titles = ['/' => 'Ana Sayfa'];
    foreach (admin_pages_flat() as $p) {
        $u = '/' . trim($p['url'], '/');
        $titles[$u === '/' ? '/' : $u] ??= $p['title'];
    }
    $titles['/privacy'] = 'Gizlilik Politikası';

    $usage = array_fill_keys($paths, []);
    foreach (CMS_LANGS as $lang) {
        $dir = CMS_PUBLIC . '/' . $lang;
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getFilename() !== 'index.html') continue;
            $html = (string) file_get_contents($f->getPathname());
            $url = '/' . trim(substr($f->getPath(), strlen($dir)), '/');
            $name = $titles[$url === '/' ? '/' : $url] ?? null;
            if ($name === null) continue;   // panelde olmayan (boş) sayfalar
            foreach ($paths as $p) {
                if (strpos($html, $p) !== false) $usage[$p][$name] = true;
            }
        }
    }
    // Sayfaların JavaScript kodunda geçenler (ürün galerileri, ekran görüntüleri vb.)
    foreach (glob(CMS_PUBLIC . '/_next/static/chunks/*.js') ?: [] as $js) {
        $code = (string) file_get_contents($js);
        foreach ($paths as $p) {
            if (!$usage[$p] && strpos($code, $p) !== false) $usage[$p]['Sayfa kodu içinde'] = true;
        }
    }
    return array_map(fn($u) => count($u) >= 10 ? ['Tüm sayfalarda'] : array_keys($u), $usage);
}

/** Görsel/doküman için anlaşılır ad */
function admin_media_title(string $path): string
{
    static $fixed = [
        '/icon.png'                     => 'Site ikonu (tarayıcı sekmesi)',
        '/apple-icon.png'               => 'Site ikonu (telefon ana ekranı)',
        '/images/logo.png'              => 'Logo',
        '/images/optisoft/interface-tr.png' => 'Optisoft ekran görüntüsü (TR)',
        '/images/optisoft/interface-en.png' => 'Optisoft ekran görüntüsü (EN)',
        '/images/woodscan/screenshot.png'   => 'WoodScan ekran görüntüsü',
    ];
    static $folders = ['tuna' => 'TUNA LogScan', 'altay' => 'ALTAY LogScan', 'aras-l' => 'ARAS-L BoardScan', 'aras-t' => 'ARAS-T BoardScan'];
    if (isset($fixed[$path])) return $fixed[$path];
    $parts = explode('/', trim($path, '/'));
    if (($parts[0] ?? '') === 'images' && isset($folders[$parts[1] ?? ''])) {
        $file = pathinfo($path, PATHINFO_FILENAME);
        return $folders[$parts[1]] . (str_starts_with($file, 'hero') ? ' — ana görsel' : ' — galeri görseli');
    }
    return basename($path);
}

function admin_media_original(string $path): string
{
    return ADMIN_MEDIA_ORIGINALS . $path;
}

function admin_bytes(int $b): string
{
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    return max(1, (int) round($b / 1024)) . ' KB';
}

/** php.ini'deki yükleme sınırı (byte) */
function admin_upload_limit(): int
{
    $toBytes = function (string $v): int {
        $v = trim($v);
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    };
    return min($toBytes((string) ini_get('upload_max_filesize')), $toBytes((string) ini_get('post_max_size')));
}

/* ---------------- Firma bilgileri ---------------- */

/**
 * Firma bilgisi alanları: her biri sözlükteki bir veya birden çok anahtara yazılır.
 * per_lang: TR ve EN ayrı değer mi; link: tel/mailto linki de değişsin mi.
 */
function admin_company_fields(): array
{
    return [
        'email'   => ['label' => 'E-posta', 'type' => 'email', 'per_lang' => false, 'link' => 'mailto',
                      'keys' => ['footer.contactEmail', 'contactPage.office.email', 'privacyPage.s01.t023']],
        'gsm'     => ['label' => 'Cep telefonu (GSM)', 'per_lang' => false, 'link' => 'tel', 'hint' => 'Footer ve iletişim sayfası',
                      'keys' => ['footer.contactPhone', 'contactPage.office.gsm']],
        'phone'   => ['label' => 'Sabit telefon', 'per_lang' => false, 'link' => 'tel', 'keys' => ['contactPage.office.phone']],
        'fax'     => ['label' => 'Faks', 'per_lang' => false, 'keys' => ['contactPage.office.fax']],
        'address' => ['label' => 'Adres', 'per_lang' => true, 'multiline' => true, 'hint' => 'Satır sonları iletişim sayfasında korunur',
                      'keys' => ['contactPage.office.address'], 'flat_keys' => ['privacyPage.s01.t020']],
        'hours'   => ['label' => 'Çalışma saatleri', 'per_lang' => true, 'keys' => ['contactPage.office.hours']],
        'tax'     => ['label' => 'Vergi dairesi / no', 'per_lang' => false, 'keys' => ['contactPage.office.taxInfo']],
        'kep'     => ['label' => 'KEP adresi', 'per_lang' => false, 'hint' => 'Gizlilik politikasında geçer', 'keys' => ['privacyPage.s01.t026']],
    ];
}

/** Alanın şu anki değeri (dil => değer) */
function admin_company_value(array $f): array
{
    $out = [];
    foreach (CMS_LANGS as $l) {
        $k = $f['keys'][0];
        $out[$l] = (string) (cms_overrides($l)[$k] ?? cms_base($l)[$k] ?? '');
    }
    return $out;
}

/** Telefonu tel: linkine çevirir: "+90 (544) 636 12 00" -> "tel:+905446361200" */
function admin_tel_link(string $phone): string
{
    $d = preg_replace('/[^0-9+]/', '', $phone);
    return 'tel:' . $d;
}
