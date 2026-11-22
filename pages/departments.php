<?php
include '../includes/auth.php';

// only the administrator can manage departments
$user = require_role(['admin']);

$errors  = [];
// 0 means we are adding a new department
$edit_id = (int) ($_GET['edit'] ?? 0);
$form    = ['name' => '', 'code' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'];
    $id     = (int) $_POST['id'];

    // add or update a department
    if ($action === 'save') {
        $form['name'] = trim($_POST['name']);
        $form['code'] = strtoupper(trim($_POST['code']));
        $edit_id      = $id;

        // the short code goes into every contract reference, so it must be set
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $form['code'])) {
            $errors[] = 'The short code must be 2 to 10 letters or numbers, for example ICT.';
        }

        if (strlen($form['name']) < 2) {
            $errors[] = 'Please enter the department name.';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id FROM departments WHERE name = ? AND id <> ?");
            mysqli_stmt_bind_param($stmt, "si", $form['name'], $id);
            mysqli_stmt_execute($stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'A department with that name already exists.';
            }
        }

        if (!$errors) {
            $code = ($form['code'] !== '') ? $form['code'] : null;

            if ($id > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE departments SET name = ?, code = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssi", $form['name'], $code, $id);
                $_SESSION['success'] = 'Department updated.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO departments (name, code) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ss", $form['name'], $code);
                $_SESSION['success'] = 'Department added.';
            }
            mysqli_stmt_execute($stmt);
            header('Location: departments.php');
            exit;
        }
    } elseif ($action === 'toggle') {
        // switch between active and inactive
        $stmt = mysqli_prepare($conn, "UPDATE departments SET is_active = 1 - is_active WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'Department status updated.';
        header('Location: departments.php');
        exit;
    }
} elseif ($edit_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT name, code FROM departments WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $dept = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($dept) {
        $form = ['name' => $dept['name'], 'code' => (string) $dept['code']];
    } else {
        $edit_id = 0;
    }
}

// all departments with the number of users in each
$departments = mysqli_fetch_all(
    mysqli_query($conn,
        "SELECT d.id, d.name, d.code, d.is_active,
                (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS user_count
         FROM departments d
         ORDER BY d.name"),
    MYSQLI_ASSOC
);

$page_title = 'Departments';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Departments</h2>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="panel">
    <h3><?= $edit_id > 0 ? 'Edit department' : 'Add department' ?></h3>

    <form method="post" action="departments.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $edit_id ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Department name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($form['name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="code">Short code</label>
                <input type="text" id="code" name="code" class="form-control" maxlength="10" required value="<?= e($form['code']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $edit_id > 0 ? 'Save Changes' : 'Add Department' ?></button>
            <?php if ($edit_id > 0): ?>
                <a href="departments.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <h3>All departments</h3>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Users</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($departments as $d): ?>
            <tr>
                <td><strong><?= e($d['name']) ?></strong></td>
                <td><?= e($d['code'] ?: '-') ?></td>
                <td><?= (int) $d['user_count'] ?></td>
                <td>
                    <span class="badge <?= $d['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $d['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a href="departments.php?edit=<?= (int) $d['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                        <form method="post" action="departments.php" data-confirm="<?= $d['is_active'] ? 'Deactivate' : 'Activate' ?> <?= e($d['name']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $d['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                <?= $d['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
