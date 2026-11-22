<?php
// Everything the contract pages share: the workflow rules, who can see
// which contracts, file uploads and notifications.

// names shown for each status
$statuses = [
    'draft'              => 'Draft',
    'procurement_review' => 'Procurement',
    'legal_review'       => 'Legal Drafting',
    'legal_approval'     => 'Legal Approval',
    'executive_approval' => 'Executive Approval',
    'contractor_signing' => 'Contractor Signing',
    'active'             => 'Active',
    'returned'           => 'Returned',
    'rejected'           => 'Rejected',
    'expired'            => 'Expired',
    'terminated'         => 'Terminated',
];

// the stages: who acts, where an approval sends the contract and where a return sends it
$stages = [
    'procurement_review' => ['role' => 'procurement_officer', 'next' => 'legal_review',       'back' => 'returned'],
    'legal_review'       => ['role' => 'legal_officer',       'next' => 'legal_approval',     'back' => 'procurement_review'],
    'legal_approval'     => ['role' => 'head_of_legal',       'next' => 'executive_approval', 'back' => 'legal_review'],
    'executive_approval' => ['role' => 'ceo',                 'next' => 'contractor_signing', 'back' => 'legal_approval'],
    'contractor_signing' => ['role' => 'contractor',          'next' => 'active',             'back' => 'legal_review'],
];

// the words shown on the action form and on the action button at each stage
$stage_text = [
    'procurement_review' => [
        'title'  => 'Process the request',
        'button' => 'Process Request',
        'approve' => 'Forward to Legal for contract drafting',
        'return'  => 'Return to the department',
        'reject'  => 'Reject the request',
    ],
    'legal_review' => [
        'title'  => 'Draft the contract',
        'button' => 'Draft Contract',
        'approve' => 'Send to the Head of Legal for approval',
        'return'  => 'Return to Procurement',
        'reject'  => 'Reject the request',
    ],
    'legal_approval' => [
        'title'  => 'Review the contract',
        'button' => 'Review Contract',
        'approve' => 'Approve and send to the CEO',
        'return'  => 'Return to Legal',
        'reject'  => 'Reject the request',
    ],
    'executive_approval' => [
        'title'  => 'Approve the contract',
        'button' => 'Approve Contract',
        'approve' => 'Approve and send to the contractor for signing',
        'return'  => 'Return to the Head of Legal',
        'reject'  => 'Reject the request',
    ],
    'contractor_signing' => [
        'title'  => 'Sign the contract',
        'button' => 'Sign Contract',
        'approve' => 'Sign and activate the contract',
        'return'  => 'Return to Legal with comments',
        'reject'  => 'Decline to sign',
    ],
];

// which kind of document each stage uploads
$stage_doc = [
    'procurement_review' => 'procurement',
    'legal_review'       => 'contract',
    'contractor_signing' => 'signed',
];

$doc_labels = [
    'request'     => 'Request document',
    'procurement' => 'Procurement document',
    'contract'    => 'Contract terms',
    'signed'      => 'Signed copy',
];

$contract_types = ['Supply', 'Services', 'Consultancy', 'Works', 'Lease', 'Other'];
$currencies     = ['KES', 'USD', 'EUR'];

// these roles see contracts from every department
$all_department_roles = ['admin', 'ceo', 'procurement_officer', 'legal_officer', 'head_of_legal'];

// colour of the status badge
function status_class($status)
{
    if ($status === 'active') {
        return 'badge-active';
    }
    if (in_array($status, ['rejected', 'terminated'])) {
        return 'badge-danger';
    }
    if (in_array($status, ['draft', 'expired'])) {
        return 'badge-inactive';
    }
    return 'badge-pending';
}

// the part of a query that limits which contracts this user may see
// (c is the contracts table). The ids are cast to numbers so it is safe.
function contract_scope($user)
{
    global $all_department_roles;

    if (in_array($user['role'], $all_department_roles)) {
        return '1 = 1';
    }
    if ($user['role'] === 'contractor') {
        return 'c.contractor_id = ' . (int) $user['id'];
    }
    return 'c.department_id = ' . (int) $user['department_id'];
}

// load one contract, but only if this user is allowed to see it
function get_contract($conn, $id, $user)
{
    $scope = contract_scope($user);

    $stmt = mysqli_prepare($conn,
        "SELECT c.*, d.name AS department_name, d.code AS department_code,
                cr.full_name AS creator_name,
                ct.full_name AS contractor_name, ct.company_name AS contractor_company,
                lk.reference_no AS linked_reference
         FROM contracts c
         JOIN departments d ON d.id = c.department_id
         JOIN users cr ON cr.id = c.created_by
         LEFT JOIN users ct ON ct.id = c.contractor_id
         LEFT JOIN contracts lk ON lk.id = c.renewed_from
         WHERE c.id = ? AND " . $scope);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// next reference for a department, like CF/ICT/2026/001
function make_reference($conn, $dept_code)
{
    $prefix = 'CF/' . $dept_code . '/' . date('Y') . '/';
    $like   = $prefix . '%';

    // find the highest running number used so far for this department and year
    $stmt = mysqli_prepare($conn,
        "SELECT MAX(CAST(SUBSTRING_INDEX(reference_no, '/', -1) AS UNSIGNED)) AS last_no
         FROM contracts WHERE reference_no LIKE ?");
    mysqli_stmt_bind_param($stmt, "s", $like);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $next = ((int) $row['last_no']) + 1;
    return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
}

// ---------- who can do what to a contract ----------

// the department officer who owns a draft or a returned contract
function is_my_draft($contract, $user)
{
    return $user['role'] === 'department_officer'
        && (int) $user['department_id'] === (int) $contract['department_id']
        && in_array($contract['status'], ['draft', 'returned']);
}

function can_edit($contract, $user)
{
    return is_my_draft($contract, $user) && can($user, 'contract_edit');
}

function can_submit($contract, $user)
{
    return is_my_draft($contract, $user) && (can($user, 'contract_edit') || can($user, 'contract_create'));
}

// true when the contract is waiting for this user to approve, return or reject it
function can_decide($contract, $user)
{
    global $stages;

    $rule = $stages[$contract['status']] ?? null;
    if (!$rule || $rule['role'] !== $user['role']) {
        return false;
    }
    // the contractor only signs their own contract, and does not need the approvals permission
    if ($user['role'] === 'contractor') {
        return (int) $contract['contractor_id'] === (int) $user['id'];
    }
    return can($user, 'approvals');
}

function can_terminate($contract, $user)
{
    return $contract['status'] === 'active' && in_array($user['role'], ['admin', 'ceo']);
}

// renew an old contract, or start again from a rejected one
function can_start_new($contract, $user)
{
    return $user['role'] === 'department_officer'
        && (int) $user['department_id'] === (int) $contract['department_id']
        && can($user, 'contract_create')
        && in_array($contract['status'], ['active', 'expired', 'rejected']);
}

// the name of the action button for this user, empty when there is nothing to do
function action_label($contract, $user)
{
    global $stage_text;

    if (can_submit($contract, $user)) {
        return ($contract['status'] === 'returned') ? 'Revise Request' : 'Submit Request';
    }
    if (can_decide($contract, $user)) {
        return $stage_text[$contract['status']]['button'];
    }
    return '';
}

// true when a file was chosen in the form
function has_file($file)
{
    return isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

// checks a date typed as yyyy-mm-dd
function valid_date($text)
{
    $d = DateTime::createFromFormat('Y-m-d', $text);
    return $d && $d->format('Y-m-d') === $text;
}

// how many documents of a type a contract has
function count_documents($conn, $contract_id, $type)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM contract_documents WHERE contract_id = ? AND doc_type = ?");
    mysqli_stmt_bind_param($stmt, "is", $contract_id, $type);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (int) $row['total'];
}

// the part of a query for contracts that are waiting for this user
function waiting_condition($user)
{
    global $stages;

    if ($user['role'] === 'department_officer') {
        return "c.department_id = " . (int) $user['department_id'] . " AND c.status IN ('draft', 'returned')";
    }
    if ($user['role'] === 'contractor') {
        return "c.contractor_id = " . (int) $user['id'] . " AND c.status = 'contractor_signing'";
    }
    foreach ($stages as $status => $rule) {
        if ($rule['role'] === $user['role']) {
            return "c.status = '" . $status . "'";
        }
    }
    return '0 = 1';
}

function count_waiting($conn, $user)
{
    $sql = "SELECT COUNT(*) AS total FROM contracts c WHERE " . contract_scope($user) . " AND " . waiting_condition($user);
    $row = mysqli_fetch_assoc(mysqli_query($conn, $sql));
    return (int) $row['total'];
}

// ---------- sidebar counts ----------

// contracts that end within the next 30 days
function expiring_condition()
{
    return "c.status = 'active' AND c.end_date IS NOT NULL AND c.end_date >= CURDATE() AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

// requests still going through approval whose needed-by date has passed
function overdue_condition()
{
    return "c.status IN ('draft', 'returned', 'procurement_review', 'legal_review', 'legal_approval', 'executive_approval', 'contractor_signing') AND c.needed_by IS NOT NULL AND c.needed_by < CURDATE()";
}

// the numbers shown next to the sidebar links
function sidebar_counts($conn, $user)
{
    $counts = ['waiting' => 0, 'expiring' => 0, 'overdue' => 0];

    if (can($user, 'approvals')) {
        $counts['waiting'] = count_waiting($conn, $user);
    }
    if (can($user, 'contracts')) {
        $sql = "SELECT SUM(CASE WHEN " . expiring_condition() . " THEN 1 ELSE 0 END) AS expiring,
                       SUM(CASE WHEN " . overdue_condition() . " THEN 1 ELSE 0 END) AS overdue
                FROM contracts c WHERE " . contract_scope($user);
        $row = mysqli_fetch_assoc(mysqli_query($conn, $sql));
        $counts['expiring'] = (int) $row['expiring'];
        $counts['overdue']  = (int) $row['overdue'];
    }
    return $counts;
}

// ---------- notifications and the activity log ----------

function notify_user($conn, $user_id, $contract_id, $message)
{
    $message = substr($message, 0, 250);

    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, contract_id, message) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iis", $user_id, $contract_id, $message);
    mysqli_stmt_execute($stmt);
}

// send the same message to everyone who has a role
function notify_role($conn, $role, $contract_id, $message)
{
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE role = ? AND is_active = 1");
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);

    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) {
        notify_user($conn, $row['id'], $contract_id, $message);
    }
}

function log_activity($conn, $user_id, $contract_id, $action, $details = '')
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = mysqli_prepare($conn,
        "INSERT INTO activity_logs (user_id, contract_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iisss", $user_id, $contract_id, $action, $details, $ip);
    mysqli_stmt_execute($stmt);
}

// ---------- expiry ----------

// marks old contracts as expired and warns people before a contract ends.
// It runs whenever someone opens the dashboard, contracts or notifications page.
function refresh_contracts($conn)
{
    // contracts past their end date become expired
    $result = mysqli_query($conn,
        "SELECT id, reference_no, created_by FROM contracts
         WHERE status = 'active' AND end_date IS NOT NULL AND end_date < CURDATE()");
    foreach (mysqli_fetch_all($result, MYSQLI_ASSOC) as $c) {
        mysqli_query($conn, "UPDATE contracts SET status = 'expired' WHERE id = " . (int) $c['id']);
        notify_user($conn, $c['created_by'], $c['id'], $c['reference_no'] . ' has expired.');
    }

    // contracts ending within 90 days get a warning at 90, 60, 30 and 7 days
    $result = mysqli_query($conn,
        "SELECT id, reference_no, created_by, contractor_id, DATEDIFF(end_date, CURDATE()) AS days_left
         FROM contracts
         WHERE status = 'active' AND end_date IS NOT NULL
           AND end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)");

    foreach (mysqli_fetch_all($result, MYSQLI_ASSOC) as $c) {
        // pick the smallest warning level that still applies
        $level = 90;
        foreach ([7, 30, 60] as $days) {
            if ($c['days_left'] <= $days) {
                $level = $days;
                break;
            }
        }
        $message = $c['reference_no'] . ' expires within ' . $level . ' days.';

        $people = array_unique(array_filter([(int) $c['created_by'], (int) $c['contractor_id']]));
        foreach ($people as $person) {
            // do not send the same warning twice
            $stmt = mysqli_prepare($conn, "SELECT id FROM notifications WHERE user_id = ? AND contract_id = ? AND message = ?");
            mysqli_stmt_bind_param($stmt, "iis", $person, $c['id'], $message);
            mysqli_stmt_execute($stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
                notify_user($conn, $person, $c['id'], $message);
            }
        }
    }
}

// ---------- file uploads ----------

// contract files are kept outside the web folder so nobody can open them by typing the address
function upload_folder()
{
    $folder = dirname(__DIR__, 3) . '/contractflow_files/';
    if (!is_dir($folder)) {
        @mkdir($folder, 0775, true);
    }
    return $folder;
}

// checks the uploaded file and saves it. Returns an error message, or '' when all went well.
function save_upload($conn, $contract_id, $file, $doc_type, $user_id)
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return 'The file is too large. The limit is 5 MB.';
    }
    if ($code !== UPLOAD_ERR_OK) {
        return 'Please choose a file to upload.';
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return 'The file is too large. The limit is 5 MB.';
    }

    // only pdf and word files
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'])) {
        return 'Only PDF, DOC and DOCX files are allowed.';
    }

    // check the real file type, not just the name
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-ole-storage',
        'application/CDFV2',
        'application/vnd.ms-office',
    ];
    if (!in_array($mime, $allowed)) {
        return 'That file type is not allowed.';
    }

    $folder = upload_folder();
    if (!is_dir($folder) || !is_writable($folder)) {
        return 'The upload folder could not be created. Check the folder permissions.';
    }

    // a random name, so the original name never decides where the file lives
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $folder . $stored)) {
        return 'The file could not be saved. Please try again.';
    }

    // next version number for this contract
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(MAX(version_no), 0) + 1 AS next_no FROM contract_documents WHERE contract_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $contract_id);
    mysqli_stmt_execute($stmt);
    $row     = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $version = (int) $row['next_no'];

    $original = substr(basename($file['name']), 0, 250);
    $size     = (int) $file['size'];

    $stmt = mysqli_prepare($conn,
        "INSERT INTO contract_documents (contract_id, version_no, doc_type, original_name, stored_name, file_size, mime_type, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iisssisi", $contract_id, $version, $doc_type, $original, $stored, $size, $mime, $user_id);
    mysqli_stmt_execute($stmt);

    return '';
}
