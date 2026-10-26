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

/**
 * Metni sözlükte değil, doğrudan sayfa koduna yazılmış sayfalar.
 * önek => sayfa klasörü. Bu sayfaların metinleri extract.php ile çıkarılır.
 */
const CMS_STATIC_PAGES = ['privacyPage' => 'privacy'];

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

/** Orijinal metinler. Dosya yoksa ilk istekte statik sayfalardan otomatik üretilir. */
function cms_base(string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        if (!is_file(CMS_DATA . "/base_{$lang}.json")) cms_build_base();
        $cache[$lang] = cms_read_json(CMS_DATA . "/base_{$lang}.json");
    }
    return $cache[$lang];
}

/**
 * Düzenlenebilir metinleri statik sayfalardan çıkarır:
 *  - data/base_{lang}.json : tüm metinler (next-intl sözlüğü + sabit sayfalar)
 *  - data/static_meta.json : sabit sayfalar için bölüm ve alan adları
 * Panel değişiklikleri (overrides) etkilenmez. Dönüş: [mesaj, ...]
 */
function cms_build_base(): array
{
    static $labels = [
        'h1' => 'Sayfa başlığı', 'h2' => 'Bölüm başlığı', 'h3' => 'Alt başlık', 'h4' => 'Alt başlık',
        'p' => 'Paragraf', 'li' => 'Liste maddesi', 'a' => 'Link', 'th' => 'Tablo başlığı',
        'td' => 'Tablo hücresi', 'strong' => 'Kalın metin', 'b' => 'Kalın metin', 'em' => 'Vurgulu metin',
        'span' => 'Metin',
    ];
    $log = [];
    $base = [];
    $meta = [];

    foreach (CMS_LANGS as $lang) {
        $file = CMS_PUBLIC . "/{$lang}/index.html";
        $messages = is_file($file) ? cms_extract_messages((string) file_get_contents($file)) : null;
        $base[$lang] = $messages ? cms_flatten($messages) : [];
        if (!$messages) $log[] = "[{$lang}] sözlük bulunamadı: {$file}";
    }

    foreach (CMS_STATIC_PAGES as $prefix => $dir) {
        $nodes = [];
        foreach (CMS_LANGS as $lang) {
            $f = CMS_PUBLIC . "/{$lang}/{$dir}/index.html";
            $nodes[$lang] = is_file($f) ? cms_static_nodes((string) file_get_contents($f)) : [];
        }
        $ref = $nodes['tr'] ?: reset($nodes);
        if (!$ref) continue;

        $keyOf = [];
        $nodeKey = [];
        $section = 0;
        $t = 0;
        $meta["{$prefix}.s00"] = 'Giriş';
        foreach ($ref as $i => $n) {
            if ($n['heading'] && $n['tag'] === 'h2') {
                $section++;
                $meta[sprintf('%s.s%02d', $prefix, $section)] = trim($n['raw']);
            }
            if (!isset($keyOf[$n['raw']])) {
                $key = sprintf('%s.s%02d.t%03d', $prefix, $section, ++$t);
                $keyOf[$n['raw']] = $key;
                $meta[$key] = $labels[$n['tag']] ?? 'Metin';
            } elseif ($n['tag'] === 'h2') {
                $meta[$keyOf[$n['raw']]] = 'Bölüm başlığı (içindekilerde de)';
            }
            $nodeKey[$i] = $keyOf[$n['raw']];
        }
        foreach (CMS_LANGS as $lang) {
            $list = $nodes[$lang];
            $aligned = count($list) === count($ref);
            foreach ($nodeKey as $i => $key) {
                if (!isset($base[$lang][$key])) $base[$lang][$key] = $aligned ? $list[$i]['raw'] : $ref[$i]['raw'];
            }
        }
        $log[] = "[{$dir}] " . count($keyOf) . " sabit metin çıkarıldı";
    }

    foreach (CMS_LANGS as $lang) {
        if (!$base[$lang]) continue;
        file_put_contents(CMS_DATA . "/base_{$lang}.json",
            json_encode($base[$lang], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if (!is_file(CMS_DATA . "/overrides_{$lang}.json")) {
            $n = cms_migrate_legacy($lang, $base[$lang]);   // eski panelin değişikliklerini taşı
            $log[] = "[{$lang}] eski panelden {$n} değişiklik taşındı";
        }
        @unlink(CMS_DATA . "/unused_{$lang}.json");
        $log[] = "[{$lang}] " . count($base[$lang]) . " metin -> data/base_{$lang}.json";
    }
    file_put_contents(CMS_DATA . '/static_meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
    return $log;
}

/** Panelde değiştirilen metinler. */
function cms_overrides(string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        // İlk kurulum: base ve (eski panelden taşınan) overrides dosyaları üretilir
        if (!is_file(CMS_DATA . "/overrides_{$lang}.json")) cms_base($lang);
        if (!is_file(CMS_DATA . "/overrides_{$lang}.json") && is_file(CMS_DATA . "/base_{$lang}.json")) {
            cms_migrate_legacy($lang, cms_base($lang));
        }
        $cache[$lang] = cms_read_json(CMS_DATA . "/overrides_{$lang}.json");
    }
    return $cache[$lang];
}

/**
 * Hangi sayfada hangi sözlük bölümleri kullanılıyor.
 * Görünen metin değişimi sadece ilgili sayfada yapılır; böylece aynı metin
 * başka bir sayfada geçiyorsa oradaki etkilenmez.
 */
function cms_page_prefixes(string $path): ?array
{
    $p = preg_replace('#^/(tr|en)(?=/|$)#', '', $path);
    $p = '/' . trim((string) $p, '/');
    $map = [
        '/'                             => ['hero', 'solutions', 'products', 'technology', 'stats', 'cta'],
        '/products'                     => ['productsPage'],
        '/products/tuna'                => ['tunaPage'],
        '/products/altay'               => ['altayPage'],
        '/products/aras'                => ['arasPage'],
        '/products/aras-l'              => ['arasLPage'],
        '/products/aras-t'              => ['arasTPage'],
        '/solutions'                    => ['solutionsPage'],
        '/solutions/logyard'            => ['logyardPage'],
        '/solutions/primary-breakdown'  => ['primaryBreakdownPage'],
        '/solutions/secondary-breakdown'=> ['secondaryBreakdownPage'],
        '/software'                     => ['softwarePage'],
        '/software/woodscan'            => ['woodscanPage'],
        '/software/optisoft'            => ['optisoftPage'],
        '/wooderp'                      => ['wooderpPage'],
        '/technology'                   => ['technologyPage'],
        '/case-studies'                 => ['caseStudiesPage'],
        '/contact'                      => ['contactPage'],
        '/privacy'                      => ['privacyPage'],
    ];
    if (!isset($map[$p])) return null;              // bilinmeyen sayfa: kapsam yok
    return array_merge($map[$p], ['nav', 'footer']); // menü ve footer her sayfada
}

/**
 * Görünen HTML'de değiştirilecek [eski, yeni] çiftleri.
 * $prefixes verilirse sadece o sayfanın bölümlerindeki değişiklikler alınır.
 * Aynı sayfada aynı metni taşıyan ve değişmeyen başka bir alan varsa,
 * o alanı bozmamak için görünen metne dokunulmaz (React yüklenince düzeltir).
 */
function cms_replacements(string $lang, ?array $prefixes = null): array
{
    $base = cms_base($lang);
    $over = cms_overrides($lang);
    $inScope = function (string $key) use ($prefixes): bool {
        if ($prefixes === null) return true;
        return in_array(strstr($key, '.', true) ?: $key, $prefixes, true);
    };

    $pairs = [];
    foreach ($over as $key => $new) {
        if (!isset($base[$key]) || !is_string($new) || !$inScope($key)) continue;
        $old = $base[$key];
        if ($old === $new || $old === '') continue;

        // Aynı sayfada aynı metne sahip, farklı kalacak başka alan var mı?
        $conflict = false;
        foreach ($base as $k2 => $v2) {
            if ($k2 === $key || $v2 !== $old || !$inScope($k2)) continue;
            if (($over[$k2] ?? $v2) !== $new) { $conflict = true; break; }
        }
        if (!$conflict) $pairs[$old] = $new;
    }

    $list = [];
    foreach ($pairs as $old => $new) $list[] = [(string) $old, (string) $new];
    // Uzun metinler önce: kısa bir metnin uzun metnin parçasını bozmasını engeller
    usort($list, fn($a, $b) => strlen($b[0]) <=> strlen($a[0]));
    return $list;
}

/** Sözlükte "a.b.c" yolundaki değeri değiştirir (sadece var olan metinleri). */
function cms_set_path(array &$arr, string $path, string $value): void
{
    $ref = &$arr;
    foreach (explode('.', $path) as $seg) {
        if (!is_array($ref) || !array_key_exists($seg, $ref)) return;
        $ref = &$ref[$seg];
    }
    if (is_string($ref)) $ref = $value;
}

/** RSC akışındaki "messages" satırına değişiklikleri anahtar bazında uygular. */
function cms_patch_messages_line(string $line, string $lang, bool $htmlSafe): string
{
    $p = strpos($line, ':');
    if ($p === false) return $line;
    $row = json_decode(substr($line, $p + 1), true);
    if (!is_array($row) || !isset($row[3]['messages']) || !is_array($row[3]['messages'])) return $line;

    $base = cms_base($lang);
    $changed = false;
    foreach (cms_overrides($lang) as $key => $val) {
        if (!isset($base[$key]) || !is_string($val) || $val === $base[$key]) continue;
        cms_set_path($row[3]['messages'], $key, $val);
        $changed = true;
    }
    if (!$changed) return $line;

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($htmlSafe) $flags |= JSON_HEX_TAG | JSON_HEX_AMP;
    return substr($line, 0, $p + 1) . json_encode($row, $flags);
}

/**
 * Sözlükte olup sitede hiç görünmeyen anahtarlar (eski/kullanılmayan metinler).
 * Statik sayfalar taranır; sonuç data/unused_{lang}.json'da önbelleklenir ve
 * base dosyası değişince yeniden hesaplanır.
 */
function cms_unused_keys(string $lang): array
{
    $baseFile  = CMS_DATA . "/base_{$lang}.json";
    $cacheFile = CMS_DATA . "/unused_{$lang}.json";
    $mtime = is_file($baseFile) ? filemtime($baseFile) : 0;
    $cache = cms_read_json($cacheFile);
    if (($cache['base_mtime'] ?? null) === $mtime && isset($cache['keys']) && is_array($cache['keys'])) {
        return $cache['keys'];
    }

    // Sayfaların görünür HTML'i (script'ler çıkarılmış, React ayraçları silinmiş)
    $pages = [];
    $dir = CMS_PUBLIC . '/' . $lang;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getFilename() !== 'index.html') continue;
            $html = (string) file_get_contents($f->getPathname());
            $html = preg_replace('#<script\b[^>]*>.*?</script>#s', '', $html);
            $pages[substr($f->getPath(), strlen(CMS_PUBLIC)) . '/'] = str_replace('<!-- -->', '', (string) $html);
        }
    }

    $unused = [];
    foreach (cms_base($lang) as $key => $val) {
        if ($val === '') continue;
        $top = strstr($key, '.', true) ?: $key;
        $needle = cms_html_text($val);
        $found = false;
        foreach ($pages as $path => $html) {
            $scope = cms_page_prefixes($path);
            if ($scope !== null && !in_array($top, $scope, true)) continue;
            if (strpos($html, $needle) !== false) { $found = true; break; }
        }
        if (!$found) $unused[] = $key;
    }

    @file_put_contents($cacheFile, json_encode(['base_mtime' => $mtime, 'keys' => $unused], JSON_UNESCAPED_UNICODE) . "\n");
    return $unused;
}

/** Anahtar, sözlükte değil sayfa koduna gömülü bir metne mi ait? */
function cms_is_static_key(string $key): bool
{
    return isset(CMS_STATIC_PAGES[strstr($key, '.', true) ?: $key]);
}

/**
 * Sabit sayfadaki (<main> içi) metin düğümlerini sırasıyla döndürür.
 * Her öğe: raw (metnin kendisi), tag (içinde bulunduğu etiket), heading (h2 mi).
 */
function cms_static_nodes(string $html): array
{
    $s = strpos($html, '<main');
    $e = $s === false ? false : strpos($html, '</main>', $s);
    if ($s === false || $e === false) return [];

    $parts = preg_split('/(<[^>]+>)/', substr($html, $s, $e - $s), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $void = ['br', 'img', 'hr', 'input', 'meta', 'link', 'source', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon'];
    $stack = [];
    $out = [];
    foreach ($parts as $p) {
        if ($p[0] === '<') {
            if (preg_match('#^<(/?)([a-zA-Z0-9]+)#', $p, $m)) {
                $tag = strtolower($m[2]);
                if ($m[1] === '/') {
                    while ($stack && array_pop($stack) !== $tag) {}
                } elseif (!str_ends_with($p, '/>') && !in_array($tag, $void, true)) {
                    $stack[] = $tag;
                }
            }
            continue;
        }
        $raw = html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('/\p{L}/u', $raw)) continue;
        $out[] = ['raw' => $raw, 'tag' => (string) end($stack), 'heading' => in_array('h2', $stack, true)];
    }
    return $out;
}

/** Sabit sayfa metinleri için [eski, yeni] çiftleri (sayfa kapsamına göre). */
function cms_static_pairs(string $lang, ?array $prefixes): array
{
    if ($prefixes === null) return [];
    $base = cms_base($lang);
    $pairs = [];
    foreach (cms_overrides($lang) as $key => $new) {
        if (!cms_is_static_key($key) || !isset($base[$key]) || !is_string($new)) continue;
        if (!in_array(strstr($key, '.', true), $prefixes, true)) continue;
        if ($new !== $base[$key]) $pairs[] = [$base[$key], $new];
    }
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
function cms_transform_html(string $html, string $lang, ?array $prefixes = null): string
{
    // Eski DOM yaması artık gerekmiyor
    $html = str_replace('<script src="/js/site-sync.js" defer></script>', '', $html);
    $html = cms_apply_links($html);

    if (!cms_overrides($lang)) return $html;

    // 1) React'in gömülü verisi (RSC):
    //    - sözlük satırında anahtar bazında kesin değişim
    //    - sabit sayfalarda (gizlilik vb.) diğer satırlarda metin değişimi
    $static = cms_static_pairs($lang, $prefixes);
    foreach (array_reverse(cms_rsc_chunks($html)) as [$a, $b]) {
        $literal = substr($html, $a, $b - $a);
        $hasMessages = strpos($literal, 'messages') !== false;
        if (!$hasMessages && !$static) continue;
        $stream = json_decode($literal);
        if (!is_string($stream)) continue;

        $lines = explode("\n", $stream);
        $changed = false;
        foreach ($lines as $i => $line) {
            if (strpos($line, '"messages":{') !== false) {
                $new = cms_patch_messages_line($line, $lang, true);
            } elseif ($static) {
                $new = $line;
                foreach ($static as [$old, $rep]) {
                    $new = str_replace(cms_json_token_html($old), cms_json_token_html($rep), $new);
                }
            } else {
                continue;
            }
            if ($new !== $line) { $lines[$i] = $new; $changed = true; }
        }
        if (!$changed) continue;
        $newLiteral = json_encode(implode("\n", $lines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $html = substr($html, 0, $a) . $newLiteral . substr($html, $b);
    }

    // 2) Görünen metin: sayfayı bölgelere ayırıp her bölgeye sadece ona ait
    //    değişiklikleri uygula (menü <main> öncesinde, footer <main> sonrasında)
    if ($prefixes === null) {
        return cms_replace_visible($html, cms_replacements($lang));
    }
    $pageOnly = array_values(array_diff($prefixes, ['nav', 'footer']));
    $mStart = strpos($html, '<main');
    $mEnd = $mStart === false ? false : strpos($html, '</main>', $mStart);
    if ($mStart === false || $mEnd === false) {
        return cms_replace_visible($html, cms_replacements($lang, $prefixes));
    }
    $head = substr($html, 0, $mStart);
    $main = substr($html, $mStart, $mEnd - $mStart);
    $tail = substr($html, $mEnd);

    return cms_replace_visible($head, cms_replacements($lang, ['nav']))
         . cms_replace_visible($main, cms_replacements($lang, $pageOnly))
         . cms_replace_visible($tail, cms_replacements($lang, ['nav', 'footer']));
}

/** Firma Bilgileri'nden gelen link değişimleri: ["tel:+90...": "tel:+90...", "mailto:a@b": "mailto:c@d"] */
function cms_link_overrides(): array
{
    static $map = null;
    if ($map === null) {
        $map = array_filter(cms_read_json(CMS_DATA . '/link_overrides.json'), fn($v, $k) => is_string($v) && $v !== $k, ARRAY_FILTER_USE_BOTH);
    }
    return $map;
}

/** tel:/mailto: linklerini değiştirir; sayfa içi geçişler için küçük bir yardımcı script ekler. */
function cms_apply_links(string $html): string
{
    $map = cms_link_overrides();
    if (!$map) return $html;
    foreach ($map as $old => $new) {
        $html = str_replace('href="' . cms_html_text((string) $old) . '"', 'href="' . cms_html_text($new) . '"', $html);
    }
    // Next.js sayfa geçişlerinde linkler koddan geldiği için tıklama anında düzeltilir
    $js = '<script>(function(m){document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");'
        . 'if(a&&m[a.getAttribute("href")])a.setAttribute("href",m[a.getAttribute("href")]);},true);})('
        . json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ');</script>';
    return str_replace('</body>', $js . '</body>', $html);
}

/** Görünen metin düğümlerinde ve metin öznitelikilerinde değişim yapar. */
function cms_replace_visible(string $html, array $pairs): string
{
    if (!$pairs) return $html;
    $search = [];
    $replace = [];
    foreach ($pairs as [$old, $new]) {
        $search[] = '>' . cms_html_text($old) . '<';
        $replace[] = '>' . cms_html_text($new) . '<';
        foreach (['alt', 'title', 'placeholder', 'aria-label', 'content'] as $attr) {
            $search[] = $attr . '="' . cms_html_text($old) . '"';
            $replace[] = $attr . '="' . cms_html_text($new) . '"';
        }
    }
    return str_replace($search, $replace, $html);
}

/** Next.js'in sayfa geçişlerinde kullandığı .txt RSC dosyalarına uygular. */
function cms_transform_rsc(string $txt, string $lang, ?array $prefixes = null): string
{
    if (!cms_overrides($lang)) return $txt;
    $static = cms_static_pairs($lang, $prefixes);
    if (!$static && strpos($txt, '"messages":{') === false) return $txt;

    $lines = explode("\n", $txt);
    foreach ($lines as $i => $line) {
        if (strpos($line, '"messages":{') !== false) {
            $lines[$i] = cms_patch_messages_line($line, $lang, false);
        } elseif ($static) {
            foreach ($static as [$old, $rep]) {
                $lines[$i] = str_replace(cms_json_token($old), cms_json_token($rep), $lines[$i]);
            }
        }
    }
    return implode("\n", $lines);
}

/**
 * Eski panelin (content_{lang}.json) canlıda görünen değişikliklerini override'a taşır.
 * Sadece ana sayfa hero ve CTA metinleri otomatik taşınır; ürün istatistikleri gibi
 * teknik değerler data/migration_review_{lang}.json'a "kontrol edilecek" olarak yazılır.
 * Dönüş: taşınan değişiklik sayısı.
 */
function cms_migrate_legacy(string $lang, array $base): int
{
    $overFile = CMS_DATA . "/overrides_{$lang}.json";
    $over = cms_read_json($overFile);
    $content = cms_read_json(CMS_DATA . "/content_{$lang}.json");
    $clean = fn($v) => is_string($v) && trim($v) !== '' ? trim((string) preg_replace('/\s+/u', ' ', $v)) : null;
    $get = function (array $a, string $path) {
        foreach (explode('.', $path) as $p) { if (!is_array($a) || !array_key_exists($p, $a)) return null; $a = $a[$p]; }
        return $a;
    };

    $added = 0;
    foreach (['hero.badge', 'hero.title', 'hero.titleHighlight', 'hero.subtitle', 'hero.subtitle1', 'hero.subtitle2', 'cta.title', 'cta.subtitle'] as $key) {
        $val = $clean($get($content, $key));
        if ($val === null || !isset($base[$key]) || $val === $base[$key] || array_key_exists($key, $over)) continue;
        $over[$key] = $val;
        $added++;
    }

    $review = [];
    $pages = ['tuna' => 'tunaPage', 'altay' => 'altayPage', 'arasL' => 'arasLPage', 'arasT' => 'arasTPage'];
    foreach ((array) $get($content, 'products.list') as $prod) {
        $page = $pages[$prod['id'] ?? ''] ?? null;
        if (!$page) continue;
        foreach (array_values((array) ($prod['stats'] ?? [])) as $i => $stat) {
            foreach (['Value' => 'value', 'Label' => 'label'] as $suffix => $field) {
                $key = "{$page}.hero.stat" . ($i + 1) . $suffix;
                $val = $clean($stat[$field] ?? null);
                if ($val !== null && isset($base[$key]) && $val !== $base[$key]) $review[$key] = ['site' => $base[$key], 'eski_panel' => $val];
            }
        }
    }

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    file_put_contents($overFile, json_encode($over ?: new stdClass(), $flags) . "\n", LOCK_EX);
    if ($review) file_put_contents(CMS_DATA . "/migration_review_{$lang}.json", json_encode($review, $flags) . "\n");
    return $added;
}

/** Sade 404 sayfası (Next.js'in 404 sayfası /tr/tr/... döngüsüne girebildiği için kullanılmıyor). */
function cms_not_found(string $path): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $en = cms_lang_from_path($path) === 'en';
    $home = $en ? '/en/' : '/tr/';
    $title = $en ? 'Page not found' : 'Sayfa bulunamadı';
    $text = $en ? 'The page you are looking for does not exist or has been moved.' : 'Aradığınız sayfa mevcut değil veya taşınmış olabilir.';
    $btn = $en ? 'Go to homepage' : 'Ana sayfaya dön';
    echo '<!doctype html><html lang="' . ($en ? 'en' : 'tr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>404 · OEM Vision</title><link rel="icon" href="/favicon.ico"><style>'
       . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b0f0d;color:#e8ede9;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif;text-align:center;padding:24px}'
       . 'b{display:block;font-size:72px;line-height:1;color:#3ddc84}h1{font-size:24px;margin:16px 0 8px}p{color:#9aa59f;margin:0 0 24px}'
       . 'a{display:inline-block;background:#22c55e;color:#04210f;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none}</style></head>'
       . '<body><main><b>404</b><h1>' . $title . '</h1><p>' . $text . '</p><a href="' . $home . '">' . $btn . '</a></main></body></html>';
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
        echo cms_transform_html($body, $lang, cms_page_prefixes($path));
    } else {
        header('Content-Type: text/x-component; charset=utf-8');
        header('Cache-Control: no-cache');
        echo cms_transform_rsc($body, $lang, cms_page_prefixes(dirname($path)));
    }
    return true;
}
