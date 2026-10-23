<?php
/**
 * OEM Vision — Admin Panel Configuration & l10n
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$outsideData = dirname(__DIR__, 2) . '/data';
if (is_dir($outsideData)) {
    define('DATA_DIR', $outsideData);
} else {
    define('DATA_DIR', __DIR__ . '/../data');
}

define('SETTINGS_FILE', DATA_DIR . '/settings.json');
define('CONTENT_TR_FILE', DATA_DIR . '/content_tr.json');
define('CONTENT_EN_FILE', DATA_DIR . '/content_en.json');
define('MESSAGES_FILE', DATA_DIR . '/messages.json');
define('BACKUPS_DIR', DATA_DIR . '/backups');
define('AUTH_FILE', DATA_DIR . '/admin_auth.json');
define('DOCS_DIR', __DIR__ . '/../docs');
define('MEDIA_DIR', __DIR__ . '/../uploads/images');

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
if (!is_dir(BACKUPS_DIR)) @mkdir(BACKUPS_DIR, 0755, true);
if (!is_dir(MEDIA_DIR)) @mkdir(MEDIA_DIR, 0755, true);


// Resmi Doküman Kataloğu (Her ürün ve çözüm için TR/EN eşleşmesi)
$docCatalog = [
    'brief' => [
        'name' => 'Genel Teknik Brifing',
        'tr'   => 'OEM-Vision-Technical-Brief-TR.pdf',
        'en'   => 'OEM-Vision-Technical-Brief-EN.pdf',
    ],
    'tuna' => [
        'name' => 'TUNA LogScan Ürün Föyü',
        'tr'   => 'TUNA-LogScan-Teknik-Foy-TR.pdf',
        'en'   => 'TUNA-LogScan-Datasheet-EN.pdf',
    ],
    'altay' => [
        'name' => 'ALTAY LogScan Ürün Föyü',
        'tr'   => 'ALTAY-LogScan-Teknik-Foy-TR.pdf',
        'en'   => 'ALTAY-LogScan-Datasheet-EN.pdf',
    ],
    'arasL' => [
        'name' => 'ARAS-L BoardScan Ürün Föyü',
        'tr'   => 'ARAS-L-BoardScan-Teknik-Foy-TR.pdf',
        'en'   => 'ARAS-L-BoardScan-Datasheet-EN.pdf',
    ],
    'arasT' => [
        'name' => 'ARAS-T BoardScan Ürün Föyü',
        'tr'   => 'ARAS-T-BoardScan-Teknik-Foy-TR.pdf',
        'en'   => 'ARAS-T-BoardScan-Datasheet-EN.pdf',
    ],
    'logyard' => [
        'name' => 'Tomruk Sahası Çözüm Föyü',
        'tr'   => 'Logyard-Tomruk-Sahasi-Cozum-TR.pdf',
        'en'   => 'Logyard-Solution-Brief-EN.pdf',
    ],
    'primary' => [
        'name' => 'Birincil Kesim Çözüm Föyü',
        'tr'   => 'Primary-Birincil-Kesim-Cozum-TR.pdf',
        'en'   => 'Primary-Breakdown-Solution-Brief-EN.pdf',
    ],
    'secondary' => [
        'name' => 'İkincil Kesim Çözüm Föyü',
        'tr'   => 'Secondary-Ikincil-Kesim-Cozum-TR.pdf',
        'en'   => 'Secondary-Breakdown-Solution-Brief-EN.pdf',
    ],
    'woodscan' => [
        'name' => 'WoodScan Yazılım Föyü',
        'tr'   => 'WoodScan-Yazilim-Foyu-TR.pdf',
        'en'   => 'WoodScan-Software-Datasheet-EN.pdf',
    ],
    'optisoft' => [
        'name' => 'Optisoft Optimizer Yazılım Föyü',
        'tr'   => 'Optisoft-Optimizer-Yazilim-Foyu-TR.pdf',
        'en'   => 'Optisoft-Optimizer-Software-Datasheet-EN.pdf',
    ],
];

// Varsayılan yönetici hesabı oluşturma (Dosya yoksa)
if (!file_exists(AUTH_FILE)) {
    $defaultAuth = [
        'username' => 'admin',
        // Varsayılan parola: oemvision2026!
        'password_hash' => password_hash('oemvision2026!', PASSWORD_BCRYPT),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    @file_put_contents(AUTH_FILE, json_encode($defaultAuth, JSON_PRETTY_PRINT));
}

// l10n (Dil Seçimi)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'], true)) {
    $_SESSION['admin_lang'] = $_GET['lang'];
}
$adminLang = $_SESSION['admin_lang'] ?? 'tr';

$adminTranslations = [
    'tr' => [
        'panel_title' => 'OEM Vision Yönetim Paneli',
        'login_title' => 'Yönetici Girişi',
        'login_subtitle' => 'Devam etmek için lütfen giriş yapınız',
        'username' => 'Kullanıcı Adı',
        'password' => 'Parola',
        'login_btn' => 'Giriş Yap',
        'logout' => 'Çıkış Yap',
        'view_site' => 'Canlı Siteyi Gör',
        'preview_site' => 'Canlı Önizle',
        'dashboard' => 'Genel Bakış',
        'messages' => 'Gelen Formlar & Teklifler',
        'products' => 'Ürün Yönetimi',
        'solutions' => 'Çözümler',
        'software' => 'Yazılımlar',
        'pages' => 'Sayfa CMS',
        'menu' => 'Menü Yönetimi',
        'media' => 'Medya Kütüphanesi',
        'documents' => 'PDF & Dokümanlar',
        'backups' => 'Sürüm Geçmişi & Geri Alma',
        'settings' => 'Firma & Ayarlar',
        'security' => 'Güvenlik & Şifre',
        'save' => 'Değişiklikleri Kaydet',
        'saving' => 'Kaydediliyor...',
        'saved_success' => 'Başarıyla kaydedildi ve canlı siteye uygulandı!',
        'error_occurred' => 'Bir hata oluştu. Lütfen tekrar deneyiniz.',
        'invalid_credentials' => 'Hatalı kullanıcı adı veya parola.',
        'company_info' => 'Firma Temel Bilgileri',
        'company_name' => 'Firma Adı',
        'company_title' => 'Resmi Ticari Unvan',
        'contact_numbers' => 'İletişim Numaraları',
        'phone_landline' => 'Sabit Telefon',
        'phone_mobile' => 'GSM / WhatsApp',
        'emails' => 'E-Posta Adresleri',
        'general_email' => 'Genel İletişim E-Postası (Sitede Görünen)',
        'notification_email' => 'Form Bildirim E-Postası (Mesajların Gideceği Adres)',
        'address_location' => 'Adres ve Konum',
        'address_text' => 'Açık Adres',
        'google_maps' => 'Google Haritalar Linki',
        'working_hours' => 'Çalışma Saatleri',
        'social_media' => 'Sosyal Medya Hesapları',
        'hero_section' => 'Ana Sayfa (Hero Bölümü)',
        'hero_badge' => 'Üst Rozet Metni',
        'hero_title' => 'Ana Başlık (1. Satır)',
        'hero_title_highlight' => 'Ana Başlık Vurgusu (2. Satır - Yeşil)',
        'hero_subtitle' => 'Ana Açıklama',
        'stats_section' => 'İstatistikler',
        'products_section' => 'Ürünler',
        'solutions_section' => 'Çözümler',
        'cta_section' => 'Teklif ve İletişim Çağrısı',
        'footer_section' => 'Alt Bilgi (Footer)',
        'current_pdf' => 'Mevcut Teknik PDF',
        'upload_new_pdf' => 'Yeni PDF Yükle',
        'pdf_note' => 'Yalnızca .pdf dosyaları kabul edilir (Maksimum 30 MB).',
        'change_password_title' => 'Panel Giriş Şifresini Değiştir',
        'current_password' => 'Mevcut Parola',
        'new_password' => 'Yeni Parola (En az 8 karakter)',
        'new_password_confirm' => 'Yeni Parola Tekrarı',
        'password_changed' => 'Parolanız başarıyla güncellendi!',
        'password_mismatch' => 'Yeni parolalar birbiriyle eşleşmiyor.',
        'password_too_short' => 'Yeni parola en az 8 karakter olmalıdır.',
        'last_updated' => 'Son Güncelleme',
        'active_server' => 'Sunucu Durumu',
        'online' => 'Canlı ve Aktif',
        'add_new' => 'Yeni Ekle',
        'edit' => 'Düzenle',
        'delete' => 'Sil',
        'status_active' => 'Yayında (Aktif)',
        'status_hidden' => 'Gizli (Pasif)',
        'rollback_btn' => 'Bu Sürüme Geri Dön',
        'rollback_confirm' => 'Bu sürüme geri dönmek istediğinizden emin misiniz? Mevcut içerikler bu sürümle değiştirilecektir.',
    ],
    'en' => [
        'panel_title' => 'OEM Vision Admin Panel',
        'login_title' => 'Admin Login',
        'login_subtitle' => 'Please enter your credentials to continue',
        'username' => 'Username',
        'password' => 'Password',
        'login_btn' => 'Log In',
        'logout' => 'Log Out',
        'view_site' => 'View Live Site',
        'preview_site' => 'Live Preview',
        'dashboard' => 'Dashboard',
        'messages' => 'Inbox & Inquiries',
        'products' => 'Products Management',
        'solutions' => 'Solutions',
        'software' => 'Software Suite',
        'pages' => 'Pages CMS',
        'menu' => 'Menu & Navigation',
        'media' => 'Media Library',
        'documents' => 'Docs & PDF',
        'backups' => 'Version History & Rollback',
        'settings' => 'Company & Settings',
        'security' => 'Security & Password',
        'save' => 'Save Changes',
        'saving' => 'Saving...',
        'saved_success' => 'Saved successfully and applied to live site!',
        'error_occurred' => 'An error occurred. Please try again.',
        'invalid_credentials' => 'Invalid username or password.',
        'company_info' => 'Company Information',
        'company_name' => 'Company Name',
        'company_title' => 'Official Legal Title',
        'contact_numbers' => 'Contact Numbers',
        'phone_landline' => 'Landline Phone',
        'phone_mobile' => 'Mobile / WhatsApp',
        'emails' => 'Email Addresses',
        'general_email' => 'General Contact Email (Displayed on site)',
        'notification_email' => 'Form Notification Email (Recipient for leads)',
        'address_location' => 'Address & Location',
        'address_text' => 'Full Address',
        'google_maps' => 'Google Maps Link',
        'working_hours' => 'Working Hours',
        'social_media' => 'Social Media Links',
        'hero_section' => 'Home Page (Hero Section)',
        'hero_badge' => 'Top Badge Text',
        'hero_title' => 'Main Title (Line 1)',
        'hero_title_highlight' => 'Main Title Highlight (Line 2 - Green)',
        'hero_subtitle' => 'Subtitle',
        'stats_section' => 'Key Stats',
        'products_section' => 'Products',
        'solutions_section' => 'Solutions',
        'cta_section' => 'Call to Action',
        'footer_section' => 'Footer Information',
        'current_pdf' => 'Current Technical PDF',
        'upload_new_pdf' => 'Upload New PDF',
        'pdf_note' => 'Only .pdf files are accepted (Max 30 MB).',
        'change_password_title' => 'Change Admin Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password (Min 8 chars)',
        'new_password_confirm' => 'Confirm New Password',
        'password_changed' => 'Password updated successfully!',
        'password_mismatch' => 'New passwords do not match.',
        'password_too_short' => 'New password must be at least 8 characters.',
        'last_updated' => 'Last Updated',
        'active_server' => 'Server Status',
        'online' => 'Online & Active',
        'add_new' => 'Add New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'status_active' => 'Published (Active)',
        'status_hidden' => 'Hidden (Draft)',
        'rollback_btn' => 'Restore This Version',
        'rollback_confirm' => 'Are you sure you want to restore this version? Current content will be replaced.',
    ]
];

$t = $adminTranslations[$adminLang];

// CSRF Token Yönetimi
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isAuthenticated(): bool {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Otomatik Snapshot / Versiyon Yedeği Al
 */
function createContentSnapshot(string $lang, string $description = 'Güncelleme'): void {
    $src = $lang === 'en' ? CONTENT_EN_FILE : CONTENT_TR_FILE;
    if (!file_exists($src)) return;
    
    $timestamp = date('Ymd_His');
    $dest = BACKUPS_DIR . '/content_' . $lang . '_' . $timestamp . '.json';
    @copy($src, $dest);
    
    // Max 30 yedek tut, eskileri sil
    $files = glob(BACKUPS_DIR . '/content_' . $lang . '_*.json');
    if ($files && count($files) > 30) {
        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
        while (count($files) > 30) {
            $old = array_shift($files);
            @unlink($old);
        }
    }
}
