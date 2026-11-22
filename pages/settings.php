<?php
include '../includes/auth.php';

// only the administrator can change what users can access
$user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) $_POST['user_id'];

    $stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$target || in_array($target['role'], $full_roles)) {
        $_SESSION['error'] = 'The access of this user cannot be changed.';
        header('Location: settings.php');
        exit;
    }

    // reset goes back to the role defaults, save stores the ticked options
    if (isset($_POST['reset'])) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET permissions = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $_SESSION['success'] = 'Access reset to the role defaults.';
    } else {
        $keep   = [];
        $ticked = $_POST['permissions'] ?? [];

        // keep a module only when its access radio says yes, then add its ticked options
        foreach ($modules as $module => $label) {
            if (($_POST['modules'][$module] ?? '0') !== '1') {
                continue;
            }
            $keep[] = $module;

            foreach ($module_options[$module] ?? [] as $items) {
                foreach ($items as $key => $name) {
                    if (in_array($key, $ticked)) {
                        $keep[] = $key;
                    }
                }
            }
        }
        // saved as a comma separated list
        $value = implode(',', $keep);

        $stmt = mysqli_prepare($conn, "UPDATE users SET permissions = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $value, $id);
        $_SESSION['success'] = 'Access saved.';
    }
    mysqli_stmt_execute($stmt);

    header('Location: settings.php?user=' . $id);
    exit;
}

// every user for the drop-down
$users = mysqli_fetch_all(
    mysqli_query($conn, "SELECT id, full_name, role, is_active, permissions FROM users ORDER BY full_name"),
    MYSQLI_ASSOC
);

// the user being edited, the first one in the list by default
$selected = null;
$wanted   = (int) ($_GET['user'] ?? 0);
foreach ($users as $u) {
    if ($u['id'] == $wanted) {
        $selected = $u;
    }
}
if (!$selected && $users) {
    $selected = $users[0];
}

// admin and ceo are locked because they always have full access
$is_full = $selected && in_array($selected['role'], $full_roles);
$current = $selected ? user_permissions($selected) : [];
$disabled = $is_full ? 'disabled' : '';

$page_title = 'Settings';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>User Access</h2>
</div>

<div class="panel">
    <form method="get" action="settings.php" class="toolbar">
        <label for="user">User</label>
        <select id="user" name="user" class="form-control" data-autosubmit>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>"<?= ($selected && $u['id'] == $selected['id']) ? ' selected' : '' ?>>
                    <?= e($u['full_name']) ?> - <?= e($roles[$u['role']]) ?><?= $u['is_active'] ? '' : ' (inactive)' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-outline">Open</button></noscript>
    </form>
</div>

<?php if ($selected): ?>
<form method="post" action="settings.php" class="panel panel-flush<?= $is_full ? ' locked' : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= (int) $selected['id'] ?>">

    <?php if ($is_full): ?>
        <p class="panel-note">The <?= e($roles[$selected['role']]) ?> has full access, including every type of report. This cannot be changed.</p>
    <?php endif; ?>

    <div class="tabs">
        <?php $first = true; ?>
        <?php foreach ($modules as $module => $label): ?>
            <button type="button" class="tab<?= $first ? ' active' : '' ?>" data-tab="tab-<?= e($module) ?>"><?= e($label) ?></button>
            <?php $first = false; ?>
        <?php endforeach; ?>
    </div>

    <?php $first = true; ?>
    <?php foreach ($modules as $module => $label): ?>
    <div class="tab-panel<?= $first ? ' active' : '' ?>" id="tab-<?= e($module) ?>">

        <div class="setting-row">
            <div class="setting-label">Access</div>
            <div class="setting-options">
                <label class="option">
                    <input type="radio" name="modules[<?= e($module) ?>]" value="1" data-access="<?= e($module) ?>" <?= in_array($module, $current) ? 'checked' : '' ?> <?= $disabled ?>>
                    <span>Access to <?= e(strtolower($label)) ?></span>
                </label>
                <label class="option">
                    <input type="radio" name="modules[<?= e($module) ?>]" value="0" data-access="<?= e($module) ?>" <?= in_array($module, $current) ? '' : 'checked' ?> <?= $disabled ?>>
                    <span>No access to <?= e(strtolower($label)) ?></span>
                </label>
            </div>
        </div>

        <?php foreach ($module_options[$module] ?? [] as $title => $items): ?>
        <div class="setting-row">
            <div class="setting-label"><?= e($title) ?></div>
            <div class="setting-options">
                <?php foreach ($items as $key => $name): ?>
                <label class="option">
                    <input type="checkbox" name="permissions[]" value="<?= e($key) ?>" data-module="<?= e($module) ?>" <?= in_array($key, $current) ? 'checked' : '' ?> <?= $disabled ?>>
                    <span><?= e($name) ?></span>
                </label>
                <?php endforeach; ?>
                <?php if ($module === 'reports'): ?>
                    <p class="option-hint">Only the Administrator and the CEO can open every type of report.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php $first = false; ?>
    <?php endforeach; ?>

    <?php if (!$is_full): ?>
    <div class="panel-footer">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="submit" name="reset" value="1" class="btn btn-outline" data-confirm-click="Reset this user to the default access for the role?">Reset to Defaults</button>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
