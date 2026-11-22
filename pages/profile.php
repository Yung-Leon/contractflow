<?php
include '../includes/auth.php';

// any logged in user can open their own profile
$user   = require_login();
$errors = [];
$form   = ['full_name' => $user['full_name'], 'phone' => (string) $user['phone']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['full_name'] = trim($_POST['full_name']);
    $form['phone']     = trim($_POST['phone']);

    // check the name
    if (strlen($form['full_name']) < 2) {
        $errors[] = 'Please enter your full name.';
    }

    if (!$errors) {
        $phone = ($form['phone'] !== '') ? $form['phone'] : null;

        // save the new details
        $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $form['full_name'], $phone, $user['id']);
        mysqli_stmt_execute($stmt);

        $_SESSION['success'] = 'Your profile has been updated.';
        header('Location: profile.php');
        exit;
    }
}

$page_title = 'My Profile';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>My Profile</h2>
    <a href="change_password.php" class="btn btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Change Password</a>
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

<div class="two-col">
    <div class="panel">
        <h3>Account details</h3>
        <dl class="detail-list">
            <dt>Email</dt>
            <dd><?= e($user['email']) ?></dd>

            <dt>Role</dt>
            <dd><?= e($roles[$user['role']]) ?></dd>

            <?php if ($user['role'] === 'contractor'): ?>
                <dt>Company</dt>
                <dd><?= e($user['company_name'] ?: '-') ?></dd>
            <?php else: ?>
                <dt>Department</dt>
                <dd><?= e($user['department_name'] ?: '-') ?></dd>
            <?php endif; ?>

            <dt>Last login</dt>
            <dd><?= $user['last_login'] ? e(date('d M Y, H:i', strtotime($user['last_login']))) : 'Never' ?></dd>
        </dl>
    </div>

    <div class="panel">
        <h3>Edit my details</h3>
        <form method="post" action="profile.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= e($form['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone number</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e($form['phone']) ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
