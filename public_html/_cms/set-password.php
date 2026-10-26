<?php
/**
 * Panel kullanıcısını oluşturur veya şifresini değiştirir (sadece terminalden).
 *
 *   php public_html/_cms/set-password.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/cms.php';

$file = CMS_DATA . '/admin_auth.json';
$auth = cms_read_json($file);

echo 'Kullanıcı adı [' . ($auth['username'] ?? 'admin') . ']: ';
$user = trim((string) fgets(STDIN)) ?: ($auth['username'] ?? 'admin');

system('stty -echo 2>/dev/null');
echo 'Yeni şifre (en az 10 karakter): ';
$pass = trim((string) fgets(STDIN));
echo "\nTekrar: ";
$pass2 = trim((string) fgets(STDIN));
system('stty echo 2>/dev/null');
echo "\n";

if (mb_strlen($pass) < 10) { fwrite(STDERR, "Şifre en az 10 karakter olmalı.\n"); exit(1); }
if ($pass !== $pass2)      { fwrite(STDERR, "Şifreler eşleşmiyor.\n"); exit(1); }

$auth['username'] = $user;
$auth['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
$auth['updated_at'] = date('c');
file_put_contents($file, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
echo "Kaydedildi: {$user}\n";
