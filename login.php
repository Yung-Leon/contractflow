<?php
include 'includes/auth.php';

// already logged in, go straight to the dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// keeps the error message and the email that was typed
$error = '';
$email = '';

// the form was sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // look the user up by email
        $stmt = mysqli_prepare($conn, "SELECT id, password_hash, is_active FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // same message for a wrong email or a wrong password
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (!$user['is_active']) {
            $error = 'This account is deactivated. Please contact the administrator.';
        } else {
            // new session id after login, then remember who the user is
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];

            // save the time of this login
            $stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user['id']);
            mysqli_stmt_execute($stmt);

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | ContractFlow</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="login-box">
        <div class="login-brand">
            <img src="assets/img/logo.svg" alt="CF" class="login-logo">
            <h1>ContractFlow</h1>
            <p>Contract Management System</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
    </div>

</body>
</html>
