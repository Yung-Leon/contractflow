<?php
include '../includes/auth.php';

$user   = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // read the three password fields
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user['id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // the old password has to be right
    if (!password_verify($current, $row['password_hash'])) {
        $errors[] = 'Your current password is not correct.';
    }
    // the new password needs 8 characters and must match the confirmation
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'The two new passwords do not match.';
    }

    if (!$errors) {
        // save the new password and clear the forced change
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $user['id']);
        mysqli_stmt_execute($stmt);

        $_SESSION['success'] = 'Your password has been changed.';
        header('Location: ../dashboard.php');
        exit;
    }
}

$page_title = 'Change Password';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Change Password</h2>
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

<div class="panel narrow">
    <form method="post" action="change_password.php">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required>
            <span class="hint">At least 8 characters.</span>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Password</button>
            <?php if (!$user['must_change_password']): ?>
                <a href="../dashboard.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
