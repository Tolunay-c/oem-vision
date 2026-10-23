<?php
/**
 * OEM Vision — Enterprise Admin Backend API
 * 
 * Handles Content CMS, Products, Solutions, Software, Pages, Navigation Menu,
 * Media Library, Inquiries/Inbox, Document PDFs, Rollback Snapshots & Settings.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Oturum kontrolü
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim. Lütfen giriş yapınız.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF kontrolü (POST istekleri için)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF güvenlik belirteci geçersiz.']);
        exit;
    }
}

// =========================================================================
// 1. AYARLAR & GÜVENLİK
// =========================================================================

// Ayarları Getir
if ($action === 'get_settings') {
    $settings = file_exists(SETTINGS_FILE) ? json_decode((string)file_get_contents(SETTINGS_FILE), true) : [];
    echo json_encode(['success' => true, 'data' => $settings]);
    exit;
}

// Ayarları Kaydet
if ($action === 'save_settings') {
    $currentSettings = file_exists(SETTINGS_FILE) ? json_decode((string)file_get_contents(SETTINGS_FILE), true) : [];

    $phone = trim((string)($_POST['phone'] ?? ''));
    $gsm = trim((string)($_POST['gsm'] ?? ''));
    $phoneClean = preg_replace('/[^\d+]/', '', $phone);
    $gsmClean   = preg_replace('/[^\d+]/', '', $gsm);

    $updated = [
        'general' => [
            'company_name'       => trim((string)($_POST['company_name'] ?? 'OEM Vision')),
            'company_full_title' => trim((string)($_POST['company_full_title'] ?? '')),
            'phone'              => $phone,
            'phone_clean'        => $phoneClean,
            'gsm'                => $gsm,
            'gsm_clean'          => $gsmClean,
            'email'              => trim((string)($_POST['email'] ?? '')),
            'notification_email' => trim((string)($_POST['notification_email'] ?? '')),
            'address'            => trim((string)($_POST['address'] ?? '')),
            'working_hours'      => trim((string)($_POST['working_hours'] ?? '')),
            'google_maps_url'    => trim((string)($_POST['google_maps_url'] ?? '')),
            'mersis_no'          => trim((string)($_POST['mersis_no'] ?? '')),
            'kep_address'        => trim((string)($_POST['kep_address'] ?? '')),
        ],
        'social' => [
            'linkedin'  => trim((string)($_POST['social_linkedin'] ?? '')),
            'youtube'   => trim((string)($_POST['social_youtube'] ?? '')),
            'instagram' => trim((string)($_POST['social_instagram'] ?? '')),
            'x'         => trim((string)($_POST['social_x'] ?? '')),
        ],
        'docs' => $currentSettings['docs'] ?? [],
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $result = @file_put_contents(SETTINGS_FILE, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => $t['saved_success']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ayar dosyasına yazılamadı.']);
    }
    exit;
}

// Şifre Değiştir
if ($action === 'change_password') {
    $currentPass = (string)($_POST['current_password'] ?? '');
    $newPass     = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['new_password_confirm'] ?? '');

    $auth = file_exists(AUTH_FILE) ? json_decode((string)file_get_contents(AUTH_FILE), true) : [];
    $hash = $auth['password_hash'] ?? '';

    if (!password_verify($currentPass, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Mevcut parola hatalı.']);
        exit;
    }
    if (strlen($newPass) < 8) {
        echo json_encode(['success' => false, 'message' => $t['password_too_short']]);
        exit;
    }
    if ($newPass !== $confirmPass) {
        echo json_encode(['success' => false, 'message' => $t['password_mismatch']]);
        exit;
    }

    $auth['password_hash'] = password_hash($newPass, PASSWORD_BCRYPT);
    $auth['updated_at'] = date('Y-m-d H:i:s');
    @file_put_contents(AUTH_FILE, json_encode($auth, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'message' => $t['password_changed']]);
    exit;
}

// =========================================================================
// 2. GELEN FORMLAR & MESAJLAR (INBOX)
// =========================================================================

if ($action === 'get_messages') {
    $messages = file_exists(MESSAGES_FILE) ? json_decode((string)file_get_contents(MESSAGES_FILE), true) ?: [] : [];
    $unreadCount = 0;
    foreach ($messages as $m) {
        if (($m['status'] ?? '') === 'unread') $unreadCount++;
    }
    echo json_encode(['success' => true, 'messages' => $messages, 'unread_count' => $unreadCount]);
    exit;
}

if ($action === 'update_message_status') {
    $id = (string)($_POST['id'] ?? '');
    $newStatus = (string)($_POST['status'] ?? 'read');
    $messages = file_exists(MESSAGES_FILE) ? json_decode((string)file_get_contents(MESSAGES_FILE), true) ?: [] : [];
    
    $found = false;
    foreach ($messages as &$m) {
        if ($m['id'] === $id) {
            $m['status'] = $newStatus;
            $found = true;
            break;
        }
    }
    unset($m);

    if ($found) {
        @file_put_contents(MESSAGES_FILE, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => 'Mesaj durumu güncellendi.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mesaj bulunamadı.']);
    }
    exit;
}

if ($action === 'delete_message') {
    $id = (string)($_POST['id'] ?? '');
    $messages = file_exists(MESSAGES_FILE) ? json_decode((string)file_get_contents(MESSAGES_FILE), true) ?: [] : [];
    
    $filtered = array_values(array_filter($messages, fn($m) => $m['id'] !== $id));
    @file_put_contents(MESSAGES_FILE, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'message' => 'Mesaj silindi.']);
    exit;
}

// =========================================================================
// 3. İÇERİK CMS & VERSİYON ROLLBACK
// =========================================================================

// İçerik Getir
if ($action === 'get_content') {
    $targetLang = ($_GET['lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) : [];
    echo json_encode(['success' => true, 'lang' => $targetLang, 'data' => $content]);
    exit;
}

// İçerik Kaydet (Otomatik Snapshot Alarak)
if ($action === 'save_content') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;

    $rawContent = $_POST['content_json'] ?? '';
    $decoded = json_decode($rawContent, true);

    if (!is_array($decoded)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz veri formatı.']);
        exit;
    }

    // Yedek / Snapshot al
    createContentSnapshot($targetLang, 'İçerik güncellemesi');

    $result = @file_put_contents($targetFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => $t['saved_success']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'İçerik dosyasına yazılamadı.']);
    }
    exit;
}

// Taslak Olarak Kaydet (Draft)
if ($action === 'save_draft') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $draftFile = DATA_DIR . '/content_' . $targetLang . '_draft.json';

    $rawContent = $_POST['content_json'] ?? '';
    $decoded = json_decode($rawContent, true);

    if (!is_array($decoded)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz veri formatı.']);
        exit;
    }

    $result = @file_put_contents($draftFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => $result !== false, 'message' => 'Taslak kaydedildi. ?preview=1 ile önizleyebilirsiniz.']);
    exit;
}

// Taslağı Canlıya Al (Publish)
if ($action === 'publish_draft') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $draftFile = DATA_DIR . '/content_' . $targetLang . '_draft.json';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;

    if (!file_exists($draftFile)) {
        echo json_encode(['success' => false, 'message' => 'Yayınlanacak taslak bulunamadı.']);
        exit;
    }

    createContentSnapshot($targetLang, 'Taslak yayına alındı');
    @copy($draftFile, $targetFile);
    @unlink($draftFile);
    echo json_encode(['success' => true, 'message' => 'Taslak başarıyla yayına alındı!']);
    exit;
}

// Yedekleri / Snapshot Listesini Getir
if ($action === 'list_backups') {
    $targetLang = ($_GET['lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $files = glob(BACKUPS_DIR . '/content_' . $targetLang . '_*.json') ?: [];
    
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

    $backups = [];
    foreach ($files as $f) {
        $name = basename($f);
        // format: content_tr_YYYYMMDD_HHMMSS.json
        preg_match('/content_[a-z]+_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})\.json/', $name, $matches);
        $formattedDate = $matches ? "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}" : date('Y-m-d H:i:s', filemtime($f));

        $backups[] = [
            'filename' => $name,
            'date' => $formattedDate,
            'size' => round(filesize($f) / 1024, 1) . ' KB',
        ];
    }

    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

// Sürüme Geri Dön (Rollback)
if ($action === 'restore_backup') {
    $filename = basename((string)($_POST['filename'] ?? ''));
    $backupPath = BACKUPS_DIR . '/' . $filename;

    if (!file_exists($backupPath)) {
        echo json_encode(['success' => false, 'message' => 'Yedek dosyası bulunamadı.']);
        exit;
    }

    $isEn = str_contains($filename, 'content_en_');
    $targetLang = $isEn ? 'en' : 'tr';
    $targetFile = $isEn ? CONTENT_EN_FILE : CONTENT_TR_FILE;

    // Geri dönmeden önce de mevcut halin bir kopyasını al
    createContentSnapshot($targetLang, 'Geri alma öncesi otomatik snapshot');

    $result = @copy($backupPath, $targetFile);
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Sürüm başarıyla geri yüklendi ve canlıya alındı!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sürüm geri yüklenirken hata oluştu.']);
    }
    exit;
}

// =========================================================================
// 4. ÜRÜN YÖNETİMİ (PRODUCTS CRUD)
// =========================================================================

if ($action === 'save_product') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;

    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) ?: [] : [];
    $productList = $content['products']['list'] ?? [];

    $prodJson = $_POST['product_json'] ?? '';
    $productData = json_decode($prodJson, true);

    if (!is_array($productData) || empty($productData['name'])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz ürün verisi.']);
        exit;
    }

    $id = trim((string)($productData['id'] ?? ''));
    if (empty($id)) {
        $id = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $productData['name']), '-'));
        $productData['id'] = $id;
    }
    if (empty($productData['slug'])) {
        $productData['slug'] = $id;
    }

    // Varsa güncelle, yoksa ekle
    $foundIndex = -1;
    foreach ($productList as $idx => $p) {
        if ($p['id'] === $id) {
            $foundIndex = $idx;
            break;
        }
    }

    if ($foundIndex >= 0) {
        $productList[$foundIndex] = $productData;
    } else {
        $productList[] = $productData;
    }

    // Sıralamaya göre diz
    usort($productList, fn($a, $b) => ($a['sort_order'] ?? 99) <=> ($b['sort_order'] ?? 99));

    $content['products']['list'] = $productList;

    createContentSnapshot($targetLang, 'Ürün kaydedildi: ' . $productData['name']);
    @file_put_contents($targetFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo json_encode(['success' => true, 'message' => 'Ürün başarıyla kaydedildi!', 'product' => $productData]);
    exit;
}

if ($action === 'delete_product') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    $id = (string)($_POST['id'] ?? '');

    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) ?: [] : [];
    $productList = $content['products']['list'] ?? [];

    $filtered = array_values(array_filter($productList, fn($p) => $p['id'] !== $id));
    $content['products']['list'] = $filtered;

    createContentSnapshot($targetLang, 'Ürün silindi: ' . $id);
    @file_put_contents($targetFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo json_encode(['success' => true, 'message' => 'Ürün başarıyla silindi.']);
    exit;
}

// =========================================================================
// 5. ÇÖZÜMLER & YAZILIMLAR & ÖZEL SAYFALAR (CRUD)
// =========================================================================

// Çözüm Kaydet
if ($action === 'save_solution') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) ?: [] : [];

    $data = json_decode($_POST['data_json'] ?? '', true);
    if (!is_array($data) || empty($data['title'])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz çözüm verisi.']);
        exit;
    }

    $id = trim((string)($data['id'] ?? ''));
    if (empty($id)) $id = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['title']), '-'));
    $data['id'] = $id;
    if (empty($data['slug'])) $data['slug'] = $id;

    $list = $content['solutions']['list'] ?? [];
    $found = false;
    foreach ($list as &$item) {
        if ($item['id'] === $id) {
            $item = $data;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) $list[] = $data;

    $content['solutions']['list'] = $list;
    createContentSnapshot($targetLang, 'Çözüm kaydedildi: ' . $data['title']);
    @file_put_contents($targetFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'message' => 'Çözüm başarıyla kaydedildi!']);
    exit;
}

// Yazılım Kaydet
if ($action === 'save_software') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) ?: [] : [];

    $data = json_decode($_POST['data_json'] ?? '', true);
    if (!is_array($data) || empty($data['title'])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz yazılım verisi.']);
        exit;
    }

    $id = trim((string)($data['id'] ?? ''));
    if (empty($id)) $id = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['title']), '-'));
    $data['id'] = $id;
    if (empty($data['slug'])) $data['slug'] = $id;

    $list = $content['software']['list'] ?? [];
    $found = false;
    foreach ($list as &$item) {
        if ($item['id'] === $id) {
            $item = $data;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) $list[] = $data;

    $content['software']['list'] = $list;
    createContentSnapshot($targetLang, 'Yazılım kaydedildi: ' . $data['title']);
    @file_put_contents($targetFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'message' => 'Yazılım başarıyla kaydedildi!']);
    exit;
}

// Özel Sayfa Kaydet
if ($action === 'save_page') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) ?: [] : [];

    $data = json_decode($_POST['data_json'] ?? '', true);
    if (!is_array($data) || empty($data['title'])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz sayfa verisi.']);
        exit;
    }

    $id = trim((string)($data['id'] ?? ''));
    if (empty($id)) $id = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['title']), '-'));
    $data['id'] = $id;
    if (empty($data['slug'])) $data['slug'] = $id;

    $list = $content['custom_pages'] ?? [];
    $found = false;
    foreach ($list as &$item) {
        if ($item['id'] === $id) {
            $item = $data;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) $list[] = $data;

    $content['custom_pages'] = $list;
    createContentSnapshot($targetLang, 'Özel sayfa kaydedildi: ' . $data['title']);
    @file_put_contents($targetFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'message' => 'Sayfa başarıyla kaydedildi!']);
    exit;
}

// Menü Kaydet
if ($action === 'save_menu') {
    $targetLang = ($_POST['target_lang'] ?? 'tr') === 'en' ? 'en' : 'tr';
    $targetFile = $targetLang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    $content = file_exists($targetFile) ? json_decode((string)file_get_contents($targetFile), true) ?: [] : [];

    $menu = json_decode($_POST['menu_json'] ?? '', true);
    if (!is_array($menu)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz menü verisi.']);
        exit;
    }

    $content['menu'] = $menu;
    createContentSnapshot($targetLang, 'Menü güncellendi');
    @file_put_contents($targetFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'message' => 'Menü yapısı başarıyla kaydedildi!']);
    exit;
}

// =========================================================================
// 6. MEDYA KÜTÜPHANESİ (IMAGE UPLOAD & GALLERY)
// =========================================================================

// Medya Listele
if ($action === 'list_media') {
    $files = glob(MEDIA_DIR . '/*.{jpg,jpeg,png,webp,svg,gif}', GLOB_BRACE) ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

    $media = [];
    foreach ($files as $f) {
        $name = basename($f);
        $media[] = [
            'name' => $name,
            'url' => '/uploads/images/' . $name,
            'size' => round(filesize($f) / 1024, 1) . ' KB',
            'date' => date('Y-m-d H:i', filemtime($f)),
        ];
    }
    echo json_encode(['success' => true, 'media' => $media]);
    exit;
}

// Medya Yükle
if ($action === 'upload_media') {
    if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Görsel dosyası yüklenemedi.']);
        exit;
    }

    $file = $_FILES['image_file'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['success' => false, 'message' => 'Desteklenmeyen dosya türü. (Yalnızca JPG, PNG, WEBP, SVG, GIF)']);
        exit;
    }

    if ($file['size'] > 15 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Dosya boyutu 15 MB sınırını aşıyor.']);
        exit;
    }

    $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo((string)$file['name'], PATHINFO_FILENAME));
    $finalName = $cleanName . '_' . time() . '.' . $ext;
    $targetPath = MEDIA_DIR . '/' . $finalName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => true,
            'message' => 'Görsel başarıyla yüklendi!',
            'url' => '/uploads/images/' . $finalName,
            'filename' => $finalName
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dosya taşınamadı. İzinleri kontrol ediniz.']);
    }
    exit;
}

// Medya Sil
if ($action === 'delete_media') {
    $filename = basename((string)($_POST['filename'] ?? ''));
    $targetPath = MEDIA_DIR . '/' . $filename;

    if (file_exists($targetPath)) {
        @unlink($targetPath);
        echo json_encode(['success' => true, 'message' => 'Görsel başarıyla silindi.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Görsel bulunamadı.']);
    }
    exit;
}

// =========================================================================
// 7. DOKÜMAN & PDF YÖNETİMİ
// =========================================================================

if ($action === 'get_doc_list') {
    $list = [];
    foreach ($docCatalog as $key => $info) {
        $trFile = $info['tr'];
        $enFile = $info['en'];
        $trPath = DOCS_DIR . '/' . $trFile;
        $enPath = DOCS_DIR . '/' . $enFile;

        $list[] = [
            'key' => $key,
            'name' => $info['name'],
            'tr' => [
                'filename' => $trFile,
                'exists' => file_exists($trPath),
                'size' => file_exists($trPath) ? round(filesize($trPath) / 1024, 1) . ' KB' : 'Yok',
                'url' => '/docs/' . $trFile,
                'updated' => file_exists($trPath) ? date('Y-m-d H:i', filemtime($trPath)) : '-',
            ],
            'en' => [
                'filename' => $enFile,
                'exists' => file_exists($enPath),
                'size' => file_exists($enPath) ? round(filesize($enPath) / 1024, 1) . ' KB' : 'None',
                'url' => '/docs/' . $enFile,
                'updated' => file_exists($enPath) ? date('Y-m-d H:i', filemtime($enPath)) : '-',
            ]
        ];
    }
    echo json_encode(['success' => true, 'docs' => $list]);
    exit;
}

if ($action === 'upload_pdf') {
    if (empty($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Dosya yüklenemedi veya seçim yapılmadı.']);
        exit;
    }

    $file = $_FILES['pdf_file'];
    $docKey = (string)($_POST['doc_key'] ?? '');
    $docLang = (string)($_POST['doc_lang'] ?? 'tr');

    if (!isset($docCatalog[$docKey])) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz doküman kategorisi.']);
        exit;
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        echo json_encode(['success' => false, 'message' => 'Yalnızca PDF dosyaları (.pdf) yüklenebilir.']);
        exit;
    }

    if ($file['size'] > 30 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'PDF dosyası 30 MB sınırından büyük olamaz.']);
        exit;
    }

    $targetFileName = $docCatalog[$docKey][$docLang] ?? null;
    if (!$targetFileName) {
        echo json_encode(['success' => false, 'message' => 'Hedef dosya adı bulunamadı.']);
        exit;
    }

    $targetPath = DOCS_DIR . '/' . $targetFileName;
    if (!is_dir(DOCS_DIR)) @mkdir(DOCS_DIR, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => true,
            'message' => 'PDF başarıyla yüklendi ve canlı sitede güncellendi!',
            'filename' => $targetFileName,
            'url' => '/docs/' . $targetFileName . '?v=' . time(),
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'PDF sunucuya kaydedilemedi. Klasör izinlerini kontrol ediniz.']);
    }
    exit;
}

// Bilinmeyen eylem
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Geçersiz işlem parametresi.']);
