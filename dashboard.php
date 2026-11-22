<?php
include 'includes/auth.php';
include 'includes/contracts.php';

$user = require_login();
$role = $user['role'];

// mark expired contracts and send expiry warnings, then count what needs attention
// mark expired contracts and send expiry warnings, then count what needs attention
refresh_contracts($conn);
$waiting = count_waiting($conn, $user);
$unread  = count_unread($conn, $user['id']);

// the name used in the heading
$role_name  = ($role === 'admin') ? 'Admin' : $roles[$role];
$page_title = $role_name . ' Dashboard';

include 'includes/header.php';
?>

<h2 class="welcome">Welcome to ContractFlow, <?= e($role_name) ?> Dashboard</h2>

<section class="grid">

    <?php if (can($user, 'contracts')): ?>
    <!-- contracts card -->
    <a href="pages/contracts.php" class="card card-red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <h3>Contracts</h3>
        <p>Create, search and manage contracts</p>
    </a>
    <?php endif; ?>

    <?php if (can($user, 'approvals')): ?>
    <!-- approvals card, the number shows contracts waiting for this user -->
    <a href="pages/approvals.php" class="card card-green">
        <?php if ($waiting > 0): ?><span class="count"><?= $waiting ?></span><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <h3>Approvals</h3>
        <p>Track contracts through each stage</p>
    </a>
    <?php endif; ?>

    <?php if (can($user, 'documents')): ?>
    <!-- documents card -->
    <a href="pages/documents.php" class="card card-amber">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <h3>Documents</h3>
        <p>Store and retrieve contract files</p>
    </a>
    <?php endif; ?>

    <?php if (can($user, 'notifications')): ?>
    <!-- notifications card, the number shows unread ones -->
    <a href="pages/notifications.php" class="card card-purple">
        <?php if ($unread > 0): ?><span class="count"><?= $unread ?></span><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <h3>Notifications</h3>
        <p>View expiry and renewal alerts</p>
    </a>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <!-- users card, administrator only -->
    <a href="pages/users.php" class="card card-blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <h3>Users</h3>
        <p>Manage accounts and assign roles</p>
    </a>
    <?php endif; ?>

    <?php if (can($user, 'reports')): ?>
    <!-- reports card, not linked yet -->
    <a href="#" class="card card-teal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="3" y1="20" x2="21" y2="20"/></svg>
        <h3>Reports</h3>
        <p>View status and expiry reports</p>
    </a>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <!-- departments card, administrator only -->
    <a href="pages/departments.php" class="card card-indigo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="1"/><line x1="9" y1="8" x2="9.01" y2="8"/><line x1="15" y1="8" x2="15.01" y2="8"/><line x1="9" y1="12" x2="9.01" y2="12"/><line x1="15" y1="12" x2="15.01" y2="12"/><path d="M10 21v-4h4v4"/></svg>
        <h3>Departments</h3>
        <p>Manage departments</p>
    </a>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <!-- settings card, administrator only -->
    <a href="pages/settings.php" class="card card-slate">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        <h3>Settings</h3>
        <p>Choose what each user can access</p>
    </a>
    <?php endif; ?>

</section>

<?php include 'includes/footer.php'; ?>
