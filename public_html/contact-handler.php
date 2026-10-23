<?php
/**
 * OEM Vision — Contact / Catalog form handler
 * SMTP via mail.oemvision.com.tr (form@oemvision.com.tr)
 * Recipient: oktay@oemvision.com.tr
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

// ----- Config -----
const SMTP_HOST = 'mail.oemvision.com.tr';
const SMTP_PORT = 587;
const SMTP_USER = 'form@oemvision.com.tr';
const SMTP_PASS = '4kCttyKNFkbBbGCvYacH';
const FROM_EMAIL = 'form@oemvision.com.tr';
const FROM_NAME  = 'OEM Vision Form';
const DEFAULT_TO_EMAIL = 'oktay@oemvision.com.tr';
const DEFAULT_TO_NAME  = 'Oktay';

// Panel ayarlarından dinamik alıcı e-postası kontrolü
$dynToEmail = DEFAULT_TO_EMAIL;
$outsideData = dirname(__DIR__) . '/data';
$dataDir = is_dir($outsideData) ? $outsideData : __DIR__ . '/data';
$settingsPath = $dataDir . '/settings.json';
if (file_exists($settingsPath)) {
    $rawSettings = @file_get_contents($settingsPath);
    if ($rawSettings) {
        $parsed = json_decode($rawSettings, true);
        if (!empty($parsed['general']['notification_email']) && filter_var($parsed['general']['notification_email'], FILTER_VALIDATE_EMAIL)) {
            $dynToEmail = trim($parsed['general']['notification_email']);
        }
    }
}

$toEmail = $dynToEmail;
$toName  = DEFAULT_TO_NAME;

// ----- Only allow POST -----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Method not allowed');
}

// ----- Honeypot (spam protection) -----
if (!empty($_POST['_honey'])) {
    // pretend it succeeded
    redirectSuccess($_POST['lang'] ?? 'tr');
}

// ----- Helpers -----
function clean(string $s, int $max = 2000): string {
    $s = trim($s);
    $s = strip_tags($s);
    if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
    return $s;
}

function buildReturnUrl(string $lang, string $param): string {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer) {
        // strip any existing query/fragment, keep path
        $parts = parse_url($referer);
        $host = $parts['host'] ?? '';
        // only trust same-host referrers
        if ($host === ($_SERVER['HTTP_HOST'] ?? '')) {
            $path = $parts['path'] ?? '/';
            return $path . (str_contains($path, '?') ? '&' : '?') . $param;
        }
    }
    $base = $lang === 'en' ? '/landing/en/' : '/landing/tr/';
    return $base . '?' . $param;
}

function redirectSuccess(string $lang): void {
    header('Location: ' . buildReturnUrl($lang, 'sent=1'));
    exit;
}

function redirectError(string $lang, string $code = 'error'): void {
    header('Location: ' . buildReturnUrl($lang, 'err=' . urlencode($code)));
    exit;
}

// ----- Inputs -----
$lang     = clean((string)($_POST['lang']     ?? 'tr'), 4);
$name     = clean((string)($_POST['name']     ?? ''), 200);
$company  = clean((string)($_POST['company']  ?? ''), 200);
$email    = clean((string)($_POST['email']    ?? ''), 200);
$phone    = clean((string)($_POST['phone']    ?? ''), 60);
$country  = clean((string)($_POST['country']  ?? ''), 100);
$message  = clean((string)($_POST['message']  ?? ''), 4000);
$subject  = clean((string)($_POST['_subject'] ?? 'OEM Vision — Form'), 200);
$source   = clean((string)($_SERVER['HTTP_REFERER'] ?? ''), 400);

$interestsRaw = $_POST['interests'] ?? [];
if (!is_array($interestsRaw)) $interestsRaw = [$interestsRaw];
$interests = array_map(fn($v) => clean((string)$v, 100), $interestsRaw);
$interestsStr = $interests ? implode(', ', $interests) : '-';

// ----- Validation -----
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectError($lang, 'invalid');
}

// ----- Build email body -----
$labelMap = $lang === 'en' ? [
    'name' => 'Name', 'company' => 'Company', 'email' => 'Email',
    'phone' => 'Phone', 'country' => 'Country', 'interests' => 'Interests',
    'message' => 'Message', 'source' => 'Source page', 'time' => 'Submitted at',
] : [
    'name' => 'Ad Soyad', 'company' => 'Şirket', 'email' => 'E-posta',
    'phone' => 'Telefon', 'country' => 'Ülke', 'interests' => 'İlgi alanları',
    'message' => 'Mesaj', 'source' => 'Geldiği sayfa', 'time' => 'Gönderim zamanı',
];

$rows = [
    [$labelMap['name'], $name],
    [$labelMap['company'], $company ?: '-'],
    [$labelMap['email'], $email],
    [$labelMap['phone'], $phone ?: '-'],
    [$labelMap['country'], $country ?: '-'],
    [$labelMap['interests'], $interestsStr],
    [$labelMap['message'], $message ?: '-'],
    [$labelMap['source'], $source ?: '-'],
    [$labelMap['time'], date('Y-m-d H:i:s')],
];

$rowsHtml = '';
foreach ($rows as [$k, $v]) {
    $rowsHtml .= '<tr>'
        . '<td style="padding:8px 12px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:600;width:200px;vertical-align:top">'
        . htmlspecialchars($k, ENT_QUOTES | ENT_HTML5)
        . '</td>'
        . '<td style="padding:8px 12px;border:1px solid #e5e7eb;white-space:pre-wrap;vertical-align:top">'
        . nl2br(htmlspecialchars($v, ENT_QUOTES | ENT_HTML5))
        . '</td>'
        . '</tr>';
}

$html = '<!DOCTYPE html><html><body style="font-family:Arial,Helvetica,sans-serif;color:#111">'
    . '<h2 style="color:#16a34a;margin:0 0 8px">' . htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5) . '</h2>'
    . '<p style="color:#666;margin:0 0 16px">oemvision.com.tr</p>'
    . '<table style="border-collapse:collapse;font-size:14px;width:100%;max-width:680px">'
    . $rowsHtml
    . '</table>'
    . '</body></html>';

$plain = '';
foreach ($rows as [$k, $v]) {
    $plain .= $k . ":\n" . $v . "\n\n";
}

// ----- Save message to panel data/messages.json -----
$messagesFile = $dataDir . '/messages.json';
$messages = [];

if (file_exists($messagesFile)) {
    $raw = @file_get_contents($messagesFile);
    if ($raw) {
        $messages = json_decode($raw, true) ?: [];
    }
}
$newMsg = [
    'id'        => uniqid('msg_', true),
    'date'      => date('Y-m-d H:i:s'),
    'type'      => (stripos($subject, 'teklif') !== false || stripos($subject, 'quote') !== false || stripos($subject, 'katalog') !== false) ? 'quote' : 'contact',
    'name'      => $name,
    'company'   => $company,
    'email'     => $email,
    'phone'     => $phone,
    'country'   => $country,
    'interests' => $interestsStr,
    'subject'   => $subject,
    'message'   => $message,
    'source'    => $source,
    'status'    => 'unread',
    'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
];
array_unshift($messages, $newMsg);
if (count($messages) > 1000) {
    $messages = array_slice($messages, 0, 1000);
}
@file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ----- Send via SMTP -----
$mailer = new PHPMailer(true);
$mailSuccess = true;
try {
    $mailer->CharSet = 'UTF-8';
    $mailer->isSMTP();
    $mailer->Host       = SMTP_HOST;
    $mailer->Port       = SMTP_PORT;
    $mailer->SMTPAuth   = true;
    $mailer->Username   = SMTP_USER;
    $mailer->Password   = SMTP_PASS;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587
    $mailer->Timeout    = 20;

    $mailer->setFrom(FROM_EMAIL, FROM_NAME);
    $mailer->addAddress($toEmail, $toName);
    $mailer->addReplyTo($email, $name);

    $mailer->isHTML(true);
    $mailer->Subject = $subject . ' [' . strtoupper($lang) . ']';
    $mailer->Body    = $html;
    $mailer->AltBody = $plain;

    $mailer->send();
} catch (Exception $e) {
    error_log('Mail error: ' . $mailer->ErrorInfo);
    $mailSuccess = false;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'mailSent' => $mailSuccess,
        'message' => $lang === 'en' ? 'Thank you! Your message has been received.' : 'Teşekkürler! Mesajınız başarıyla iletildi.'
    ]);
    exit;
}

if (!$mailSuccess) {
    redirectError($lang, 'mail');
}

redirectSuccess($lang);

