<?php
include '../includes/auth.php';

// only the administrator can add or edit users
$admin   = require_role(['admin']);
$id      = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
// an admin editing their own account cannot change their own role or password here
$is_self = $editing && $id === (int) $admin['id'];

$errors = [];
// the values shown in the form
$form   = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => '', 'department_id' => '', 'company_name' => ''];

// load the user being edited
if ($editing) {
    $stmt = mysqli_prepare($conn, "SELECT full_name, email, phone, role, department_id, company_name FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$existing) {
        header('Location: users.php');
        exit;
    }
    $form = array_map('strval', $existing);
}

// active departments for the drop-down
$departments = mysqli_fetch_all(
    mysqli_query($conn, "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"),
    MYSQLI_ASSOC
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['full_name']     = trim($_POST['full_name']);
    $form['email']         = strtolower(trim($_POST['email']));
    $form['phone']         = trim($_POST['phone']);
    $form['role']          = $is_self ? 'admin' : ($_POST['role'] ?? '');
    $form['department_id'] = $_POST['department_id'] ?? '';
    $form['company_name']  = trim($_POST['company_name']);
    $password              = $_POST['password'];
    $confirm               = $_POST['password_confirm'];

    // check what was typed
    if (strlen($form['full_name']) < 2) {
        $errors[] = 'Please enter the full name.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id <> ?");
        mysqli_stmt_bind_param($stmt, "si", $form['email'], $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = 'That email address is already in use.';
        }
    }
    if (!isset($roles[$form['role']])) {
        $errors[] = 'Please choose a role.';
    }

    // office roles need a department, contractors need a company
    $department_id = null;
    $company_name  = null;
    if (in_array($form['role'], $department_roles)) {
        if ($form['department_id'] === '') {
            $errors[] = 'Please choose a department.';
        } else {
            $department_id = (int) $form['department_id'];
        }
    }
    if ($form['role'] === 'contractor') {
        if ($form['company_name'] === '') {
            $errors[] = 'Please enter the company name.';
        } else {
            $company_name = $form['company_name'];
        }
    }

    // a password is needed for new users and optional when editing
    $set_password = !$is_self && ($password !== '' || !$editing);
    if ($set_password) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }
    }

    if (!$errors) {
        $phone = ($form['phone'] !== '') ? $form['phone'] : null;

        if ($editing) {
            // a new role starts again with the default access for that role
            $reset = ($existing['role'] !== $form['role']) ? ', permissions = NULL' : '';

            $stmt = mysqli_prepare($conn,
                "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, department_id = ?, company_name = ?" . $reset . " WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssisi", $form['full_name'], $form['email'], $phone, $form['role'], $department_id, $company_name, $id);
            mysqli_stmt_execute($stmt);

            if ($set_password) {
                // store the password as a hash, never as plain text
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $hash, $id);
                mysqli_stmt_execute($stmt);
            }
            $_SESSION['success'] = 'User updated.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (full_name, email, phone, password_hash, role, department_id, company_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssssis", $form['full_name'], $form['email'], $phone, $hash, $form['role'], $department_id, $company_name);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'User created.';
        }
        header('Location: users.php');
        exit;
    }
}

$page_title = $editing ? 'Edit User' : 'Add User';
$user = $admin;
include '../includes/header.php';
?>

<div class="page-head">
    <h2><?= $editing ? 'Edit User' : 'Add User' ?></h2>
    <a href="users.php" class="btn btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back to users</a>
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
    <form method="post" action="user_form.php<?= $editing ? '?id=' . $id : '' ?>">
        <?= csrf_field() ?>
        <div class="form-grid">

            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= e($form['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($form['email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone number</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e($form['phone']) ?>">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="form-control" data-dept-roles="<?= e(implode(',', $department_roles)) ?>" <?= $is_self ? 'disabled' : '' ?> required>
                    <option value="">Select a role</option>
                    <?php foreach ($roles as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $form['role'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="field-department">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id" class="form-control">
                    <option value="">Select a department</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"<?= (string) $form['department_id'] === (string) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="field-company">
                <label for="company_name">Company name</label>
                <input type="text" id="company_name" name="company_name" class="form-control" value="<?= e($form['company_name']) ?>">
            </div>

            <?php if (!$is_self): ?>
            <div class="form-group">
                <label for="password"><?= $editing ? 'New password' : 'Password' ?></label>
                <input type="password" id="password" name="password" class="form-control" <?= $editing ? '' : 'required' ?>>
                <span class="hint"><?= $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.' ?></span>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" <?= $editing ? '' : 'required' ?>>
            </div>
            <?php endif; ?>

        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Create User' ?></button>
            <a href="users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
