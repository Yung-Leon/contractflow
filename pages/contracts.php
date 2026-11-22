<?php
include '../includes/auth.php';
include '../includes/contracts.php';

// needs the contracts permission
$user = require_permission('contracts');

// mark expired contracts and send expiry warnings
refresh_contracts($conn);

// filters come from the address bar
$search   = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$dept     = (int) ($_GET['dept'] ?? 0);
$filter   = $_GET['filter'] ?? '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

// build the where part one filter at a time, values always go through placeholders
$where  = [contract_scope($user)];
$types  = '';
$params = [];

if ($search !== '') {
    $like    = '%' . $search . '%';
    $where[] = '(c.reference_no LIKE ? OR c.title LIKE ? OR ct.full_name LIKE ? OR ct.company_name LIKE ?)';
    $types  .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if (isset($statuses[$status])) {
    $where[] = 'c.status = ?';
    $types  .= 's';
    $params[] = $status;
}
if ($filter === 'expiring') {
    $where[] = expiring_condition();
}
if ($filter === 'overdue') {
    $where[] = overdue_condition();
}
if ($dept > 0) {
    $where[] = 'c.department_id = ?';
    $types  .= 'i';
    $params[] = $dept;
}

$from = "FROM contracts c
         JOIN departments d ON d.id = c.department_id
         LEFT JOIN users ct ON ct.id = c.contractor_id
         WHERE " . implode(' AND ', $where);

// count the matches so we know how many pages there are
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total " . $from);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$row   = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total = (int) $row['total'];

$pages  = max(1, (int) ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

// get the contracts for this page
$stmt = mysqli_prepare($conn,
    "SELECT c.id, c.reference_no, c.title, c.status, c.department_id, c.contractor_id,
            d.name AS department_name, ct.full_name AS contractor_name, ct.company_name
     " . $from . " ORDER BY c.id DESC LIMIT " . $per_page . " OFFSET " . $offset);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$contracts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$departments = mysqli_fetch_all(mysqli_query($conn, "SELECT id, name FROM departments ORDER BY name"), MYSQLI_ASSOC);
$sees_all    = in_array($user['role'], $all_department_roles);
$can_create  = $user['role'] === 'department_officer' && can($user, 'contract_create');

// keeps the filters in the page links
$query = http_build_query(['q' => $search, 'status' => $status, 'dept' => $dept, 'filter' => $filter]);

$page_title = 'Contracts';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Contracts</h2>
    <?php if ($can_create): ?>
        <a href="contract_form.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>New Request
        </a>
    <?php endif; ?>
</div>

<div class="panel">
    <form method="get" action="contracts.php" class="filters">
        <?php if ($filter === 'expiring' || $filter === 'overdue'): ?>
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <?php endif; ?>
        <input type="text" name="q" class="form-control" placeholder="Search reference, service or company" value="<?= e($search) ?>">

        <select name="status" class="form-control">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($sees_all): ?>
        <select name="dept" class="form-control">
            <option value="0">All departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int) $d['id'] ?>"<?= $dept === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($search !== '' || $status !== '' || $dept > 0 || $filter !== ''): ?>
            <a href="contracts.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Service</th>
                <th>Department</th>
                <th>Awarded to</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contracts as $c): ?>
            <?php $label = action_label($c, $user); ?>
            <tr>
                <td><a href="contract_view.php?id=<?= (int) $c['id'] ?>" class="link"><?= e($c['reference_no']) ?></a></td>
                <td><?= e($c['title']) ?></td>
                <td><?= e($c['department_name']) ?></td>
                <td><?= e($c['company_name'] ?: $c['contractor_name'] ?: '-') ?></td>
                <td><span class="badge <?= status_class($c['status']) ?>"><?= e($statuses[$c['status']]) ?></span></td>
                <td class="row-action">
                    <?php if ($label !== ''): ?>
                        <a href="contract_view.php?id=<?= (int) $c['id'] ?>#action" class="btn btn-primary btn-sm"><?= e($label) ?></a>
                    <?php else: ?>
                        <a href="contract_view.php?id=<?= (int) $c['id'] ?>" class="btn btn-outline btn-sm">View</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$contracts): ?>
            <tr><td colspan="6" class="empty">No contracts found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <span><?= $total ?> contracts, page <?= $page ?> of <?= $pages ?></span>
        <div>
            <?php if ($page > 1): ?>
                <a href="contracts.php?<?= $query ?>&page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a href="contracts.php?<?= $query ?>&page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
