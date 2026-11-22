<?php
include '../includes/auth.php';
include '../includes/contracts.php';

$user = require_permission('notifications');

// check for expired contracts and expiry warnings first
refresh_contracts($conn);

// opening a notification marks it as read and goes to its contract
if (isset($_GET['open'])) {
    $open = (int) $_GET['open'];

    $stmt = mysqli_prepare($conn, "SELECT contract_id FROM notifications WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $open, $user['id']);
    mysqli_stmt_execute($stmt);
    $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($note) {
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $open);
        mysqli_stmt_execute($stmt);

        header('Location: ' . ($note['contract_id'] ? 'contract_view.php?id=' . (int) $note['contract_id'] : 'notifications.php'));
        exit;
    }
}

// mark everything as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user['id']);
    mysqli_stmt_execute($stmt);

    $_SESSION['success'] = 'All notifications marked as read.';
    header('Location: notifications.php');
    exit;
}

// the latest 50 notifications for this user
$stmt = mysqli_prepare($conn,
    "SELECT id, contract_id, message, is_read, created_at FROM notifications
     WHERE user_id = ? ORDER BY id DESC LIMIT 50");
mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
$notes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$unread = count_unread($conn, $user['id']);

$page_title = 'Notifications';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Notifications</h2>
    <?php if ($unread > 0): ?>
        <form method="post" action="notifications.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<div class="panel panel-flush">
    <?php foreach ($notes as $n): ?>
        <div class="note<?= $n['is_read'] ? '' : ' unread' ?>">
            <div>
                <span><?= e($n['message']) ?></span>
                <small><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></small>
            </div>
            <a href="notifications.php?open=<?= (int) $n['id'] ?>" class="btn btn-outline btn-sm"><?= $n['contract_id'] ? 'Open' : 'Mark read' ?></a>
        </div>
    <?php endforeach; ?>
    <?php if (!$notes): ?>
        <p class="empty-note">You have no notifications.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
