<?php
// The layout shared by every page: sidebar, top bar and messages.
// Set $page_title before including this file.
global $roles;
// text under the logo
$role_panel = ($user['role'] === 'admin') ? 'Admin Panel' : $roles[$user['role']] . ' Panel';

// the sidebar: shortcuts and things that need attention, only what this user may use
include_once __DIR__ . '/contracts.php';
$nav_page = basename($_SERVER['PHP_SELF']);
$nav_filter = $_GET['filter'] ?? '';
$side    = sidebar_counts($conn, $user);

// icon shapes used by the sidebar
$nav_icons = [
    'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
    'user-add' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
    'check'    => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'alert'    => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
];

$nav_groups = [];

// shortcuts
$quick = [];
if ($user['role'] === 'department_officer' && can($user, 'contract_create')) {
    $quick[] = ['label' => 'New contract', 'href' => 'pages/contract_form.php', 'icon' => 'plus', 'color' => '#e53935'];
}
if ($user['role'] === 'admin') {
    $quick[] = ['label' => 'Add user', 'href' => 'pages/user_form.php', 'icon' => 'user-add', 'color' => '#1a5fb4'];
}
if ($quick) {
    $nav_groups['Quick actions'] = $quick;
}

// things to keep an eye on
$watch = [];
if (can($user, 'approvals')) {
    $watch[] = ['label' => 'Awaiting approval', 'href' => 'pages/approvals.php', 'icon' => 'check', 'color' => '#2e7d32',
                'count' => $side['waiting'], 'active' => $nav_page === 'approvals.php'];
}
if (can($user, 'contracts')) {
    $watch[] = ['label' => 'Expiring soon', 'href' => 'pages/contracts.php?filter=expiring', 'icon' => 'calendar', 'color' => '#c77700',
                'count' => $side['expiring'], 'active' => $nav_page === 'contracts.php' && $nav_filter === 'expiring'];
    $watch[] = ['label' => 'Overdue', 'href' => 'pages/contracts.php?filter=overdue', 'icon' => 'alert', 'color' => '#e53935',
                'count' => $side['overdue'], 'alert' => true, 'active' => $nav_page === 'contracts.php' && $nav_filter === 'overdue'];
}
if (in_array($user['role'], ['admin', 'ceo'])) {
    $watch[] = ['label' => 'Activity log', 'href' => 'pages/activity.php', 'icon' => 'activity', 'color' => '#3949ab',
                'active' => $nav_page === 'activity.php'];
}
if ($watch) {
    $nav_groups['Monitor'] = $watch;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | ContractFlow</title>
    <link rel="icon" href="<?= $base ?>assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>

    <!-- sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <img src="<?= $base ?>assets/img/logo.svg" alt="CF" class="brand-logo">
            <div>
                <strong>ContractFlow</strong>
                <span><?= e($role_panel) ?></span>
            </div>
        </div>

        <nav>
            <a href="<?= $base ?>dashboard.php" class="nav-link<?= $nav_page === 'dashboard.php' ? ' active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Home
            </a>

            <?php foreach ($nav_groups as $group_name => $items): ?>
                <div class="nav-label"><?= e($group_name) ?></div>
                <?php foreach ($items as $item): ?>
                    <a href="<?= $base . e($item['href']) ?>" class="nav-link<?= !empty($item['active']) ? ' active' : '' ?>">
                        <span class="chip" style="background: <?= e($item['color']) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $nav_icons[$item['icon']] ?></svg></span>
                        <?= e($item['label']) ?>
                        <?php if (!empty($item['count'])): ?>
                            <span class="nav-count<?= !empty($item['alert']) ? ' alert' : '' ?>"><?= (int) $item['count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="main">
        <!-- top bar with the bell and the user menu -->
        <header class="topbar">
            <h1><?= e($page_title) ?></h1>

            <div class="topbar-right">
                <?php if (can($user, 'notifications')): ?>
                    <?php $unread = count_unread($conn, $user['id']); ?>
                    <a href="<?= $base ?>pages/notifications.php" class="bell" title="Notifications">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($unread > 0): ?>
                            <span class="bell-count"><?= $unread > 99 ? '99+' : $unread ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

            <div class="user-menu">
                <button type="button" class="user-btn" id="userBtn">
                    <svg class="avatar" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#e6e9ef"/><circle cx="16" cy="12" r="5" fill="#ffffff"/><path d="M6 28c1-6 5-9 10-9s9 3 10 9a16 16 0 0 1-20 0z" fill="#ffffff"/></svg>
                    <span><?= e($user['email']) ?></span>
                    <svg class="caret" viewBox="0 0 12 12"><path d="M2 4l4 4 4-4z" fill="currentColor"/></svg>
                </button>

                <div class="dropdown" id="userDropdown">
                    <div class="dropdown-info">
                        <strong><?= e($user['full_name']) ?></strong>
                        <small><?= e($roles[$user['role']]) ?></small>
                    </div>
                    <a href="<?= $base ?>pages/profile.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>My Profile</a>
                    <a href="<?= $base ?>pages/change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Change Password</a>
                    <form method="post" action="<?= $base ?>logout.php">
                        <?= csrf_field() ?>
                        <button type="submit" class="logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</button>
                    </form>
                </div>
            </div>
            </div>
        </header>

        <!-- page content starts here -->
        <main class="content">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= e($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-error"><?= e($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
