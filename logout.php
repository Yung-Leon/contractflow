<?php
include 'includes/auth.php';

// only a post with a valid token signs the user out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    session_destroy();
    header('Location: login.php');
    exit;
}

header('Location: dashboard.php');
exit;
