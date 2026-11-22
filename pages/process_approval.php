<?php
include '../includes/auth.php';
include '../includes/contracts.php';

// this page only handles form posts, nobody opens it directly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contracts.php');
    exit;
}
csrf_check();

$user     = require_permission('contracts');
$id       = (int) ($_POST['id'] ?? 0);
$action   = $_POST['action'] ?? '';
$contract = get_contract($conn, $id, $user);

if (!$contract) {
    $_SESSION['error'] = 'That contract was not found.';
    header('Location: contracts.php');
    exit;
}

$ref = $contract['reference_no'];

// go back to the contract with a message
function back($id, $type, $text)
{
    $_SESSION[$type] = $text;
    header('Location: contract_view.php?id=' . $id);
    exit;
}

function set_status($conn, $id, $status)
{
    $stmt = mysqli_prepare($conn, "UPDATE contracts SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    mysqli_stmt_execute($stmt);
}

// ---------- the department sends the request to Procurement ----------
if ($action === 'submit') {
    if (!can_submit($contract, $user)) {
        back($id, 'error', 'You cannot submit this request.');
    }

    set_status($conn, $id, 'procurement_review');
    notify_role($conn, 'procurement_officer', $id, $ref . ': a service request is waiting for procurement.');
    log_activity($conn, $user['id'], $id, 'request_submitted', $ref);
    back($id, 'success', 'The request was sent to Procurement.');
}

// ---------- forward, return or reject at the current stage ----------
if (in_array($action, ['approve', 'return', 'reject'])) {
    // only the role that owns the stage can act, the buttons alone do not decide this
    if (!can_decide($contract, $user)) {
        back($id, 'error', 'You cannot act on this contract at this stage.');
    }

    $stage   = $contract['status'];
    $comment = trim($_POST['comment'] ?? '');
    $file    = $_FILES['document'] ?? null;

    if ($action !== 'approve' && strlen($comment) < 3) {
        back($id, 'error', 'Please write a comment when you return or reject.');
    }

    // when forwarding, check the details this stage has to provide
    $save = [];
    if ($action === 'approve') {
        $doc_type = $stage_doc[$stage] ?? null;

        if ($stage === 'procurement_review') {
            // the company that was awarded the work and the value it was awarded at
            $award = (int) ($_POST['contractor_id'] ?? 0);
            $stmt  = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'contractor' AND is_active = 1");
            mysqli_stmt_bind_param($stmt, "i", $award);
            mysqli_stmt_execute($stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
                back($id, 'error', 'Choose the company that was awarded the contract.');
            }

            $value    = $_POST['contract_value'] ?? '';
            $currency = $_POST['currency'] ?? '';
            if (!is_numeric($value) || $value < 0 || !in_array($currency, $currencies)) {
                back($id, 'error', 'Enter the awarded value and choose a currency.');
            }
            $save = ['contractor_id' => $award, 'contract_value' => (float) $value, 'currency' => $currency];
        }

        if ($stage === 'legal_review') {
            $start = $_POST['start_date'] ?? '';
            $end   = $_POST['end_date'] ?? '';
            if (!valid_date($start) || !valid_date($end) || $end < $start) {
                back($id, 'error', 'Enter a start date and an end date that comes after it.');
            }
            $save = ['start_date' => $start, 'end_date' => $end];
        }

        // the document for this stage: a new file, or one uploaded earlier
        if ($doc_type !== null && !has_file($file) && count_documents($conn, $id, $doc_type) === 0) {
            $needed = ['procurement' => 'procurement document', 'contract' => 'contract terms document', 'signed' => 'signed copy'];
            back($id, 'error', 'Upload the ' . $needed[$doc_type] . ' before you continue.');
        }
        if ($doc_type !== null && has_file($file)) {
            $problem = save_upload($conn, $id, $file, $doc_type, $user['id']);
            if ($problem !== '') {
                back($id, 'error', $problem);
            }
        }

        // save the details of this stage
        if ($stage === 'procurement_review') {
            $stmt = mysqli_prepare($conn, "UPDATE contracts SET contractor_id = ?, contract_value = ?, currency = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "idsi", $save['contractor_id'], $save['contract_value'], $save['currency'], $id);
            mysqli_stmt_execute($stmt);
        }
        if ($stage === 'legal_review') {
            $stmt = mysqli_prepare($conn, "UPDATE contracts SET start_date = ?, end_date = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $save['start_date'], $save['end_date'], $id);
            mysqli_stmt_execute($stmt);
        }
    }

    $decisions = ['approve' => 'approved', 'return' => 'returned', 'reject' => 'rejected'];
    $decision  = $decisions[$action];
    $note      = ($comment !== '') ? $comment : null;

    // save the decision in the history
    $stmt = mysqli_prepare($conn, "INSERT INTO approvals (contract_id, stage, approver_id, decision, comments) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isiss", $id, $stage, $user['id'], $decision, $note);
    mysqli_stmt_execute($stmt);

    // work out the new status
    if ($action === 'approve') {
        $new = $stages[$stage]['next'];
    } elseif ($action === 'return') {
        $new = $stages[$stage]['back'];
    } else {
        $new = 'rejected';
    }
    set_status($conn, $id, $new);

    // tell the right people
    if ($action === 'approve') {
        if ($new === 'active') {
            notify_user($conn, $contract['created_by'], $id, $ref . ' was signed and is now active.');
        } else {
            notify_user($conn, $contract['created_by'], $id, $ref . ' moved to ' . $statuses[$new] . '.');
        }

        // whoever acts next gets a notification
        if ($new === 'contractor_signing') {
            $contractor_id = $save['contractor_id'] ?? $contract['contractor_id'];
            if ($contractor_id) {
                notify_user($conn, $contractor_id, $id, $ref . ' is ready for your signature.');
            }
        } elseif (isset($stages[$new])) {
            notify_role($conn, $stages[$new]['role'], $id, $ref . ' is waiting for your action.');
        }
    } elseif ($action === 'return') {
        if ($new === 'returned') {
            notify_user($conn, $contract['created_by'], $id, $ref . ' was returned to you: ' . $comment);
        } else {
            notify_role($conn, $stages[$new]['role'], $id, $ref . ' was returned to you: ' . $comment);
        }
    } else {
        notify_user($conn, $contract['created_by'], $id, $ref . ' was rejected: ' . $comment);
    }

    log_activity($conn, $user['id'], $id, 'contract_' . $decision, $statuses[$stage]);
    back($id, 'success', 'Done. The contract is now at: ' . $statuses[$new] . '.');
}

// ---------- end an active contract early ----------
if ($action === 'terminate') {
    if (!can_terminate($contract, $user)) {
        back($id, 'error', 'You cannot terminate this contract.');
    }

    $comment = trim($_POST['comment'] ?? '');
    if (strlen($comment) < 3) {
        back($id, 'error', 'Please give a reason for ending the contract.');
    }

    set_status($conn, $id, 'terminated');
    notify_user($conn, $contract['created_by'], $id, $ref . ' was terminated: ' . $comment);
    log_activity($conn, $user['id'], $id, 'contract_terminated', $comment);
    back($id, 'success', 'The contract was terminated.');
}

back($id, 'error', 'Nothing was done.');
