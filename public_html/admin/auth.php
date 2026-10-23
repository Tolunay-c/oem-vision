<?php
/**
 * OEM Vision — Authentication Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

function respond(bool $success, string $message = '', bool $isAjax = true): void {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    if ($success) {
        header('Location: index.php');
    } else {
        header('Location: index.php?err=' . urlencode($message));
    }
    exit;
}

if ($action === 'login') {
    $token = $_POST['csrf_token'] ?? '';
    
    // CSRF Doğrulama
    if (!empty($_SESSION['csrf_token']) && !verifyCsrfToken($token)) {
        respond(false, 'Geçersiz güvenlik belirteci (CSRF). Lütfen sayfayı yenileyiniz.', $isAjax);
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        respond(false, $t['invalid_credentials'] ?? 'Kullanıcı adı veya şifre boş bırakılamaz.', $isAjax);
    }

    $authData = [];
    if (file_exists(AUTH_FILE)) {
        $authData = json_decode((string)file_get_contents(AUTH_FILE), true) ?: [];
    }

    $validUser = $authData['username'] ?? 'admin';
    $hash      = $authData['password_hash'] ?? '';

    // Varsayılan parola kontrolü
    if (empty($hash)) {
        $hash = password_hash('oemvision2026!', PASSWORD_BCRYPT);
    }

    if ($username === $validUser && password_verify($password, $hash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $validUser;
        $_SESSION['admin_last_activity'] = time();

        // Oturum sabitleme saldırısına karşı ID yenile
        session_regenerate_id(true);

        respond(true, 'Giriş başarılı', $isAjax);
    }

    respond(false, $t['invalid_credentials'] ?? 'Hatalı kullanıcı adı veya parola.', $isAjax);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

respond(false, 'Geçersiz işlem', $isAjax);
