<?php
/**
 * Panel API — JSON uç noktaları
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (!admin_is_logged_in()) {
    admin_json_response(['ok' => false, 'error' => 'Oturum süresi doldu. Lütfen tekrar giriş yapın.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_json_response(['ok' => false, 'error' => 'Geçersiz istek.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if (!admin_csrf_ok($input['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? null))) {
    admin_json_response(['ok' => false, 'error' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.'], 403);
}

$action = (string) ($input['action'] ?? '');

switch ($action) {

    /* Sayfa metinlerini kaydet: changes = { tr: {key: value}, en: {...} } */
    case 'save_texts': {
        $changes = $input['changes'] ?? null;
        if (!is_array($changes)) admin_json_response(['ok' => false, 'error' => 'Değişiklik bulunamadı.'], 400);

        $saved = 0;
        foreach (CMS_LANGS as $lang) {
            if (empty($changes[$lang]) || !is_array($changes[$lang])) continue;
            $base = cms_base($lang);
            $file = CMS_DATA . "/overrides_{$lang}.json";
            $over = cms_read_json($file);

            foreach ($changes[$lang] as $key => $value) {
                $key = (string) $key;
                if (!isset($base[$key]) || !is_string($value)) continue;
                $value = str_replace("\r\n", "\n", $value);
                // Sabit sayfa metinlerinde baştaki/sondaki boşluk metin akışının parçası, koru
                if (cms_is_static_key($key)) {
                    preg_match('/^\s*/u', $base[$key], $lead);
                    preg_match('/\s*$/u', $base[$key], $trail);
                    $value = $lead[0] . trim($value) . $trail[0];
                }
                if (mb_strlen($value) > 5000) $value = mb_substr($value, 0, 5000);

                if ($value === $base[$key]) {
                    unset($over[$key]);          // orijinale dönüldü
                } elseif (trim($value) === '') {
                    continue;                    // boş metin sayfayı bozar, kabul etme
                } else {
                    $over[$key] = $value;
                }
                $saved++;
            }

            admin_backup_overrides($lang, 'Sayfa içerikleri kaydedildi');
            ksort($over);
            if (!admin_json_write($file, $over)) {
                admin_json_response(['ok' => false, 'error' => "overrides_{$lang}.json yazılamadı. Klasör izinlerini kontrol edin."], 500);
            }
        }
        admin_json_response(['ok' => true, 'saved' => $saved]);
    }

    /* Görsel / doküman değiştir: type, path, file (multipart) */
    case 'replace_media': {
        $type = (string) ($input['type'] ?? '');
        $path = (string) ($input['path'] ?? '');
        if (!isset(ADMIN_MEDIA_TYPES[$type])) admin_json_response(['ok' => false, 'error' => 'Geçersiz tür.'], 400);
        $files = admin_media_files($type);
        if (!isset($files[$path])) admin_json_response(['ok' => false, 'error' => 'Dosya bulunamadı.'], 404);
        $target = $files[$path];

        $up = $_FILES['file'] ?? null;
        if (!$up || ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = (int) ($up['error'] ?? UPLOAD_ERR_NO_FILE);
            $msg = in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Dosya sunucunun yükleme sınırını aşıyor (' . admin_bytes(admin_upload_limit()) . ').'
                : 'Dosya yüklenemedi.';
            admin_json_response(['ok' => false, 'error' => $msg], 400);
        }
        if ($up['size'] > ADMIN_MEDIA_TYPES[$type]['max']) {
            admin_json_response(['ok' => false, 'error' => 'Dosya çok büyük. En fazla ' . admin_bytes(ADMIN_MEDIA_TYPES[$type]['max']) . '.'], 400);
        }
        $tmp = (string) $up['tmp_name'];
        $warning = null;

        if ($type === 'docs') {
            $fh = fopen($tmp, 'rb');
            $magic = $fh ? fread($fh, 5) : '';
            if ($fh) fclose($fh);
            if ($magic !== '%PDF-') admin_json_response(['ok' => false, 'error' => 'Sadece PDF dosyası yüklenebilir.'], 400);
            $data = (string) file_get_contents($tmp);
        } else {
            $info = @getimagesize($tmp);
            $okTypes = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
            if (!$info || !isset($okTypes[$info[2]])) {
                admin_json_response(['ok' => false, 'error' => 'Sadece PNG, JPG, WEBP veya GIF görsel yüklenebilir.'], 400);
            }
            $want = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            $want = $want === 'jpeg' ? 'jpg' : $want;
            $have = $okTypes[$info[2]];

            if ($have === $want) {
                $data = (string) file_get_contents($tmp);
            } elseif (function_exists('imagecreatefromstring')) {
                // Farklı formatta yüklendi: dosya adı aynı kalacağı için hedef formata çevir
                $img = @imagecreatefromstring((string) file_get_contents($tmp));
                if (!$img) admin_json_response(['ok' => false, 'error' => 'Görsel okunamadı.'], 400);
                ob_start();
                $ok = match ($want) {
                    'png'  => (function () use ($img) { imagesavealpha($img, true); return imagepng($img, null, 6); })(),
                    'jpg'  => imagejpeg($img, null, 88),
                    'webp' => function_exists('imagewebp') && imagewebp($img, null, 88),
                    'gif'  => imagegif($img),
                    default => false,
                };
                $data = (string) ob_get_clean();
                imagedestroy($img);
                if (!$ok || $data === '') admin_json_response(['ok' => false, 'error' => 'Görsel ' . strtoupper($want) . ' formatına çevrilemedi.'], 400);
            } else {
                admin_json_response(['ok' => false, 'error' => 'Lütfen görseli ' . strtoupper($want) . ' formatında yükleyin.'], 400);
            }

            // En-boy oranı çok farklıysa uyar
            $old = @getimagesize($target);
            if ($old && $old[1] > 0 && $info[1] > 0) {
                $r1 = $old[0] / $old[1];
                $r2 = $info[0] / $info[1];
                if (abs($r1 - $r2) / $r1 > 0.08) {
                    $warning = "Yeni görselin oranı farklı ({$info[0]}×{$info[1]}, orijinal {$old[0]}×{$old[1]}). Sitede kırpılmış veya farklı görünebilir.";
                }
            }
        }

        // İlk değişiklikte orijinali sakla
        $orig = admin_media_original($path);
        if (!is_file($orig)) {
            if (!is_dir(dirname($orig))) mkdir(dirname($orig), 0755, true);
            copy($target, $orig);
        }
        $tmpTarget = $target . '.upload' . bin2hex(random_bytes(4));
        if (file_put_contents($tmpTarget, $data) === false || !rename($tmpTarget, $target)) {
            @unlink($tmpTarget);
            admin_json_response(['ok' => false, 'error' => 'Dosya yazılamadı. Klasör izinlerini kontrol edin.'], 500);
        }
        clearstatcache(true, $target);

        $log = cms_read_json(ADMIN_MEDIA_LOG);
        array_unshift($log, ['time' => time(), 'user' => $_SESSION['admin_user'] ?? '', 'action' => 'replace', 'path' => $path, 'name' => (string) $up['name']]);
        admin_json_write(ADMIN_MEDIA_LOG, array_slice($log, 0, 500));

        admin_json_response(['ok' => true, 'warning' => $warning, 'mtime' => filemtime($target)]);
    }

    /* Görsel / dokümanı orijinaline döndür */
    case 'restore_media': {
        $type = (string) ($input['type'] ?? '');
        $path = (string) ($input['path'] ?? '');
        if (!isset(ADMIN_MEDIA_TYPES[$type])) admin_json_response(['ok' => false, 'error' => 'Geçersiz tür.'], 400);
        $files = admin_media_files($type);
        $orig = admin_media_original($path);
        if (!isset($files[$path]) || !is_file($orig)) admin_json_response(['ok' => false, 'error' => 'Orijinal dosya bulunamadı.'], 404);

        if (!copy($orig, $files[$path])) admin_json_response(['ok' => false, 'error' => 'Dosya geri yüklenemedi.'], 500);
        unlink($orig);

        $log = cms_read_json(ADMIN_MEDIA_LOG);
        array_unshift($log, ['time' => time(), 'user' => $_SESSION['admin_user'] ?? '', 'action' => 'restore', 'path' => $path]);
        admin_json_write(ADMIN_MEDIA_LOG, array_slice($log, 0, 500));
        admin_json_response(['ok' => true]);
    }

    /* Mesaj durumu: id, status (read|unread) ya da delete */
    case 'message_status':
    case 'message_delete': {
        $id = (string) ($input['id'] ?? '');
        $file = CMS_DATA . '/messages.json';
        $fh = fopen($file, 'c+');
        if (!$fh || !flock($fh, LOCK_EX)) admin_json_response(['ok' => false, 'error' => 'Mesaj dosyası açılamadı.'], 500);
        $msgs = json_decode((string) stream_get_contents($fh), true);
        $msgs = is_array($msgs) ? $msgs : [];
        $found = false;
        foreach ($msgs as $i => $m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $id) continue;
            $found = true;
            if ($action === 'message_delete') {
                unset($msgs[$i]);
            } else {
                $msgs[$i]['status'] = ($input['status'] ?? '') === 'unread' ? 'unread' : 'read';
            }
        }
        if ($found) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode(array_values($msgs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        flock($fh, LOCK_UN);
        fclose($fh);
        admin_json_response($found ? ['ok' => true] : ['ok' => false, 'error' => 'Mesaj bulunamadı.'], $found ? 200 : 404);
    }

    /* Firma bilgilerini kaydet: values = {alan: {tr: "...", en: "..."}}, notification_email */
    case 'save_company': {
        $values = is_array($input['values'] ?? null) ? $input['values'] : [];
        $fields = admin_company_fields();
        $notify = trim((string) ($input['notification_email'] ?? ''));
        if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            admin_json_response(['ok' => false, 'error' => 'Bildirim e-postası geçerli değil.'], 400);
        }
        if (!empty($values['email']['tr']) && !filter_var(trim((string) $values['email']['tr']), FILTER_VALIDATE_EMAIL)) {
            admin_json_response(['ok' => false, 'error' => 'E-posta adresi geçerli değil.'], 400);
        }

        $over = [];
        foreach (CMS_LANGS as $l) $over[$l] = cms_read_json(CMS_DATA . "/overrides_{$l}.json");
        $links = cms_read_json(CMS_DATA . '/link_overrides.json');

        foreach ($fields as $id => $f) {
            if (!isset($values[$id]) || !is_array($values[$id])) continue;
            foreach (CMS_LANGS as $l) {
                $val = trim(str_replace("\r\n", "\n", (string) ($f['per_lang'] ? ($values[$id][$l] ?? '') : ($values[$id]['tr'] ?? ''))));
                if ($val === '') continue;
                $base = cms_base($l);
                $targets = [];
                foreach ($f['keys'] as $k) $targets[$k] = $val;
                foreach ($f['flat_keys'] ?? [] as $k) $targets[$k] = trim((string) preg_replace('/\s*\n\s*/', ' ', $val));
                foreach ($targets as $k => $v) {
                    if (!isset($base[$k])) continue;
                    if ($v === $base[$k]) unset($over[$l][$k]); else $over[$l][$k] = $v;
                }
            }
            // tel: / mailto: linkleri (orijinal linkten yeni linke)
            if (!empty($f['link'])) {
                $origVal = (string) (cms_base('tr')[$f['keys'][0]] ?? '');
                $newVal = trim((string) ($values[$id]['tr'] ?? ''));
                if ($origVal !== '' && $newVal !== '') {
                    $from = $f['link'] === 'tel' ? admin_tel_link($origVal) : 'mailto:' . $origVal;
                    $to   = $f['link'] === 'tel' ? admin_tel_link($newVal) : 'mailto:' . $newVal;
                    if ($from === $to) unset($links[$from]); else $links[$from] = $to;
                }
            }
        }

        foreach (CMS_LANGS as $l) {
            admin_backup_overrides($l, 'Firma bilgileri kaydedildi');
            ksort($over[$l]);
            if (!admin_json_write(CMS_DATA . "/overrides_{$l}.json", $over[$l])) {
                admin_json_response(['ok' => false, 'error' => 'Kaydedilemedi. data klasörünün yazma iznini kontrol edin.'], 500);
            }
        }
        admin_json_write(CMS_DATA . '/link_overrides.json', $links);

        // Form bildirim adresi (contact-handler.php settings.json'dan okur)
        $settings = cms_read_json(CMS_DATA . '/settings.json');
        $settings['general'] = (array) ($settings['general'] ?? []);
        $settings['general']['notification_email'] = $notify;
        $settings['updated_at'] = date('c');
        admin_json_write(CMS_DATA . '/settings.json', $settings);

        admin_json_response(['ok' => true]);
    }

    /* Sürüm geçmişinden geri yükle */
    case 'restore_backup': {
        $name = basename((string) ($input['name'] ?? ''));
        $index = cms_read_json(ADMIN_BACKUP_DIR . '/index.json');
        $file = ADMIN_BACKUP_DIR . '/' . $name;
        if (!preg_match('/^overrides_(tr|en)_[0-9-]+\.json$/', $name) || !isset($index[$name]) || !is_file($file)) {
            admin_json_response(['ok' => false, 'error' => 'Yedek bulunamadı.'], 404);
        }
        $lang = (string) $index[$name]['lang'];
        $data = cms_read_json($file);
        admin_backup_overrides($lang, 'Geri yüklemeden önceki hâl');
        if (!admin_json_write(CMS_DATA . "/overrides_{$lang}.json", $data)) {
            admin_json_response(['ok' => false, 'error' => 'Geri yüklenemedi.'], 500);
        }
        admin_json_response(['ok' => true]);
    }

    default:
        admin_json_response(['ok' => false, 'error' => 'Bilinmeyen işlem.'], 400);
}
