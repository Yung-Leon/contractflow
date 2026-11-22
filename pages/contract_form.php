<?php
include '../includes/auth.php';
include '../includes/contracts.php';

$user = require_permission('contracts');

// only department officers raise service requests
if ($user['role'] !== 'department_officer' || !$user['department_id']) {
    $_SESSION['error'] = 'Only a department officer can raise a service request.';
    header('Location: contracts.php');
    exit;
}

$id      = (int) ($_GET['id'] ?? 0);
$from    = (int) ($_GET['from'] ?? 0);
$editing = $id > 0;
$errors  = [];

// the values shown in the form
$form = [
    'title' => '', 'contract_type' => '', 'description' => '',
    'estimated_budget' => '', 'currency' => 'KES', 'needed_by' => '',
];
$renewed_from = null;

if ($editing) {
    // a request can only be edited while it is a draft or returned to the department
    $contract = get_contract($conn, $id, $user);
    if (!$contract || !can_edit($contract, $user)) {
        $_SESSION['error'] = 'This request cannot be edited.';
        header('Location: contracts.php');
        exit;
    }
    foreach ($form as $key => $value) {
        $form[$key] = (string) $contract[$key];
    }
} else {
    if (!can($user, 'contract_create')) {
        $_SESSION['error'] = 'You do not have permission to raise a request.';
        header('Location: contracts.php');
        exit;
    }

    // renewing an old contract or starting again from a rejected one: copy its details
    if ($from > 0) {
        $source = get_contract($conn, $from, $user);
        if ($source && can_start_new($source, $user)) {
            foreach (['title', 'contract_type', 'description', 'currency'] as $key) {
                $form[$key] = (string) $source[$key];
            }
            $form['estimated_budget'] = (string) ($source['contract_value'] > 0 ? $source['contract_value'] : $source['estimated_budget']);
            $renewed_from = $from;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    if (!$editing) {
        $renewed_from = (int) ($_POST['renewed_from'] ?? 0) ?: null;
    }

    // check what was typed
    if (strlen($form['title']) < 3) {
        $errors[] = 'Please enter the service you need.';
    }
    if (!in_array($form['contract_type'], $contract_types)) {
        $errors[] = 'Please choose a category.';
    }
    if (strlen($form['description']) < 10) {
        $errors[] = 'Please describe the service in at least a few words.';
    }
    if (!is_numeric($form['estimated_budget']) || $form['estimated_budget'] < 0) {
        $errors[] = 'The estimated budget must be a number, zero or more.';
    }
    if (!in_array($form['currency'], $currencies)) {
        $errors[] = 'Please choose a currency.';
    }
    if (!valid_date($form['needed_by'])) {
        $errors[] = 'Please enter the date the service is needed by.';
    }

    if (!$errors) {
        $budget = (float) $form['estimated_budget'];

        if ($editing) {
            $stmt = mysqli_prepare($conn,
                "UPDATE contracts SET title = ?, contract_type = ?, description = ?, estimated_budget = ?, currency = ?, needed_by = ?
                 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssdssi", $form['title'], $form['contract_type'], $form['description'],
                $budget, $form['currency'], $form['needed_by'], $id);
            mysqli_stmt_execute($stmt);

            $contract_id = $id;
            log_activity($conn, $user['id'], $contract_id, 'request_edited', $contract['reference_no']);
        } else {
            // the reference uses the department code
            $stmt = mysqli_prepare($conn, "SELECT code FROM departments WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user['department_id']);
            mysqli_stmt_execute($stmt);
            $row       = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $dept_code = $row['code'] ?? '';

            if ($dept_code === '') {
                $errors[] = 'Your department has no short code yet. Ask the administrator to add one.';
            } else {
                // try again if two people save at the same moment and get the same number
                $saved = false;
                for ($try = 0; $try < 3 && !$saved; $try++) {
                    $reference = make_reference($conn, $dept_code);
                    try {
                        $stmt = mysqli_prepare($conn,
                            "INSERT INTO contracts (reference_no, title, contract_type, description, estimated_budget, currency,
                                    needed_by, department_id, created_by, renewed_from)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssssdssiii", $reference, $form['title'], $form['contract_type'],
                            $form['description'], $budget, $form['currency'], $form['needed_by'],
                            $user['department_id'], $user['id'], $renewed_from);
                        mysqli_stmt_execute($stmt);
                        $saved = true;
                    } catch (mysqli_sql_exception $e) {
                        // the reference was taken a moment ago, the loop makes a new one
                    }
                }
                if ($saved) {
                    $contract_id = mysqli_insert_id($conn);
                    log_activity($conn, $user['id'], $contract_id, 'request_created', $reference);
                } else {
                    $errors[] = 'The request could not be saved. Please try again.';
                }
            }
        }

        if (!$errors) {
            // save the supporting document if one was chosen
            $upload_note = '';
            $file = $_FILES['document'] ?? null;
            if (has_file($file) && can($user, 'document_upload')) {
                $problem = save_upload($conn, $contract_id, $file, 'request', $user['id']);
                if ($problem !== '') {
                    $upload_note = ' The document was not uploaded: ' . $problem;
                }
            }

            $_SESSION['success'] = ($editing ? 'Request updated.' : 'Request saved. Review it and submit it to Procurement.') . $upload_note;
            header('Location: contract_view.php?id=' . $contract_id);
            exit;
        }
    }
}

$page_title = $editing ? 'Edit Request' : 'New Service Request';
include '../includes/header.php';
?>

<div class="page-head">
    <h2><?= $editing ? 'Edit ' . e($contract['reference_no']) : 'New Service Request' ?></h2>
    <a href="<?= $editing ? 'contract_view.php?id=' . $id : 'contracts.php' ?>" class="btn btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back
    </a>
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
    <form method="post" action="contract_form.php<?= $editing ? '?id=' . $id : '' ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($renewed_from): ?>
            <input type="hidden" name="renewed_from" value="<?= (int) $renewed_from ?>">
        <?php endif; ?>
        <?php if (isset($source) && $renewed_from): ?>
            <p class="muted-text">The details were copied from <?= e($source['reference_no']) ?>. Change anything that needs updating.</p>
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="title">Service needed</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="For example: Office cleaning services" value="<?= e($form['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="contract_type">Category</label>
                <select id="contract_type" name="contract_type" class="form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($contract_types as $type): ?>
                        <option value="<?= e($type) ?>"<?= $form['contract_type'] === $type ? ' selected' : '' ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="estimated_budget">Estimated budget</label>
                <div class="input-pair">
                    <select name="currency" class="form-control">
                        <?php foreach ($currencies as $cur): ?>
                            <option value="<?= e($cur) ?>"<?= $form['currency'] === $cur ? ' selected' : '' ?>><?= e($cur) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="estimated_budget" name="estimated_budget" class="form-control" min="0" step="0.01" value="<?= e($form['estimated_budget']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="needed_by">Needed by</label>
                <input type="date" id="needed_by" name="needed_by" class="form-control" value="<?= e($form['needed_by']) ?>" required>
            </div>
        </div>

        <div class="form-group top-gap">
            <label for="description">Description of the service</label>
            <textarea id="description" name="description" class="form-control" rows="4" required><?= e($form['description']) ?></textarea>
            <span class="hint">Say what is needed, where, for how long and any special requirements.</span>
        </div>

        <?php if (can($user, 'document_upload')): ?>
        <div class="form-group">
            <label for="document">Supporting document (optional)</label>
            <input type="file" id="document" name="document" class="form-control" accept=".pdf,.doc,.docx">
            <span class="hint">PDF, DOC or DOCX, up to 5 MB.</span>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Save Request' ?></button>
            <a href="<?= $editing ? 'contract_view.php?id=' . $id : 'contracts.php' ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
