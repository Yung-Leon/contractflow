<?php
include '../includes/auth.php';

// only the administrator can open this page
$admin = require_role(['admin']);

// activate or deactivate a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) $_POST['id'];

    // an admin cannot deactivate their own account
    if ($id === (int) $admin['id']) {
        $_SESSION['error'] = 'You cannot deactivate your own account.';
    } else {
        // flip between active and inactive
        $stmt = mysqli_prepare($conn, "UPDATE users SET is_active = 1 - is_active WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'User status updated.';
    }
    header('Location: users.php');
    exit;
}

// search by name or email
$search = trim($_GET['q'] ?? '');
$like   = '%' . $search . '%';

// get the users that match the search
$stmt = mysqli_prepare($conn,
    "SELECT u.id, u.full_name, u.email, u.role, u.company_name, u.is_active, d.name AS department_name
     FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE u.full_name LIKE ? OR u.email LIKE ?
     ORDER BY u.id DESC");
mysqli_stmt_bind_param($stmt, "ss", $like, $like);
mysqli_stmt_execute($stmt);
$users = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$page_title = 'Users';
$user = $admin;
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Manage Users</h2>
    <a href="user_form.php" class="btn btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add User</a>
</div>

<div class="panel">
    <form method="get" action="users.php" class="filters">
        <input type="text" name="q" class="form-control" placeholder="Search by name or email" value="<?= e($search) ?>">
        <button type="submit" class="btn btn-outline">Search</button>
        <?php if ($search !== ''): ?>
            <a href="users.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Department / Company</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <strong><?= e($u['full_name']) ?></strong>
                    <div class="muted"><?= e($u['email']) ?></div>
                </td>
                <td><?= e($roles[$u['role']]) ?></td>
                <td><?= e(($u['role'] === 'contractor' ? $u['company_name'] : $u['department_name']) ?: '-') ?></td>
                <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a href="user_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>

                        <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                        <form method="post" action="users.php" data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= e($u['full_name']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
            <tr><td colspan="5" class="empty">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
