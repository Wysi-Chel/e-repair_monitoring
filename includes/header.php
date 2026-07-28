<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_portal_auth();

$pageTitle = $pageTitle ?? APP_NAME;
$pageSubtitle = $pageSubtitle ?? '';
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$flashes = pull_flashes();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= PORTAL_BASE ?>/assets/img/favicon.png">
    <meta name="theme-color" content="#bf1f2f">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <script src="<?= url('assets/js/app.js') ?>" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="app-sidebar">
        <a class="brand" href="<?= url() ?>">
            <span class="brand-mark"><img src="<?= PORTAL_BASE ?>/assets/img/favicon.png" alt=""></span>
            <span><small>MICEI Portal</small><strong>Equipment Repair</strong></span>
        </a>

        <nav aria-label="Main navigation">
            <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= url() ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>
                Dashboard
            </a>
            <a class="<?= in_array($currentPage, ['repairs.php','repair_form.php','repair_view.php'], true) ? 'active' : '' ?>" href="<?= url('repairs.php') ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m14.7 6.3 3-3a4.5 4.5 0 0 1-5.8 5.8l-6.6 6.6a2.1 2.1 0 0 0 3 3l6.6-6.6a4.5 4.5 0 0 0 5.8-5.8l-3 3z"/></svg>
                Repair Requests
            </a>
            <a class="<?= in_array($currentPage, ['equipment.php','equipment_form.php'], true) ? 'active' : '' ?>" href="<?= url('equipment.php') ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Equipment Registry
            </a>
            <a class="<?= $currentPage === 'activity.php' ? 'active' : '' ?>" href="<?= url('activity.php') ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12h4l2-6 4 12 2-6h6"/><path d="M4 4h16v16H4z"/></svg>
                Activity Log
            </a>
        </nav>

        <div class="sidebar-user">
            <span class="user-avatar"><?= e(strtoupper(substr((string) ($user['full_name'] ?? 'U'), 0, 1))) ?></span>
            <div>
                <strong><?= e($user['full_name'] ?? 'Portal user') ?></strong>
                <small><?= e($user['role'] ?? 'User') ?></small>
            </div>
            <a href="<?= PORTAL_BASE ?>/systems.php">Back to systems</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <p class="eyebrow">IT Department</p>
                <h1><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle !== ''): ?><p class="subtitle"><?= e($pageSubtitle) ?></p><?php endif; ?>
            </div>
            <div class="topbar-actions">
                <button class="sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    Menu
                </button>
                <a class="btn btn-primary" href="<?= url('repair_form.php') ?>">
                    <svg class="btn-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    New repair request
                </a>
            </div>
        </header>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type'] ?? 'success') ?>"><?= e($flash['message'] ?? '') ?></div>
        <?php endforeach; ?>
