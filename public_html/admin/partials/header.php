<?php
/** @var string $pageTitle  @var string $active */
$unread = 0;
foreach (cms_read_json(CMS_DATA . '/messages.json') as $m) {
    if (is_array($m) && ($m['status'] ?? '') === 'unread') $unread++;
}
$nav = [
    ['index.php',    'dashboard', 'Genel Bakış',      'dashboard', null],
    ['content.php',  'file-text', 'Sayfa İçerikleri', 'content',   null],
    ['messages.php', 'inbox',     'Gelen Formlar',    'messages',  $unread ?: null],
    ['media.php?type=images', 'image', 'Görseller',  'media',     null],
    ['media.php?type=docs', 'file-down', 'Dokümanlar', 'docs',      null],
    ['settings.php', 'building',  'Firma Bilgileri',  'settings',  null],
    ['history.php',  'history',   'Sürüm Geçmişi',    'history',   null],
    ['security.php', 'shield',    'Güvenlik',         'security',  null],
];
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(admin_csrf()) ?>">
<title><?= e($pageTitle) ?> · OEM Vision Panel</title>
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php">
            <img src="/icon.png" alt="" width="32" height="32">
            <span>OEM <b>VISION</b></span>
        </a>
        <nav class="nav">
            <?php foreach ($nav as [$href, $ico, $label, $key, $badge]): ?>
                <a href="<?= e($href) ?>" class="nav-item<?= $active === $key ? ' is-active' : '' ?><?= $href === '#' ? ' is-soon' : '' ?>"
                   <?= $href === '#' ? 'title="Yakında" aria-disabled="true"' : '' ?>>
                    <?= icon($ico) ?><span><?= e($label) ?></span>
                    <?php if ($badge): ?><em class="badge"><?= (int) $badge ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="user-chip"><?= icon('user', 16) ?><span><?= e($_SESSION['admin_user'] ?? '') ?></span></div>
            <a class="nav-item" href="logout.php"><?= icon('log-out') ?><span>Çıkış</span></a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="icon-btn only-mobile" type="button" data-toggle-sidebar aria-label="Menü"><?= icon('menu') ?></button>
            <h1><?= e($pageTitle) ?></h1>
            <div class="topbar-actions">
                <a class="btn btn-ghost" href="/tr/" target="_blank" rel="noopener"><?= icon('external', 16) ?><span>Siteyi aç</span></a>
            </div>
        </header>
        <main class="content">
