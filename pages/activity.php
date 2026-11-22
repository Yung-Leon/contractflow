<?php
include '../includes/auth.php';
include '../includes/contracts.php';

// only the administrator and the CEO can read the activity log
$user = require_role(['admin', 'ceo']);

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;

// how many entries there are
$row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM activity_logs"));
$total = (int) $row['total'];

$pages  = max(1, (int) ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

// the newest entries first, with the name of the person and the contract
$logs = mysqli_fetch_all(mysqli_query($conn,
    "SELECT l.action, l.details, l.created_at, u.full_name, c.id AS contract_id, c.reference_no
     FROM activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     LEFT JOIN contracts c ON c.id = l.contract_id
     ORDER BY l.id DESC LIMIT " . $per_page . " OFFSET " . $offset), MYSQLI_ASSOC);

// readable names for the actions we record
function action_name($action)
{
    $names = [
        'request_created'     => 'Request created',
        'request_edited'      => 'Request edited',
        'request_submitted'   => 'Request submitted',
        'contract_approved'   => 'Approved',
        'contract_returned'   => 'Returned',
        'contract_rejected'   => 'Rejected',
        'contract_terminated' => 'Terminated',
    ];
    return $names[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

$page_title = 'Activity Log';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Activity Log</h2>
</div>

<div class="panel">
    <table class="table">
        <thead>
            <tr>
                <th>When</th>
                <th>User</th>
                <th>Action</th>
                <th>Contract</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="log-when"><?= e(date('d M Y, H:i', strtotime($log['created_at']))) ?></td>
                <td><?= e($log['full_name'] ?: 'System') ?></td>
                <td class="log-action"><?= e(action_name($log['action'])) ?></td>
                <td>
                    <?php if ($log['contract_id']): ?>
                        <a href="contract_view.php?id=<?= (int) $log['contract_id'] ?>" class="link"><?= e($log['reference_no']) ?></a>
                    <?php else: ?>
                        <span class="muted">-</span>
                    <?php endif; ?>
                </td>
                <td><?= e($log['details'] ?: '-') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
            <tr><td colspan="5" class="empty">No activity yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <span><?= $total ?> entries, page <?= $page ?> of <?= $pages ?></span>
        <div>
            <?php if ($page > 1): ?>
                <a href="activity.php?page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a href="activity.php?page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
