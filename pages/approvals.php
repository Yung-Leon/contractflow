<?php
include '../includes/auth.php';
include '../includes/contracts.php';

$user = require_permission('approvals');

$scope = contract_scope($user);

// contracts waiting for this user
$result = mysqli_query($conn,
    "SELECT c.id, c.reference_no, c.title, c.status, c.updated_at, c.department_id, c.contractor_id, d.name AS department_name
     FROM contracts c
     JOIN departments d ON d.id = c.department_id
     WHERE " . $scope . " AND " . waiting_condition($user) . "
     ORDER BY c.updated_at ASC");
$waiting = mysqli_fetch_all($result, MYSQLI_ASSOC);

// every contract still moving through the stages
$in_stages = "'" . implode("','", array_keys($stages)) . "'";
$result = mysqli_query($conn,
    "SELECT c.id, c.reference_no, c.title, c.status, c.updated_at, c.department_id, c.contractor_id, d.name AS department_name
     FROM contracts c
     JOIN departments d ON d.id = c.department_id
     WHERE " . $scope . " AND c.status IN (" . $in_stages . ")
     ORDER BY c.updated_at DESC");
$in_progress = mysqli_fetch_all($result, MYSQLI_ASSOC);

// prints one table of contracts
function contract_rows($rows, $statuses, $empty, $user)
{
    ?>
    <table class="table">
        <thead>
            <tr><th>Reference</th><th>Service</th><th>Department</th><th>Stage</th><th>Last update</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
            <tr>
                <td><a href="contract_view.php?id=<?= (int) $c['id'] ?>" class="link"><?= e($c['reference_no']) ?></a></td>
                <td><?= e($c['title']) ?></td>
                <td><?= e($c['department_name']) ?></td>
                <td><span class="badge <?= status_class($c['status']) ?>"><?= e($statuses[$c['status']]) ?></span></td>
                <td><?= e(date('d M Y', strtotime($c['updated_at']))) ?></td>
                <td class="row-action">
                    <?php $label = action_label($c, $user); ?>
                    <?php if ($label !== ''): ?>
                        <a href="contract_view.php?id=<?= (int) $c['id'] ?>#action" class="btn btn-primary btn-sm"><?= e($label) ?></a>
                    <?php else: ?>
                        <a href="contract_view.php?id=<?= (int) $c['id'] ?>" class="btn btn-outline btn-sm">View</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty"><?= e($empty) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
}

$page_title = 'Approvals';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Approvals</h2>
</div>

<div class="panel">
    <h3>Waiting for your action</h3>
    <?php contract_rows($waiting, $statuses, 'Nothing is waiting for you right now.', $user); ?>
</div>

<div class="panel">
    <h3>All contracts in progress</h3>
    <?php contract_rows($in_progress, $statuses, 'No contracts are in progress.', $user); ?>
</div>

<?php include '../includes/footer.php'; ?>
