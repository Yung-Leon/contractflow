<?php
include '../includes/auth.php';
include '../includes/contracts.php';

// needs the documents permission
$user = require_permission('documents');

$id = (int) ($_GET['id'] ?? 0);

// find the document, but only if the user is allowed to see its contract
$stmt = mysqli_prepare($conn,
    "SELECT d.stored_name, d.original_name, d.mime_type
     FROM contract_documents d
     JOIN contracts c ON c.id = d.contract_id
     WHERE d.id = ? AND " . contract_scope($user));
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$path = $doc ? upload_folder() . $doc['stored_name'] : '';

if (!$doc || !is_file($path)) {
    $_SESSION['error'] = 'That document was not found.';
    header('Location: documents.php');
    exit;
}

// send the file as a download
header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $doc['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
