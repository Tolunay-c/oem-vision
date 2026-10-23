<?php
/**
 * OEM Vision CMS — Bağlantı katmanı çekirdeği
 *
 * Next.js statik çıktısındaki metinleri, panelde yapılan değişikliklerle
 * (data/overrides_{lang}.json) sunucu tarafında değiştirir.
 * Hem görünen HTML hem de React'in kullandığı gömülü veri (RSC) güncellenir,
 * böylece Google güncel metni görür ve sayfada titreme olmaz.
 */

declare(strict_types=1);

define('CMS_PUBLIC', dirname(__DIR__));                 // public_html
define('CMS_DATA', dirname(__DIR__, 2) . '/data');       // public_html'in bir üstündeki data
define('CMS_LANGS', ['tr', 'en']);

/* ---------------------------------------------------------------
 * RSC (React Server Components) verisini HTML içinden okuma
 * ------------------------------------------------------------- */

/** self.__next_f.push([1,"..."]) parçalarının konumlarını bulur. */
function cms_rsc_chunks(string $html): array
{
    $out = [];
    $needle = 'self.__next_f.push([1,';
    $pos = 0;
    $len = strlen($html);
    while (($i = strpos($html, $needle, $pos)) !== false) {
        $start = $i + strlen($needle);   // açılış tırnağı
        $j = $start + 1;
        while ($j < $len) {
            $ch = $html[$j];
            if ($ch === '\\') { $j += 2; continue; }
            if ($ch === '"') break;
            $j++;
        }
        $out[] = [$start, $j + 1];
        $pos = $j + 1;
    }
    return $out;
}

/** HTML içindeki next-intl sözlüğünü (messages) döndürür. */
function cms_extract_messages(string $html): ?array
{
    $stream = '';
    foreach (cms_rsc_chunks($html) as [$a, $b]) {
        $stream .= (string) json_decode(substr($html, $a, $b - $a));
    }
    foreach (explode("\n", $stream) as $line) {
        if (strpos($line, '"messages":{') === false) continue;
        $p = strpos($line, ':');
        $row = json_decode(substr($line, $p + 1), true);
        if (is_array($row) && isset($row[3]['messages']) && is_array($row[3]['messages'])) {
            return $row[3]['messages'];
        }
    }
    return null;
}

/** İç içe sözlüğü "hero.title" biçiminde düz listeye çevirir. */
function cms_flatten(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += cms_flatten($v, $key);
        } elseif (is_string($v)) {
            $out[$key] = $v;
        }
    }
    return $out;
}

/* ---------------------------------------------------------------
 * Veri dosyaları
 * ------------------------------------------------------------- */

function cms_read_json(string $file): array
{
    if (!is_file($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/** Orijinal metinler (extract.php ile üretilir). */
function cms_base(string $lang): array
{
    static $cache = [];
    return $cache[$lang] ??= cms_read_json(CMS_DATA . "/base_{$lang}.json");
}

/** Panelde değiştirilen metinler. */
function cms_overrides(string $lang): array
{
    static $cache = [];
    return $cache[$lang] ??= cms_read_json(CMS_DATA . "/overrides_{$lang}.json");
}

/** Sadece gerçekten değişmiş metinleri [eski, yeni] çiftleri olarak döndürür. */
function cms_replacements(string $lang): array
{
    $base = cms_base($lang);
    $pairs = [];
    foreach (cms_overrides($lang) as $key => $new) {
        if (!isset($base[$key]) || !is_string($new)) continue;
        $old = $base[$key];
        if ($old === $new || $old === '') continue;
        $pairs[] = [$old, $new];
    }
    // Uzun metinler önce: kısa bir metnin uzun metnin parçasını bozmasını engeller
    usort($pairs, fn($a, $b) => strlen($b[0]) <=> strlen($a[0]));
    return $pairs;
}

/* ---------------------------------------------------------------
 * Kodlama yardımcıları (Next.js'in yazdığı biçimle birebir aynı)
 * ------------------------------------------------------------- */

/** .txt RSC dosyalarındaki JSON string biçimi: "Metin" */
function cms_json_token(string $s): string
{
    return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** HTML'e gömülü RSC'deki biçim: <, >, & karakterleri \u003c... olarak kaçışlı */
function cms_json_token_html(string $s): string
{
    return str_replace(['<', '>', '&'], ['\u003c', '\u003e', '\u0026'], cms_json_token($s));
}

/** HTML içindeki <script> literal biçimi: \"Metin\" */
function cms_script_token(string $s): string
{
    $j = json_encode(cms_json_token_html($s), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return substr($j, 1, -1);
}

/** React'in metin kaçışı */
function cms_html_text(string $s): string
{
    return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&#x27;'], $s);
}

/* ---------------------------------------------------------------
 * Dönüştürme
 * ------------------------------------------------------------- */

/** Statik HTML sayfasına değişiklikleri uygular. */
function cms_transform_html(string $html, string $lang): string
{
    // Eski DOM yaması artık gerekmiyor
    $html = str_replace('<script src="/js/site-sync.js" defer></script>', '', $html);

    $pairs = cms_replacements($lang);
    if (!$pairs) return $html;

    $search = [];
    $replace = [];
    foreach ($pairs as [$old, $new]) {
        // 1) React'in gömülü verisi
        $search[] = cms_script_token($old);
        $replace[] = cms_script_token($new);
        // 2) Görünen metin düğümü: >Metin<
        $search[] = '>' . cms_html_text($old) . '<';
        $replace[] = '>' . cms_html_text($new) . '<';
        // 3) Metin taşıyan öznitelikler
        foreach (['alt', 'title', 'placeholder', 'aria-label', 'content'] as $attr) {
            $search[] = $attr . '="' . cms_html_text($old) . '"';
            $replace[] = $attr . '="' . cms_html_text($new) . '"';
        }
    }
    return str_replace($search, $replace, $html);
}

/** Next.js'in sayfa geçişlerinde kullandığı .txt RSC dosyalarına uygular. */
function cms_transform_rsc(string $txt, string $lang): string
{
    $pairs = cms_replacements($lang);
    if (!$pairs) return $txt;
    $search = [];
    $replace = [];
    foreach ($pairs as [$old, $new]) {
        $search[] = cms_json_token($old);
        $replace[] = cms_json_token($new);
    }
    return str_replace($search, $replace, $txt);
}

/** URL yolundan dili bulur. */
function cms_lang_from_path(string $path): string
{
    return preg_match('#^/en(/|$)#', $path) ? 'en' : 'tr';
}

/**
 * Bir isteği karşılar. İşlenmesi gereken bir sayfa değilse false döner
 * (o zaman sunucu dosyayı olduğu gibi verir).
 */
function cms_serve(string $path): bool
{
    $path = '/' . ltrim(rawurldecode($path), '/');

    if ($path === '/' || $path === '/index.html') {
        header('Location: /tr/', true, 302);
        return true;
    }

    // Dizin geçişi koruması
    if (str_contains($path, '..')) {
        http_response_code(400);
        return true;
    }

    $file = CMS_PUBLIC . $path;
    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.html';
    }

    $isHtml = str_ends_with($file, '.html');
    $isRsc  = str_ends_with($file, '.txt') && (str_contains(basename($file), '__next') || basename($file) === 'index.txt');

    if (!($isHtml || $isRsc) || !is_file($file)) {
        return false;
    }

    $lang = cms_lang_from_path($path);
    $body = (string) file_get_contents($file);

    if ($isHtml) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo cms_transform_html($body, $lang);
    } else {
        header('Content-Type: text/x-component; charset=utf-8');
        header('Cache-Control: no-cache');
        echo cms_transform_rsc($body, $lang);
    }
    return true;
}
