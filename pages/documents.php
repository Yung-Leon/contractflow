<?php
include '../includes/auth.php';
include '../includes/contracts.php';

$user = require_permission('documents');

// all documents of the contracts this user can see
$result = mysqli_query($conn,
    "SELECT d.id, d.version_no, d.doc_type, d.original_name, d.file_size, d.uploaded_at,
            c.id AS contract_id, c.reference_no, c.title
     FROM contract_documents d
     JOIN contracts c ON c.id = d.contract_id
     WHERE " . contract_scope($user) . "
     ORDER BY d.uploaded_at DESC
     LIMIT 100");
$documents = mysqli_fetch_all($result, MYSQLI_ASSOC);

$page_title = 'Documents';
include '../includes/header.php';
?>

<div class="page-head">
    <h2>Documents</h2>
</div>

<div class="panel">
    <table class="table">
        <thead>
            <tr><th>File</th><th>Contract</th><th>Type</th><th>Version</th><th>Uploaded</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($documents as $doc): ?>
            <tr>
                <td>
                    <?= e($doc['original_name']) ?>
                    <div class="muted"><?= number_format($doc['file_size'] / 1024, 0) ?> KB</div>
                </td>
                <td>
                    <a href="contract_view.php?id=<?= (int) $doc['contract_id'] ?>" class="link"><?= e($doc['reference_no']) ?></a>
                    <div class="muted"><?= e($doc['title']) ?></div>
                </td>
                <td><?= $doc['doc_type'] === 'signed' ? 'Signed copy' : 'Contract document' ?></td>
                <td>v<?= (int) $doc['version_no'] ?></td>
                <td><?= e(date('d M Y', strtotime($doc['uploaded_at']))) ?></td>
                <td><a href="download.php?id=<?= (int) $doc['id'] ?>" class="btn btn-outline btn-sm">Download</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$documents): ?>
            <tr><td colspan="6" class="empty">No documents yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
