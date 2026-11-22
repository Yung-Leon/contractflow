<?php
// Starts the session, connects to the database and holds the login and role checks.
// Every page except login.php includes this file first.
session_start();
// connect to the database
include __DIR__ . '/../config/db.php';

// report database errors as exceptions so nothing fails silently
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// pages inside the pages folder need ../ in front of their links
$base = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'pages') ? '../' : '';

// one csrf token per session, every form sends it back and we check it
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// role keys and the names we show on screen
$roles = [
    'admin'               => 'Administrator',
    'department_officer'  => 'Department Officer',
    'procurement_officer' => 'Procurement Officer',
    'legal_officer'       => 'Legal Officer',
    'head_of_legal'       => 'Head of Legal',
    'ceo'                 => 'Chief Executive Officer',
    'contractor'          => 'Contractor',
];

// roles that belong to a department
$department_roles = ['department_officer', 'procurement_officer', 'legal_officer', 'head_of_legal'];

// these roles can always do everything
$full_roles = ['admin', 'ceo'];

// the modules the admin can switch on or off for a user
$modules = [
    'contracts'     => 'Contracts',
    'approvals'     => 'Approvals',
    'documents'     => 'Documents',
    'notifications' => 'Notifications',
    'reports'       => 'Reports',
];

// extra options inside a module
$module_options = [
    'contracts' => [
        'Contracts' => [
            'contract_create' => 'Create contracts',
            'contract_edit'   => 'Edit contracts',
        ],
    ],
    'documents' => [
        'Documents' => [
            'document_upload' => 'Upload documents',
        ],
    ],
    'reports' => [
        'Report types' => [
            'report_status'     => 'Contracts by status',
            'report_department' => 'Contracts by department',
            'report_expiring'   => 'Expiring contracts',
        ],
    ],
];

// what each role gets until the admin changes it in Settings
$default_permissions = [
    'department_officer'  => ['contracts', 'documents', 'notifications', 'contract_create', 'contract_edit', 'document_upload'],
    'procurement_officer' => ['contracts', 'approvals', 'documents', 'notifications'],
    'legal_officer'       => ['contracts', 'approvals', 'documents', 'notifications'],
    'head_of_legal'       => ['contracts', 'approvals', 'documents', 'notifications', 'reports', 'report_status', 'report_expiring'],
    'contractor'          => ['contracts', 'documents', 'notifications'],
];

// makes text safe to print in html
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// hidden input that goes inside every form
function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

// stop the request if the form did not send the right token
function csrf_check()
{
    global $base;

    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $sent)) {
        $_SESSION['error'] = 'Your session expired. Please try again.';
        header('Location: ' . $base . (empty($_SESSION['user_id']) ? 'login.php' : 'dashboard.php'));
        exit;
    }
}

// number of unread notifications for the bell
function count_unread($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (int) $row['total'];
}

// checks someone is logged in and returns their record
function require_login()
{
    global $conn, $base;

    // not logged in, so send them to the login page
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $base . 'login.php');
        exit;
    }

    // get the logged in user and their department name
    $stmt = mysqli_prepare($conn,
        "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.company_name, u.department_id, u.is_active, u.must_change_password, u.permissions, u.last_login,
                d.name AS department_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // unknown or deactivated users are logged out
    if (!$user || !$user['is_active']) {
        session_destroy();
        header('Location: ' . $base . 'login.php');
        exit;
    }

    // someone who must change their password can only open that page
    if ($user['must_change_password'] && basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
        $_SESSION['error'] = 'Please change your password before continuing.';
        header('Location: ' . $base . 'pages/change_password.php');
        exit;
    }
    return $user;
}

// list of permission keys a user has
function user_permissions($u)
{
    global $full_roles, $modules, $module_options, $default_permissions;

    // admin and ceo have everything
    if (in_array($u['role'], $full_roles)) {
        $all = array_keys($modules);
        foreach ($module_options as $sections) {
            foreach ($sections as $items) {
                $all = array_merge($all, array_keys($items));
            }
        }
        return $all;
    }
    // nothing saved yet, so use the defaults for the role
    if ($u['permissions'] === null) {
        return $default_permissions[$u['role']] ?? [];
    }
    return ($u['permissions'] === '') ? [] : explode(',', $u['permissions']);
}

// true if the user may do the given thing, for example can($user, 'reports')
function can($u, $key)
{
    return in_array($key, user_permissions($u));
}

// same as require_login but the user must have the permission
function require_permission($key)
{
    global $base;

    $user = require_login();

    if (!can($user, $key)) {
        header('Location: ' . $base . 'dashboard.php');
        exit;
    }
    return $user;
}

// same as require_login but only the listed roles can open the page
function require_role($allowed)
{
    global $base;

    $user = require_login();

    if (!in_array($user['role'], $allowed)) {
        header('Location: ' . $base . 'dashboard.php');
        exit;
    }
    return $user;
}
?>
