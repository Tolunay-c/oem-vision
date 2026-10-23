<?php
/**
 * OEM Vision — Enterprise Management & CMS Panel
 * 
 * Full Content Control: Pages, Products, Solutions, Software, Media Library,
 * Inquiries Inbox, Dynamic Navigation, PDF Catalog & Rollback Versioning.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$loggedIn = isAuthenticated();
$csrfToken = getCsrfToken();

// Mevcut ayarları ve mesaj sayısını oku
$settings = file_exists(SETTINGS_FILE) ? json_decode((string)file_get_contents(SETTINGS_FILE), true) ?: [] : [];
$general  = $settings['general'] ?? [];
$social   = $settings['social'] ?? [];

$messages = file_exists(MESSAGES_FILE) ? json_decode((string)file_get_contents(MESSAGES_FILE), true) ?: [] : [];
$unreadMessagesCount = 0;
foreach ($messages as $m) {
    if (($m['status'] ?? '') === 'unread') $unreadMessagesCount++;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($adminLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t['panel_title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="/favicon.ico">
    <style>
        :root {
            --bg-body: #070a0b;
            --bg-sidebar: #0b1012;
            --bg-card: #0f1618;
            --bg-card-hover: #141d20;
            --border: #1a2528;
            --border-light: #243438;
            --text-main: #f3f4f6;
            --text-muted: #94a3b8;
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-glow: rgba(16, 185, 129, 0.15);
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
        }

        /* Login Screen */
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo h1 {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 1px;
        }
        .login-logo h1 span {
            color: var(--primary);
        }
        .login-logo p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* Inputs & Buttons */
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 11px 14px;
            background: #080d0e;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-main);
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .form-textarea {
            resize: vertical;
            min-height: 90px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary);
            color: #04120c;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-secondary {
            background: #141e21;
            color: var(--text-main);
            border: 1px solid var(--border-light);
        }
        .btn-secondary:hover {
            background: #1a282c;
            border-color: var(--primary);
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            background: var(--danger);
            color: #fff;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* App Layout */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 40;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-brand h2 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #fff;
        }
        .sidebar-brand h2 span {
            color: var(--primary);
        }
        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .nav-item:hover {
            color: var(--text-main);
            background: #101719;
        }
        .nav-item.active {
            color: var(--primary);
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.25);
        }
        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 700;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Top Header */
        .top-bar {
            height: 68px;
            background: #080c0d;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 30;
        }
        .top-title {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .lang-switch {
            display: flex;
            background: #11181b;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .lang-btn {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            color: var(--text-muted);
            border: none;
            background: transparent;
            text-decoration: none;
        }
        .lang-btn.active {
            background: var(--primary);
            color: #000;
        }

        /* Content Area */
        .page-content {
            padding: 28px;
            flex: 1;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }

        /* Cards & Grids */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .data-table th {
            text-align: left;
            padding: 12px 14px;
            background: #0b1113;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: var(--primary); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: var(--info); }

        /* Media Grid */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }
        .media-item {
            background: #0b1113;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .media-item:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        .media-thumb {
            width: 100%;
            height: 110px;
            object-fit: cover;
            background: #000;
        }
        .media-info {
            padding: 8px 10px;
            font-size: 11px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.8);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #090e10;
        }

        /* Toast Notifications */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        .toast-success { background: #064e3b; border: 1px solid var(--primary); color: #fff; }
        .toast-error { background: #7f1d1d; border: 1px solid var(--danger); color: #fff; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 900px) {
            .sidebar { width: 70px; }
            .sidebar-brand h2, .nav-item span { display: none; }
            .main-content { margin-left: 70px; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php if (!$loggedIn): ?>
    <!-- Giriş Ekranı -->
    <div class="login-wrap">
        <div class="login-box">
            <div class="login-logo">
                <h1>OEM <span>VISION</span></h1>
                <p><?= htmlspecialchars($t['login_subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if (!empty($_GET['err'])): ?>
                <div style="background:rgba(239, 68, 68, 0.15); border:1px solid var(--danger); color:#fff; padding:12px; border-radius:10px; font-size:13px; margin-bottom:18px; text-align:center;">
                    <?= htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="auth.php">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($t['username'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="username" class="form-input" required autocomplete="username" autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($t['password'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="password" name="password" class="form-input" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    <?= htmlspecialchars($t['login_btn'], ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>

            <script>
                const loginForm = document.getElementById('loginForm');
                if (loginForm) {
                    loginForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const btn = loginForm.querySelector('button[type="submit"]');
                        if (btn) { btn.disabled = true; btn.textContent = 'Giriş yapılıyor...'; }
                        const fd = new FormData(loginForm);
                        try {
                            const res = await fetch('auth.php', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json' },
                                body: fd
                            });
                            const data = await res.json();
                            if (data.success) {
                                window.location.href = 'index.php';
                            } else {
                                alert(data.message || 'Hatalı kullanıcı adı veya şifre.');
                                if (btn) { btn.disabled = false; btn.textContent = 'Giriş Yap'; }
                            }
                        } catch (err) {
                            loginForm.submit();
                        }
                    });
                }
            </script>
        </div>
    </div>
<?php else: ?>
    <!-- Yönetim Paneli Uygulaması -->
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h2>OEM <span>VISION</span></h2>
            </div>

            <nav class="sidebar-nav">
                <a class="nav-item active" data-tab="dashboard">
                    📊 <span><?= htmlspecialchars($t['dashboard']) ?></span>
                </a>
                <a class="nav-item" data-tab="messages">
                    💬 <span><?= htmlspecialchars($t['messages']) ?></span>
                    <?php if ($unreadMessagesCount > 0): ?>
                        <span class="nav-badge" id="sidebarUnreadBadge"><?= $unreadMessagesCount ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item" data-tab="products">
                    📦 <span><?= htmlspecialchars($t['products']) ?></span>
                </a>
                <a class="nav-item" data-tab="solutions">
                    ⚙️ <span><?= htmlspecialchars($t['solutions']) ?></span>
                </a>
                <a class="nav-item" data-tab="software">
                    💻 <span><?= htmlspecialchars($t['software']) ?></span>
                </a>
                <a class="nav-item" data-tab="pages">
                    📄 <span><?= htmlspecialchars($t['pages']) ?></span>
                </a>
                <a class="nav-item" data-tab="menu">
                    🧭 <span><?= htmlspecialchars($t['menu']) ?></span>
                </a>
                <a class="nav-item" data-tab="media">
                    🖼️ <span><?= htmlspecialchars($t['media']) ?></span>
                </a>
                <a class="nav-item" data-tab="documents">
                    📑 <span><?= htmlspecialchars($t['documents']) ?></span>
                </a>
                <a class="nav-item" data-tab="backups">
                    🕒 <span><?= htmlspecialchars($t['backups']) ?></span>
                </a>
                <a class="nav-item" data-tab="settings">
                    ⚙️ <span><?= htmlspecialchars($t['settings']) ?></span>
                </a>
                <a class="nav-item" data-tab="security">
                    🔒 <span><?= htmlspecialchars($t['security']) ?></span>
                </a>
            </nav>
        </aside>

        <!-- Main Wrapper -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="top-bar">
                <div class="top-title" id="pageTitleText"><?= htmlspecialchars($t['dashboard']) ?></div>

                <div class="top-actions">
                    <!-- Dil Seçici -->
                    <div class="lang-switch">
                        <button type="button" class="lang-btn <?= $adminLang === 'tr' ? 'active' : '' ?>" onclick="switchLang('tr')">TR</button>
                        <button type="button" class="lang-btn <?= $adminLang === 'en' ? 'active' : '' ?>" onclick="switchLang('en')">EN</button>
                    </div>

                    <!-- Canlı Önizle Butonu -->
                    <a href="/?preview=1" target="_blank" class="btn btn-secondary btn-sm" title="Taslak modunda önizle">
                        👁️ <?= htmlspecialchars($t['preview_site']) ?>
                    </a>

                    <!-- Canlı Site Butonu -->
                    <a href="/<?= $adminLang === 'en' ? 'en' : 'tr' ?>" target="_blank" class="btn btn-secondary btn-sm">
                        🌐 <?= htmlspecialchars($t['view_site']) ?>
                    </a>

                    <!-- Çıkış -->
                    <a href="auth.php?action=logout" class="btn btn-danger btn-sm">
                        <?= htmlspecialchars($t['logout']) ?>
                    </a>
                </div>
            </header>

            <!-- Pages Container -->
            <main class="page-content">

                <!-- 1. DASHBOARD -->
                <div id="tab-dashboard" class="tab-pane active">
                    <div class="grid-4" style="margin-bottom: 24px;">
                        <div class="card" style="margin-bottom:0; text-align:center;">
                            <div style="font-size:32px; font-weight:800; color:var(--primary);" id="dashUnreadCount"><?= $unreadMessagesCount ?></div>
                            <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-top:4px;">Okunmamış Form / Teklif</div>
                        </div>
                        <div class="card" style="margin-bottom:0; text-align:center;">
                            <div style="font-size:32px; font-weight:800; color:#fff;" id="dashProductCount">4</div>
                            <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-top:4px;">Aktif Ürün Sayısı</div>
                        </div>
                        <div class="card" style="margin-bottom:0; text-align:center;">
                            <div style="font-size:32px; font-weight:800; color:#fff;" id="dashSolutionCount">3</div>
                            <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-top:4px;">Çözüm Modülü</div>
                        </div>
                        <div class="card" style="margin-bottom:0; text-align:center;">
                            <div style="font-size:32px; font-weight:800; color:#fff;" id="dashBackupCount">-</div>
                            <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-top:4px;">Yedek Sürüm Geçmişi</div>
                        </div>
                    </div>

                    <!-- Hızlı İşlemler Kartı -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">⚡ Hızlı İşlem Kısayolları</div>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:12px;">
                            <button class="btn btn-primary" onclick="openProductModal()">➕ Yeni Ürün Ekle</button>
                            <button class="btn btn-secondary" onclick="openMediaUploadModal()">📷 Görsel Yükle</button>
                            <button class="btn btn-secondary" onclick="switchTab('messages')">💬 Gelen Mesajları Gör</button>
                            <button class="btn btn-secondary" onclick="switchTab('pages')">✏️ Ana Sayfa Metinlerini Düzenle</button>
                            <button class="btn btn-secondary" onclick="switchTab('backups')">🕒 Sürümleri Yönet</button>
                        </div>
                    </div>

                    <!-- Son Form Mesajları -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">📩 Son Gelen İletişim & Teklif Formları</div>
                            <button class="btn btn-secondary btn-sm" onclick="switchTab('messages')">Tümünü Gör</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Gönderen</th>
                                        <th>Şirket</th>
                                        <th>Konu / İlgilendiği Ürün</th>
                                        <th>Durum</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="dashRecentMessagesTable">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. GELEN FORMLAR & TEKLİFLER (MESSAGES) -->
                <div id="tab-messages" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">💬 Gelen Formlar & Teklif Talepleri</div>
                            <div style="display:flex; gap:10px;">
                                <button class="btn btn-secondary btn-sm" onclick="loadMessages()">🔄 Yenile</button>
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>İsim & Şirket</th>
                                        <th>E-Posta & Telefon</th>
                                        <th>Tip / Konu</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="messagesTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. ÜRÜN YÖNETİMİ -->
                <div id="tab-products" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">📦 Donanım Ürünleri Yönetimi</div>
                            <button class="btn btn-primary" onclick="openProductModal()">➕ Yeni Ürün Ekle</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Görsel</th>
                                        <th>Ürün Adı & Rozet</th>
                                        <th>Kategori</th>
                                        <th>Hız / Kapasite</th>
                                        <th>Sıra</th>
                                        <th>Yayın Durumu</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="productsTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 4. ÇÖZÜMLER -->
                <div id="tab-solutions" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">⚙️ Kereste Fabrikası Çözümleri</div>
                            <button class="btn btn-primary" onclick="openSolutionModal()">➕ Yeni Çözüm Ekle</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Çözüm Başlığı</th>
                                        <th>Alt Başlık</th>
                                        <th>Kapasite Hızı</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="solutionsTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 5. YAZILIMLAR -->
                <div id="tab-software" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">💻 Yazılım Çözümleri (WoodScan, Optisoft, WoodERP)</div>
                            <button class="btn btn-primary" onclick="openSoftwareModal()">➕ Yeni Yazılım Ekle</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Yazılım Adı</th>
                                        <th>Rozet</th>
                                        <th>Açıklama</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="softwareTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 6. SAYFA CMS -->
                <div id="tab-pages" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">📄 Ana Sayfa & Genel Metinler (CMS)</div>
                            <div style="display:flex; gap:10px;">
                                <button class="btn btn-secondary btn-sm" onclick="saveAsDraft()">📝 Taslak Olarak Kaydet</button>
                                <button class="btn btn-primary btn-sm" onclick="saveContentForm()">💾 Yayına Al</button>
                            </div>
                        </div>

                        <form id="cmsContentForm">
                            <!-- Hero Bölümü -->
                            <h3 style="font-size:15px; color:var(--primary); margin-bottom:14px;">🎯 Ana Sayfa Hero Alanı</h3>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Üst Rozet (Badge)</label>
                                    <input type="text" id="heroBadge" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Banner Görsel Yolu</label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="heroBannerImage" class="form-input">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="selectMediaForInput('heroBannerImage')">Seç</button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Ana Başlık (1. Satır)</label>
                                    <input type="text" id="heroTitle" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Vurgulu Başlık (2. Satır - Yeşil)</label>
                                    <input type="text" id="heroTitleHighlight" class="form-input">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Açıklama (Subtitle)</label>
                                <textarea id="heroSubtitle" class="form-textarea"></textarea>
                            </div>

                            <!-- 4 İstatistik Sayaç -->
                            <h3 style="font-size:15px; color:var(--primary); margin:24px 0 14px;">📈 Ana Sayfa 4'lü İstatistik Sayaçları</h3>
                            <div class="grid-4">
                                <div class="form-group">
                                    <label class="form-label">1. Değer / Etiket</label>
                                    <input type="text" id="heroStat1Value" class="form-input" style="margin-bottom:6px;">
                                    <input type="text" id="heroStat1Label" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">2. Değer / Etiket</label>
                                    <input type="text" id="heroStat2Value" class="form-input" style="margin-bottom:6px;">
                                    <input type="text" id="heroStat2Label" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">3. Değer / Etiket</label>
                                    <input type="text" id="heroStat3Value" class="form-input" style="margin-bottom:6px;">
                                    <input type="text" id="heroStat3Label" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">4. Değer / Etiket</label>
                                    <input type="text" id="heroStat4Value" class="form-input" style="margin-bottom:6px;">
                                    <input type="text" id="heroStat4Label" class="form-input">
                                </div>
                            </div>

                            <!-- CTA Bölümü -->
                            <h3 style="font-size:15px; color:var(--primary); margin:24px 0 14px;">📞 Kapanış CTA (Teklif & İletişim Çağrısı)</h3>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">CTA Başlık</label>
                                    <input type="text" id="ctaTitle" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">CTA Buton Metni</label>
                                    <input type="text" id="ctaButton" class="form-input">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">CTA Alt Açıklama</label>
                                <textarea id="ctaSubtitle" class="form-textarea"></textarea>
                            </div>

                            <button type="button" class="btn btn-primary" onclick="saveContentForm()">💾 Değişiklikleri Canlıya Kaydet</button>
                        </form>
                    </div>

                    <!-- Özel Sayfalar (Custom Pages) -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">📑 Yeni Özel Sayfalar (Hakkımızda, Sürdürülebilirlik vb.)</div>
                            <button class="btn btn-primary btn-sm" onclick="openPageModal()">➕ Yeni Sayfa Ekle</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Başlık</th>
                                        <th>URL Adresi (Slug)</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="customPagesTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 7. MENÜ & NAVİGASYON -->
                <div id="tab-menu" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">🧭 Ana Menü & Navigasyon Düzeni</div>
                            <button class="btn btn-primary btn-sm" onclick="saveMenuOrder()">💾 Menüyü Kaydet</button>
                        </div>
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Sitedeki üst menü başlıklarını düzenleyebilir, sıralayabilir veya gizleyebilirsiniz.</p>
                        <div id="menuItemsContainer">
                            <!-- AJAX ile dolacak -->
                        </div>
                    </div>
                </div>

                <!-- 8. MEDYA KÜTÜPHANESİ -->
                <div id="tab-media" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">🖼️ Medya & Görsel Kütüphanesi</div>
                            <button class="btn btn-primary btn-sm" onclick="openMediaUploadModal()">📤 Yeni Görsel Yükle</button>
                        </div>
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Ürün fotoğrafları, logolar ve banner görsellerini buradan yönetebilirsiniz. Yüklediğiniz görseller anında canlı sitede kullanılabilir.</p>
                        
                        <div id="mediaGridContainer" class="media-grid">
                            <!-- AJAX ile dolacak -->
                        </div>
                    </div>
                </div>

                <!-- 9. DOKÜMAN & PDF -->
                <div id="tab-documents" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">📑 PDF & Katalog Yönetim Merkezi</div>
                        </div>
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Ürün ve çözüm teknik föyleri ile genel brifing PDF dokümanlarını Türkçe ve İngilizce olarak ayrı ayrı güncelleyebilirsiniz.</p>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Doküman Adı</th>
                                        <th>Türkçe PDF Durumu</th>
                                        <th>İngilizce PDF Durumu</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="docListTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 10. SÜRÜM GEÇMİŞİ & GERİ ALMA (ROLLBACK) -->
                <div id="tab-backups" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">🕒 Versiyon Geçmişi & Tek Tıkla Geri Alma (Rollback)</div>
                            <button class="btn btn-secondary btn-sm" onclick="loadBackups()">🔄 Yenile</button>
                        </div>
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Her kaydetme işleminde sistem otomatik olarak güvenlik yedeği alır. Yanlışlıkla yapılan bir değişikliği tek tıkla eski haline döndürebilirsiniz.</p>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Yedek Tarihi</th>
                                        <th>Dosya Adı</th>
                                        <th>Boyut</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="backupsTableBody">
                                    <!-- AJAX ile dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 11. FİRMA & AYARLAR -->
                <div id="tab-settings" class="tab-pane">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">⚙️ Firma İletişim & Sistem Ayarları</div>
                        </div>
                        <form id="generalSettingsForm">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['company_name']) ?></label>
                                    <input type="text" name="company_name" class="form-input" value="<?= htmlspecialchars($general['company_name'] ?? 'OEM Vision') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['company_title']) ?></label>
                                    <input type="text" name="company_full_title" class="form-input" value="<?= htmlspecialchars($general['company_full_title'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['phone_landline']) ?></label>
                                    <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($general['phone'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['phone_mobile']) ?></label>
                                    <input type="text" name="gsm" class="form-input" value="<?= htmlspecialchars($general['gsm'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['general_email']) ?></label>
                                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($general['email'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['notification_email']) ?></label>
                                    <input type="email" name="notification_email" class="form-input" value="<?= htmlspecialchars($general['notification_email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= htmlspecialchars($t['address_text']) ?></label>
                                <textarea name="address" class="form-textarea"><?= htmlspecialchars($general['address'] ?? '') ?></textarea>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['working_hours']) ?></label>
                                    <input type="text" name="working_hours" class="form-input" value="<?= htmlspecialchars($general['working_hours'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?= htmlspecialchars($t['google_maps']) ?></label>
                                    <input type="text" name="google_maps_url" class="form-input" value="<?= htmlspecialchars($general['google_maps_url'] ?? '') ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">💾 Ayarları Kaydet</button>
                        </form>
                    </div>
                </div>

                <!-- 12. GÜVENLİK & ŞİFRE -->
                <div id="tab-security" class="tab-pane">
                    <div class="card" style="max-width:540px;">
                        <div class="card-header">
                            <div class="card-title">🔒 <?= htmlspecialchars($t['change_password_title']) ?></div>
                        </div>
                        <form id="changePasswordForm">
                            <!-- Accessibility & Credential Manager username context -->
                            <input type="text" name="username" value="admin" autocomplete="username" style="display:none;" aria-hidden="true" tabindex="-1">
                            <div class="form-group">
                                <label class="form-label"><?= htmlspecialchars($t['current_password']) ?></label>
                                <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= htmlspecialchars($t['new_password']) ?></label>
                                <input type="password" name="new_password" class="form-input" required autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= htmlspecialchars($t['new_password_confirm']) ?></label>
                                <input type="password" name="new_password_confirm" class="form-input" required autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary">🔐 Şifreyi Güncelle</button>
                        </form>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL: ÜRÜN EKLE / DÜZENLE -->
    <div id="productModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <div class="card-title" id="productModalTitle">Ürün Düzenle</div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('productModal')">✕</button>
            </div>
            <div class="modal-body">
                <form id="productEditForm">
                    <input type="hidden" id="prodId">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Ürün Adı *</label>
                            <input type="text" id="prodName" class="form-input" required placeholder="Örn: TUNA LogScan">
                        </div>
                        <div class="form-group">
                            <label class="form-label">URL Slug *</label>
                            <input type="text" id="prodSlug" class="form-input" required placeholder="Örn: tuna">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Rozet (Badge)</label>
                            <input type="text" id="prodBadge" class="form-input" placeholder="Örn: Çoklu Sensörlü Tomruk Tarayıcı">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <input type="text" id="prodCategory" class="form-input" placeholder="Örn: Tomruk Tarama">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Kapasite / Hız Göstergesi</label>
                            <input type="text" id="prodSpeed" class="form-input" placeholder="Örn: 180 m/dk">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sıralama No</label>
                            <input type="number" id="prodSortOrder" class="form-input" value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ana Görsel Yolu</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="prodHeroImage" class="form-input" placeholder="/images/products/tuna-hero.webp">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="selectMediaForInput('prodHeroImage')">📷 Seç</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kısa Açıklama (Kart Metni)</label>
                        <textarea id="prodShortDesc" class="form-textarea"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Detaylı Açıklama (Sayfa Metni)</label>
                        <textarea id="prodFullDesc" class="form-textarea" style="min-height:110px;"></textarea>
                    </div>

                    <!-- 4 Temel Metrik -->
                    <h4 style="font-size:14px; color:var(--primary); margin:18px 0 10px;">📊 4 Temel Metrik (Sayaçlar)</h4>
                    <div class="grid-4">
                        <div>
                            <input type="text" id="prodStat1Val" class="form-input" placeholder="Değer (180 m/dk)" style="margin-bottom:4px;">
                            <input type="text" id="prodStat1Lbl" class="form-input" placeholder="Etiket (Tarama Hızı)">
                        </div>
                        <div>
                            <input type="text" id="prodStat2Val" class="form-input" placeholder="Değer (1200 mm)" style="margin-bottom:4px;">
                            <input type="text" id="prodStat2Lbl" class="form-input" placeholder="Etiket (Maks Çap)">
                        </div>
                        <div>
                            <input type="text" id="prodStat3Val" class="form-input" placeholder="Değer (±1 mm)" style="margin-bottom:4px;">
                            <input type="text" id="prodStat3Lbl" class="form-input" placeholder="Etiket (Hassasiyet)">
                        </div>
                        <div>
                            <input type="text" id="prodStat4Val" class="form-input" placeholder="Değer (<200 ms)" style="margin-bottom:4px;">
                            <input type="text" id="prodStat4Lbl" class="form-input" placeholder="Etiket (Karar Süresi)">
                        </div>
                    </div>

                    <!-- Teknik Özellikler Tablosu (Specs) -->
                    <div style="display:flex; align-items:center; justify-content:space-between; margin:22px 0 10px;">
                        <h4 style="font-size:14px; color:var(--primary);">📋 Teknik Özellikler Tablosu (Specs)</h4>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addSpecRow()">➕ Satır Ekle</button>
                    </div>
                    <div id="specsContainer" style="display:flex; flex-direction:column; gap:8px;">
                        <!-- Dinamik satırlar -->
                    </div>

                    <div class="form-group" style="margin-top:20px;">
                        <label class="form-label">Yayın Durumu</label>
                        <select id="prodActive" class="form-select">
                            <option value="1">Yayında (Aktif)</option>
                            <option value="0">Gizli (Taslak)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveProductModal()">💾 Ürünü Kaydet</button>
            </div>
        </div>
    </div>

    <!-- MODAL: MEDYA YÜKLEME -->
    <div id="mediaUploadModal" class="modal-overlay">
        <div class="modal-box" style="max-width:480px;">
            <div class="modal-header">
                <div class="card-title">📤 Görsel Yükle</div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('mediaUploadModal')">✕</button>
            </div>
            <div class="modal-body">
                <form id="mediaUploadForm">
                    <div class="form-group">
                        <label class="form-label">Görsel Dosyası (JPG, PNG, WEBP, SVG)</label>
                        <input type="file" id="mediaFileInput" name="image_file" class="form-input" accept="image/*" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('mediaUploadModal')">İptal</button>
                <button type="button" class="btn btn-primary" onclick="uploadMediaFile()">Yükle</button>
            </div>
        </div>
    </div>

    <!-- MODAL: MEDYA SEÇİCİ (INPUT'A AKTARMA) -->
    <div id="mediaPickerModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <div class="card-title">📷 Kütüphaneden Görsel Seç</div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('mediaPickerModal')">✕</button>
            </div>
            <div class="modal-body">
                <div id="mediaPickerGrid" class="media-grid">
                    <!-- Görseller -->
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: MESAJ DETAY -->
    <div id="messageViewModal" class="modal-overlay">
        <div class="modal-box" style="max-width:600px;">
            <div class="modal-header">
                <div class="card-title">📩 Mesaj / Teklif Talebi Detayı</div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('messageViewModal')">✕</button>
            </div>
            <div class="modal-body" id="messageDetailBody">
                <!-- Mesaj bilgileri -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('messageViewModal')">Kapat</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Admin Panel JavaScript Controller -->
    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        let currentLang = '<?= $adminLang ?>';
        let activeInputTarget = null;
        let siteContent = null;

        // Toast Gösterici
        function showToast(msg, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.textContent = msg;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        // Tab Değiştirme
        function switchTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

            const targetPane = document.getElementById('tab-' + tabId);
            const targetNav = document.querySelector(`.nav-item[data-tab="${tabId}"]`);

            if (targetPane) targetPane.classList.add('active');
            if (targetNav) targetNav.classList.add('active');

            const titleEl = document.getElementById('pageTitleText');
            if (titleEl && targetNav) {
                const label = targetNav.querySelector('span');
                if (label) titleEl.textContent = label.textContent;
            }

            // Tab bazlı veri yükleme
            if (tabId === 'messages') loadMessages();
            else if (tabId === 'products') renderProductsTable();
            else if (tabId === 'solutions') renderSolutionsTable();
            else if (tabId === 'software') renderSoftwareTable();
            else if (tabId === 'media') loadMediaGrid();
            else if (tabId === 'documents') loadDocList();
            else if (tabId === 'backups') loadBackups();
            else if (tabId === 'menu') renderMenuEditor();
            else if (tabId === 'pages') loadCmsContent();
        }

        document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
            item.addEventListener('click', () => switchTab(item.getAttribute('data-tab')));
        });

        // Dil Değiştirme
        function switchLang(lang) {
            window.location.href = '?lang=' + lang;
        }

        // Modallar
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        // =========================================================================
        // VERİ YÜKLEME & CMS İŞLEMLERİ
        // =========================================================================
        async function fetchContent() {
            try {
                const res = await fetch(`api.php?action=get_content&lang=${currentLang}`);
                const data = await res.json();
                if (data.success) {
                    siteContent = data.data || {};
                    return siteContent;
                }
            } catch (e) {
                showToast('İçerik yüklenemedi', 'error');
            }
            return null;
        }

        // 1. Dashboard Yükleme
        async function initDashboard() {
            await fetchContent();
            loadMessages();
            renderProductsTable();
            renderSolutionsTable();
            renderSoftwareTable();
            loadBackups();
        }

        // 2. Mesajlar (Inbox)
        async function loadMessages() {
            try {
                const res = await fetch('api.php?action=get_messages');
                const data = await res.json();
                if (!data.success) return;

                const messages = data.messages || [];
                const tbody = document.getElementById('messagesTableBody');
                const dashRecent = document.getElementById('dashRecentMessagesTable');
                const unreadEl = document.getElementById('dashUnreadCount');
                const badgeEl = document.getElementById('sidebarUnreadBadge');

                if (unreadEl) unreadEl.textContent = data.unread_count || 0;
                if (badgeEl) {
                    badgeEl.textContent = data.unread_count || 0;
                    badgeEl.style.display = data.unread_count > 0 ? 'inline-block' : 'none';
                }

                if (tbody) tbody.innerHTML = '';
                if (dashRecent) dashRecent.innerHTML = '';

                if (messages.length === 0) {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Henüz mesaj bulunmuyor.</td></tr>';
                    if (dashRecent) dashRecent.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Henüz mesaj bulunmuyor.</td></tr>';
                    return;
                }

                messages.forEach((m, idx) => {
                    const isUnread = (m.status || '') === 'unread';
                    const badge = isUnread 
                        ? '<span class="badge badge-danger">Yeni</span>' 
                        : '<span class="badge badge-success">Okundu</span>';

                    const rowHtml = `
                        <tr>
                            <td style="font-size:12px; color:var(--text-muted);">${m.date || '-'}</td>
                            <td><strong>${escapeHtml(m.name || '-')}</strong><br><small style="color:var(--text-muted);">${escapeHtml(m.company || '-')}</small></td>
                            <td><a href="mailto:${escapeHtml(m.email)}" style="color:var(--primary);">${escapeHtml(m.email)}</a><br><small>${escapeHtml(m.phone || '-')}</small></td>
                            <td>${escapeHtml(m.subject || m.type || 'İletişim')}</td>
                            <td>${badge}</td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="viewMessageDetail('${m.id}')">Gözat</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteMessage('${m.id}')">Sil</button>
                            </td>
                        </tr>
                    `;

                    if (tbody) tbody.innerHTML += rowHtml;
                    if (dashRecent && idx < 5) {
                        dashRecent.innerHTML += `
                            <tr>
                                <td style="font-size:12px; color:var(--text-muted);">${m.date || '-'}</td>
                                <td><strong>${escapeHtml(m.name || '-')}</strong></td>
                                <td>${escapeHtml(m.company || '-')}</td>
                                <td>${escapeHtml(m.subject || '-')}</td>
                                <td>${badge}</td>
                                <td><button class="btn btn-secondary btn-sm" onclick="viewMessageDetail('${m.id}')">İncele</button></td>
                            </tr>
                        `;
                    }
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function viewMessageDetail(id) {
            const res = await fetch('api.php?action=get_messages');
            const data = await res.json();
            const m = (data.messages || []).find(x => x.id === id);
            if (!m) return;

            // Durumu okundu yap
            const fd = new FormData();
            fd.append('action', 'update_message_status');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('id', id);
            fd.append('status', 'read');
            await fetch('api.php', { method: 'POST', body: fd });
            loadMessages();

            const body = document.getElementById('messageDetailBody');
            body.innerHTML = `
                <div style="margin-bottom:12px;"><strong style="color:var(--text-muted); font-size:12px;">Gönderen:</strong><br><span style="font-size:16px; font-weight:700;">${escapeHtml(m.name)}</span> (${escapeHtml(m.company || 'Bireysel')})</div>
                <div style="margin-bottom:12px;"><strong style="color:var(--text-muted); font-size:12px;">E-Posta & Telefon:</strong><br><a href="mailto:${escapeHtml(m.email)}" style="color:var(--primary); font-weight:600;">${escapeHtml(m.email)}</a> | ${escapeHtml(m.phone || '-')}</div>
                <div style="margin-bottom:12px;"><strong style="color:var(--text-muted); font-size:12px;">Konu / İlgilendiği Ürün:</strong><br>${escapeHtml(m.subject || '-')}</div>
                <div style="margin-bottom:16px;"><strong style="color:var(--text-muted); font-size:12px;">Mesaj İçeriği:</strong><div style="background:#080d0e; padding:14px; border-radius:10px; border:1px solid var(--border); margin-top:6px; line-height:1.6; font-size:14px;">${escapeHtml(m.message || '-')}</div></div>
                <div style="font-size:11px; color:var(--text-muted);">Tarih: ${m.date} | IP: ${m.ip || '-'}</div>
            `;
            openModal('messageViewModal');
        }

        async function deleteMessage(id) {
            if (!confirm('Bu mesajı silmek istediğinizden emin misiniz?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_message');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('id', id);
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                loadMessages();
            }
        }

        // 3. Ürünler Tablosu
        function renderProductsTable() {
            if (!siteContent || !siteContent.products) return;
            const list = siteContent.products.list || [];
            const tbody = document.getElementById('productsTableBody');
            const dashCount = document.getElementById('dashProductCount');
            if (dashCount) dashCount.textContent = list.length;
            if (!tbody) return;

            tbody.innerHTML = '';
            list.forEach(p => {
                const isActive = p.active !== false;
                const statusBadge = isActive 
                    ? '<span class="badge badge-success">Yayında</span>' 
                    : '<span class="badge badge-warning">Gizli</span>';

                tbody.innerHTML += `
                    <tr>
                        <td><img src="${escapeHtml(p.hero_image || '/images/logo.png')}" onerror="this.src='/images/logo.png'" style="width:48px; height:48px; object-fit:contain; border-radius:8px; background:#000;"></td>
                        <td><strong>${escapeHtml(p.name)}</strong><br><small style="color:var(--text-muted);">${escapeHtml(p.badge || '')}</small></td>
                        <td>${escapeHtml(p.category || '-')}</td>
                        <td><strong style="color:var(--primary);">${escapeHtml(p.speed || '-')}</strong></td>
                        <td>${p.sort_order || 1}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="editProduct('${p.id}')">Düzenle</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteProduct('${p.id}')">Sil</button>
                        </td>
                    </tr>
                `;
            });
        }

        function openProductModal(prod = null) {
            document.getElementById('productEditForm').reset();
            document.getElementById('specsContainer').innerHTML = '';

            if (prod) {
                document.getElementById('productModalTitle').textContent = 'Ürünü Düzenle: ' + prod.name;
                document.getElementById('prodId').value = prod.id || '';
                document.getElementById('prodName').value = prod.name || '';
                document.getElementById('prodSlug').value = prod.slug || prod.id || '';
                document.getElementById('prodBadge').value = prod.badge || '';
                document.getElementById('prodCategory').value = prod.category || '';
                document.getElementById('prodSpeed').value = prod.speed || '';
                document.getElementById('prodSortOrder').value = prod.sort_order || 1;
                document.getElementById('prodHeroImage').value = prod.hero_image || '';
                document.getElementById('prodShortDesc').value = prod.short_desc || '';
                document.getElementById('prodFullDesc').value = prod.full_desc || '';
                document.getElementById('prodActive').value = prod.active !== false ? '1' : '0';

                // Stats
                if (Array.isArray(prod.stats)) {
                    if (prod.stats[0]) { document.getElementById('prodStat1Val').value = prod.stats[0].value || ''; document.getElementById('prodStat1Lbl').value = prod.stats[0].label || ''; }
                    if (prod.stats[1]) { document.getElementById('prodStat2Val').value = prod.stats[1].value || ''; document.getElementById('prodStat2Lbl').value = prod.stats[1].label || ''; }
                    if (prod.stats[2]) { document.getElementById('prodStat3Val').value = prod.stats[2].value || ''; document.getElementById('prodStat3Lbl').value = prod.stats[2].label || ''; }
                    if (prod.stats[3]) { document.getElementById('prodStat4Val').value = prod.stats[3].value || ''; document.getElementById('prodStat4Lbl').value = prod.stats[3].label || ''; }
                }

                // Specs
                if (Array.isArray(prod.specs)) {
                    prod.specs.forEach(s => addSpecRow(s.group, s.label, s.value));
                }
            } else {
                document.getElementById('productModalTitle').textContent = 'Yeni Ürün Ekle';
                document.getElementById('prodId').value = '';
                addSpecRow('Kapasite', 'Besleme Hızı', '180 m/dk');
                addSpecRow('Kapasite', 'Maks. Tomruk Çapı', '1200 mm');
            }

            openModal('productModal');
        }

        function editProduct(id) {
            const list = siteContent.products.list || [];
            const prod = list.find(p => p.id === id);
            if (prod) openProductModal(prod);
        }

        function addSpecRow(group = 'Genel', label = '', val = '') {
            const container = document.getElementById('specsContainer');
            const row = document.createElement('div');
            row.style.cssText = 'display:grid; grid-template-columns: 140px 1fr 1fr 40px; gap:8px; align-items:center;';
            row.innerHTML = `
                <input type="text" class="form-input spec-group" value="${escapeHtml(group)}" placeholder="Grup">
                <input type="text" class="form-input spec-label" value="${escapeHtml(label)}" placeholder="Parametre">
                <input type="text" class="form-input spec-value" value="${escapeHtml(val)}" placeholder="Değer">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
            `;
            container.appendChild(row);
        }

        async function saveProductModal() {
            const id = document.getElementById('prodId').value;
            const name = document.getElementById('prodName').value.trim();
            const slug = document.getElementById('prodSlug').value.trim();

            if (!name) { alert('Ürün adı zorunludur.'); return; }

            // Specs topla
            const specs = [];
            document.querySelectorAll('#specsContainer > div').forEach(row => {
                const g = row.querySelector('.spec-group').value.trim();
                const l = row.querySelector('.spec-label').value.trim();
                const v = row.querySelector('.spec-value').value.trim();
                if (l) specs.push({ group: g || 'Genel', label: l, value: v });
            });

            const prodData = {
                id: id || slug.toLowerCase(),
                slug: slug.toLowerCase(),
                name: name,
                badge: document.getElementById('prodBadge').value.trim(),
                category: document.getElementById('prodCategory').value.trim(),
                speed: document.getElementById('prodSpeed').value.trim(),
                sort_order: parseInt(document.getElementById('prodSortOrder').value) || 1,
                hero_image: document.getElementById('prodHeroImage').value.trim(),
                short_desc: document.getElementById('prodShortDesc').value.trim(),
                full_desc: document.getElementById('prodFullDesc').value.trim(),
                active: document.getElementById('prodActive').value === '1',
                stats: [
                    { value: document.getElementById('prodStat1Val').value, label: document.getElementById('prodStat1Lbl').value },
                    { value: document.getElementById('prodStat2Val').value, label: document.getElementById('prodStat2Lbl').value },
                    { value: document.getElementById('prodStat3Val').value, label: document.getElementById('prodStat3Lbl').value },
                    { value: document.getElementById('prodStat4Val').value, label: document.getElementById('prodStat4Lbl').value },
                ],
                specs: specs,
                pdf_tr: `/docs/${slug.toUpperCase()}-LogScan-Teknik-Foy-TR.pdf`,
                pdf_en: `/docs/${slug.toUpperCase()}-LogScan-Datasheet-EN.pdf`
            };

            const fd = new FormData();
            fd.append('action', 'save_product');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('target_lang', currentLang);
            fd.append('product_json', JSON.stringify(prodData));

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                showToast(data.message);
                closeModal('productModal');
                await fetchContent();
                renderProductsTable();
            } else {
                showToast(data.message || 'Hata oluştu', 'error');
            }
        }

        async function deleteProduct(id) {
            if (!confirm('Bu ürünü silmek istediğinizden emin misiniz?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_product');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('target_lang', currentLang);
            fd.append('id', id);

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                await fetchContent();
                renderProductsTable();
            }
        }

        // 4. Çözümler & Yazılımlar Tablosu
        function renderSolutionsTable() {
            if (!siteContent || !siteContent.solutions) return;
            const list = siteContent.solutions.list || [];
            const tbody = document.getElementById('solutionsTableBody');
            const dashCount = document.getElementById('dashSolutionCount');
            if (dashCount) dashCount.textContent = list.length;
            if (!tbody) return;

            tbody.innerHTML = '';
            list.forEach(s => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${escapeHtml(s.title)}</strong></td>
                        <td>${escapeHtml(s.subtitle || '-')}</td>
                        <td><strong style="color:var(--primary);">${escapeHtml(s.speed || '-')}</strong></td>
                        <td><span class="badge badge-success">Yayında</span></td>
                        <td><button class="btn btn-secondary btn-sm" onclick="editSolution('${s.id}')">Düzenle</button></td>
                    </tr>
                `;
            });
        }

        function renderSoftwareTable() {
            if (!siteContent || !siteContent.software) return;
            const list = siteContent.software.list || [];
            const tbody = document.getElementById('softwareTableBody');
            if (!tbody) return;

            tbody.innerHTML = '';
            list.forEach(sw => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${escapeHtml(sw.title)}</strong></td>
                        <td><span class="badge badge-info">${escapeHtml(sw.badge || '')}</span></td>
                        <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(sw.description || '')}</td>
                        <td><span class="badge badge-success">Yayında</span></td>
                        <td><button class="btn btn-secondary btn-sm" onclick="editSoftware('${sw.id}')">Düzenle</button></td>
                    </tr>
                `;
            });
        }

        // 5. CMS Metinleri Yükleme ve Kaydetme
        function loadCmsContent() {
            if (!siteContent) return;
            const hero = siteContent.hero || {};
            const cta = siteContent.cta || {};

            document.getElementById('heroBadge').value = hero.badge || '';
            document.getElementById('heroBannerImage').value = hero.bannerImage || '';
            document.getElementById('heroTitle').value = hero.title || '';
            document.getElementById('heroTitleHighlight').value = hero.titleHighlight || '';
            document.getElementById('heroSubtitle').value = hero.subtitle || '';

            document.getElementById('heroStat1Value').value = hero.stat1Value || '';
            document.getElementById('heroStat1Label').value = hero.stat1Label || '';
            document.getElementById('heroStat2Value').value = hero.stat2Value || '';
            document.getElementById('heroStat2Label').value = hero.stat2Label || '';
            document.getElementById('heroStat3Value').value = hero.stat3Value || '';
            document.getElementById('heroStat3Label').value = hero.stat3Label || '';
            document.getElementById('heroStat4Value').value = hero.stat4Value || '';
            document.getElementById('heroStat4Label').value = hero.stat4Label || '';

            document.getElementById('ctaTitle').value = cta.title || '';
            document.getElementById('ctaSubtitle').value = cta.subtitle || '';
            document.getElementById('ctaButton').value = cta.button || '';
        }

        async function saveContentForm() {
            if (!siteContent) return;
            siteContent.hero = siteContent.hero || {};
            siteContent.hero.badge = document.getElementById('heroBadge').value;
            siteContent.hero.bannerImage = document.getElementById('heroBannerImage').value;
            siteContent.hero.title = document.getElementById('heroTitle').value;
            siteContent.hero.titleHighlight = document.getElementById('heroTitleHighlight').value;
            siteContent.hero.subtitle = document.getElementById('heroSubtitle').value;

            siteContent.hero.stat1Value = document.getElementById('heroStat1Value').value;
            siteContent.hero.stat1Label = document.getElementById('heroStat1Label').value;
            siteContent.hero.stat2Value = document.getElementById('heroStat2Value').value;
            siteContent.hero.stat2Label = document.getElementById('heroStat2Label').value;
            siteContent.hero.stat3Value = document.getElementById('heroStat3Value').value;
            siteContent.hero.stat3Label = document.getElementById('heroStat3Label').value;
            siteContent.hero.stat4Value = document.getElementById('heroStat4Value').value;
            siteContent.hero.stat4Label = document.getElementById('heroStat4Label').value;

            siteContent.cta = siteContent.cta || {};
            siteContent.cta.title = document.getElementById('ctaTitle').value;
            siteContent.cta.subtitle = document.getElementById('ctaSubtitle').value;
            siteContent.cta.button = document.getElementById('ctaButton').value;

            const fd = new FormData();
            fd.append('action', 'save_content');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('target_lang', currentLang);
            fd.append('content_json', JSON.stringify(siteContent));

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                loadBackups();
            } else {
                showToast(data.message || 'Hata', 'error');
            }
        }

        async function saveAsDraft() {
            if (!siteContent) return;
            const fd = new FormData();
            fd.append('action', 'save_draft');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('target_lang', currentLang);
            fd.append('content_json', JSON.stringify(siteContent));

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
            }
        }

        // 6. Menü Düzenleyici
        function renderMenuEditor() {
            if (!siteContent || !siteContent.menu) return;
            const container = document.getElementById('menuItemsContainer');
            container.innerHTML = '';

            siteContent.menu.forEach((item, idx) => {
                const el = document.createElement('div');
                el.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#0b1113; border:1px solid var(--border); border-radius:10px; margin-bottom:8px;';
                el.innerHTML = `
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="color:var(--text-muted); font-size:12px;">#${item.order || (idx+1)}</span>
                        <input type="text" class="form-input menu-label-input" value="${escapeHtml(item.label)}" style="width:200px; padding:6px 10px;" onchange="updateMenuLabel(${idx}, this.value)">
                        <span style="font-size:12px; color:var(--text-muted);">${escapeHtml(item.url)}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="font-size:12px; display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" ${item.visible !== false ? 'checked' : ''} onchange="toggleMenuVisibility(${idx}, this.checked)"> Menüde Göster
                        </label>
                    </div>
                `;
                container.appendChild(el);
            });
        }

        function updateMenuLabel(idx, val) {
            if (siteContent.menu[idx]) siteContent.menu[idx].label = val;
        }

        function toggleMenuVisibility(idx, isVisible) {
            if (siteContent.menu[idx]) siteContent.menu[idx].visible = isVisible;
        }

        async function saveMenuOrder() {
            const fd = new FormData();
            fd.append('action', 'save_menu');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('target_lang', currentLang);
            fd.append('menu_json', JSON.stringify(siteContent.menu));

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                loadBackups();
            }
        }

        // 7. Medya Kütüphanesi
        async function loadMediaGrid() {
            const container = document.getElementById('mediaGridContainer');
            if (!container) return;
            try {
                const res = await fetch('api.php?action=list_media');
                const data = await res.json();
                if (!data.success) return;

                container.innerHTML = '';
                const files = data.media || [];
                if (files.length === 0) {
                    container.innerHTML = '<div style="color:var(--text-muted); font-size:13px; grid-column:1/-1;">Henüz görsel yüklenmemiş.</div>';
                    return;
                }

                files.forEach(f => {
                    const item = document.createElement('div');
                    item.className = 'media-item';
                    item.innerHTML = `
                        <img src="${f.url}" class="media-thumb" loading="lazy">
                        <div class="media-info" title="${f.name}">${f.name}</div>
                        <div style="padding:4px 8px 8px; display:flex; justify-content:space-between; align-items:center;">
                            <button class="btn btn-secondary btn-sm" style="padding:3px 6px; font-size:10px;" onclick="copyToClipboard('${f.url}')">Kopyala</button>
                            <button class="btn btn-danger btn-sm" style="padding:3px 6px; font-size:10px;" onclick="deleteMediaFile('${f.name}')">Sil</button>
                        </div>
                    `;
                    container.appendChild(item);
                });
            } catch (e) {
                console.error(e);
            }
        }

        function openMediaUploadModal() { openModal('mediaUploadModal'); }

        async function uploadMediaFile() {
            const fileInput = document.getElementById('mediaFileInput');
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Lütfen bir dosya seçin.');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'upload_media');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('image_file', fileInput.files[0]);

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                closeModal('mediaUploadModal');
                loadMediaGrid();
            } else {
                showToast(data.message || 'Yükleme başarısız', 'error');
            }
        }

        async function deleteMediaFile(filename) {
            if (!confirm(`"${filename}" dosyasını silmek istediğinizden emin misiniz?`)) return;
            const fd = new FormData();
            fd.append('action', 'delete_media');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('filename', filename);

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                loadMediaGrid();
            }
        }

        // Medyadan Seçici Modalı
        async function selectMediaForInput(inputId) {
            activeInputTarget = document.getElementById(inputId);
            const grid = document.getElementById('mediaPickerGrid');
            grid.innerHTML = 'Yükleniyor...';
            openModal('mediaPickerModal');

            const res = await fetch('api.php?action=list_media');
            const data = await res.json();
            grid.innerHTML = '';
            (data.media || []).forEach(f => {
                const item = document.createElement('div');
                item.className = 'media-item';
                item.onclick = () => {
                    if (activeInputTarget) activeInputTarget.value = f.url;
                    closeModal('mediaPickerModal');
                };
                item.innerHTML = `
                    <img src="${f.url}" class="media-thumb">
                    <div class="media-info">${f.name}</div>
                `;
                grid.appendChild(item);
            });
        }

        // 8. Doküman Listesi & PDF Yükleme
        async function loadDocList() {
            const tbody = document.getElementById('docListTableBody');
            if (!tbody) return;
            try {
                const res = await fetch('api.php?action=get_doc_list');
                const data = await res.json();
                if (!data.success) return;

                tbody.innerHTML = '';
                (data.docs || []).forEach(d => {
                    tbody.innerHTML += `
                        <tr>
                            <td><strong>${escapeHtml(d.name)}</strong></td>
                            <td>
                                ${d.tr.exists ? `<a href="${d.tr.url}" target="_blank" style="color:var(--primary); font-weight:600;">✓ Mevcut (${d.tr.size})</a>` : '<span style="color:var(--text-muted);">Yüklenmemiş</span>'}
                                <br><label class="btn btn-secondary btn-sm" style="margin-top:4px; padding:3px 8px; font-size:11px; cursor:pointer;">
                                    📤 Yükle <input type="file" style="display:none;" accept=".pdf" onchange="uploadPdfDoc('${d.key}', 'tr', this)">
                                </label>
                            </td>
                            <td>
                                ${d.en.exists ? `<a href="${d.en.url}" target="_blank" style="color:var(--primary); font-weight:600;">✓ Mevcut (${d.en.size})</a>` : '<span style="color:var(--text-muted);">Yüklenmemiş</span>'}
                                <br><label class="btn btn-secondary btn-sm" style="margin-top:4px; padding:3px 8px; font-size:11px; cursor:pointer;">
                                    📤 Yükle <input type="file" style="display:none;" accept=".pdf" onchange="uploadPdfDoc('${d.key}', 'en', this)">
                                </label>
                            </td>
                            <td><span style="font-size:12px; color:var(--text-muted);">${d.tr.updated}</span></td>
                        </tr>
                    `;
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function uploadPdfDoc(key, docLang, inputEl) {
            if (!inputEl.files || inputEl.files.length === 0) return;
            const file = inputEl.files[0];

            const fd = new FormData();
            fd.append('action', 'upload_pdf');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('doc_key', key);
            fd.append('doc_lang', docLang);
            fd.append('pdf_file', file);

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                loadDocList();
            } else {
                showToast(data.message || 'PDF yükleme hatası', 'error');
            }
        }

        // 9. Sürüm Geçmişi & Geri Alma (Rollback)
        async function loadBackups() {
            const tbody = document.getElementById('backupsTableBody');
            const dashCount = document.getElementById('dashBackupCount');
            if (!tbody) return;

            try {
                const res = await fetch(`api.php?action=list_backups&lang=${currentLang}`);
                const data = await res.json();
                if (!data.success) return;

                const backups = data.backups || [];
                if (dashCount) dashCount.textContent = backups.length;
                tbody.innerHTML = '';

                if (backups.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Henüz yedek sürüm kaydı bulunmuyor.</td></tr>';
                    return;
                }

                backups.forEach(b => {
                    tbody.innerHTML += `
                        <tr>
                            <td><strong>${b.date}</strong></td>
                            <td style="font-family:monospace; color:var(--text-muted);">${b.filename}</td>
                            <td>${b.size}</td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="restoreBackup('${b.filename}')">🔄 Bu Sürüme Geri Dön</button>
                            </td>
                        </tr>
                    `;
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function restoreBackup(filename) {
            if (!confirm(`DİKKAT: "${filename}" yedeğine geri dönmek istediğinizden emin misiniz? Canlı içerikler bu sürümle değiştirilecektir.`)) return;

            const fd = new FormData();
            fd.append('action', 'restore_backup');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('filename', filename);

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                await fetchContent();
                loadCmsContent();
                renderProductsTable();
                loadBackups();
            } else {
                showToast(data.message || 'Geri yükleme hatası', 'error');
            }
        }

        // 10. Genel Ayarlar Formu
        const generalForm = document.getElementById('generalSettingsForm');
        if (generalForm) {
            generalForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(generalForm);
                fd.append('action', 'save_settings');
                fd.append('csrf_token', CSRF_TOKEN);

                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                } else {
                    showToast(data.message || 'Hata', 'error');
                }
            });
        }

        // 11. Şifre Değiştirme Formu
        const passForm = document.getElementById('changePasswordForm');
        if (passForm) {
            passForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(passForm);
                fd.append('action', 'change_password');
                fd.append('csrf_token', CSRF_TOKEN);

                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    passForm.reset();
                } else {
                    showToast(data.message || 'Hata', 'error');
                }
            });
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            showToast('URL panoya kopyalandı: ' + text);
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Başlat
        document.addEventListener('DOMContentLoaded', () => {
            initDashboard();
        });
    </script>
<?php endif; ?>

</body>
</html>
