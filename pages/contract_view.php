<?php
include '../includes/auth.php';
include '../includes/contracts.php';

$user = require_permission('contracts');

// load the contract, this also checks the user is allowed to see it
$id       = (int) ($_GET['id'] ?? 0);
$contract = get_contract($conn, $id, $user);

if (!$contract) {
    $_SESSION['error'] = 'That contract was not found.';
    header('Location: contracts.php');
    exit;
}

// documents, newest first
$stmt = mysqli_prepare($conn,
    "SELECT d.id, d.version_no, d.doc_type, d.original_name, d.file_size, d.uploaded_at
     FROM contract_documents d
     WHERE d.contract_id = ?
     ORDER BY d.id DESC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$documents = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// the approval history
$stmt = mysqli_prepare($conn,
    "SELECT a.stage, a.decision, a.comments, a.decided_at, u.full_name
     FROM approvals a
     JOIN users u ON u.id = a.approver_id
     WHERE a.contract_id = ?
     ORDER BY a.id DESC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$history = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// what this user can do with the contract right now
$can_edit      = can_edit($contract, $user);
$can_submit    = can_submit($contract, $user);
$can_decide    = can_decide($contract, $user);
$can_terminate = can_terminate($contract, $user);
$can_start_new = can_start_new($contract, $user);
$stage         = $contract['status'];

// work out what the single action form shows
$box = null;
if ($can_submit) {
    $box = [
        'title'   => $stage === 'returned' ? 'Revise and resubmit' : 'Submit the request',
        'intro'   => 'Check the details, then send the request to the Procurement Officer. You cannot edit it while it is with them.',
        'options' => ['submit' => 'Submit to Procurement'],
        'button'  => 'Submit Request',
    ];
} elseif ($can_decide) {
    $box = [
        'title'   => $stage_text[$stage]['title'],
        'intro'   => '',
        'options' => [
            'approve' => $stage_text[$stage]['approve'],
            'return'  => $stage_text[$stage]['return'],
            'reject'  => $stage_text[$stage]['reject'],
        ],
        'button'  => 'Submit',
    ];
} elseif ($can_terminate) {
    $box = [
        'title'   => 'Terminate contract',
        'intro'   => 'Use this only to end an active contract early.',
        'options' => ['terminate' => 'Terminate this contract'],
        'button'  => 'Terminate Contract',
    ];
}

// companies that can be awarded the contract, only needed at the procurement stage
$contractors = [];
if ($can_decide && $stage === 'procurement_review') {
    $contractors = mysqli_fetch_all(
        mysqli_query($conn, "SELECT id, full_name, company_name FROM users WHERE role = 'contractor' AND is_active = 1 ORDER BY company_name, full_name"),
        MYSQLI_ASSOC
    );
}

$awarded = $contract['contractor_company']
    ? $contract['contractor_company'] . ' (' . $contract['contractor_name'] . ')'
    : ($contract['contractor_name'] ?: '');

$page_title = 'Contract Details';
include '../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2><?= e($contract['title']) ?></h2>
        <div class="muted"><?= e($contract['reference_no']) ?></div>
    </div>
    <div class="actions">
        <span class="badge <?= status_class($stage) ?>"><?= e($statuses[$stage]) ?></span>
        <?php if ($can_edit): ?>
            <a href="contract_form.php?id=<?= $id ?>" class="btn btn-outline">Edit</a>
        <?php endif; ?>
        <?php if ($can_start_new): ?>
            <a href="contract_form.php?from=<?= $id ?>" class="btn btn-outline">
                <?= $stage === 'rejected' ? 'Start a New Request' : 'Renew Contract' ?>
            </a>
        <?php endif; ?>
        <a href="contracts.php" class="btn btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back
        </a>
    </div>
</div>

<?php if ($stage === 'returned'): ?>
    <div class="alert alert-error">This request was returned. Read the comment in the history below, make the changes, then submit it again.</div>
<?php endif; ?>

<div class="panel sheet">

    <section>
        <h3>Request</h3>
        <dl class="detail-grid">
            <div><dt>Department</dt><dd><?= e($contract['department_name']) ?></dd></div>
            <div><dt>Raised by</dt><dd><?= e($contract['creator_name']) ?>, <?= e(date('d M Y', strtotime($contract['created_at']))) ?></dd></div>
            <div><dt>Category</dt><dd><?= e($contract['contract_type']) ?></dd></div>
            <div><dt>Estimated budget</dt><dd><?= e($contract['currency']) ?> <?= number_format($contract['estimated_budget'], 2) ?></dd></div>
            <div><dt>Needed by</dt><dd><?= $contract['needed_by'] ? e(date('d M Y', strtotime($contract['needed_by']))) : '-' ?></dd></div>
            <?php if ($contract['linked_reference']): ?>
                <div><dt>Linked to</dt><dd><a href="contract_view.php?id=<?= (int) $contract['renewed_from'] ?>" class="link"><?= e($contract['linked_reference']) ?></a></dd></div>
            <?php endif; ?>
            <div class="wide"><dt>Description</dt><dd class="pre"><?= e($contract['description'] ?: '-') ?></dd></div>
        </dl>
    </section>

    <?php if ($awarded !== ''): ?>
    <section>
        <h3>Award and contract</h3>
        <dl class="detail-grid">
            <div><dt>Awarded to</dt><dd><?= e($awarded) ?></dd></div>
            <div><dt>Awarded value</dt><dd><?= e($contract['currency']) ?> <?= number_format($contract['contract_value'], 2) ?></dd></div>
            <?php if ($contract['start_date'] && $contract['end_date']): ?>
                <div><dt>Contract period</dt><dd><?= e(date('d M Y', strtotime($contract['start_date']))) ?> to <?= e(date('d M Y', strtotime($contract['end_date']))) ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>
    <?php endif; ?>

    <section>
        <h3>Documents</h3>
        <table class="table">
            <thead>
                <tr><th>File</th><th>Type</th><th>Uploaded</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= e($doc['original_name']) ?></td>
                    <td><?= e($doc_labels[$doc['doc_type']] ?? 'Document') ?></td>
                    <td><?= e(date('d M Y', strtotime($doc['uploaded_at']))) ?></td>
                    <td class="row-action">
                        <?php if (can($user, 'documents')): ?>
                            <a href="download.php?id=<?= (int) $doc['id'] ?>" class="btn btn-outline btn-sm">Download</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?>
                <tr><td colspan="4" class="empty">No documents yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php if ($box): ?>
    <section class="sheet-action" id="action">
        <h3><?= e($box['title']) ?></h3>
        <?php if ($box['intro'] !== ''): ?>
            <p class="muted-text"><?= e($box['intro']) ?></p>
        <?php endif; ?>

        <form method="post" action="process_approval.php" enctype="multipart/form-data" class="action-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <?php if ($can_decide && isset($stage_doc[$stage])): ?>
            <div class="stage-fields">
                <?php if ($stage === 'procurement_review'): ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="contractor_id">Company awarded</label>
                            <select id="contractor_id" name="contractor_id" class="form-control">
                                <option value="">Select the company</option>
                                <?php foreach ($contractors as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === (int) $contract['contractor_id'] ? ' selected' : '' ?>>
                                        <?= e($c['company_name'] ? $c['company_name'] . ' - ' . $c['full_name'] : $c['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contract_value">Awarded value</label>
                            <div class="input-pair">
                                <select name="currency" class="form-control">
                                    <?php foreach ($currencies as $cur): ?>
                                        <option value="<?= e($cur) ?>"<?= $contract['currency'] === $cur ? ' selected' : '' ?>><?= e($cur) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" id="contract_value" name="contract_value" class="form-control" min="0" step="0.01" value="<?= $contract['contract_value'] > 0 ? e($contract['contract_value']) : '' ?>">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($stage === 'legal_review'): ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="start_date">Contract start date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= e($contract['start_date']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">Contract end date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= e($contract['end_date']) ?>">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <?php $labels = ['procurement_review' => 'Procurement document', 'legal_review' => 'Contract terms document', 'contractor_signing' => 'Signed copy of the contract']; ?>
                    <label for="document"><?= e($labels[$stage]) ?></label>
                    <input type="file" id="document" name="document" class="form-control" accept=".pdf,.doc,.docx">
                    <span class="hint">
                        PDF, DOC or DOCX, up to 5 MB.
                        <?php if (count_documents($conn, $id, $stage_doc[$stage]) > 0): ?>
                            One is already uploaded, choose a file only to replace it.
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($box['options']) > 1): ?>
                <div class="choice-list">
                    <?php foreach ($box['options'] as $value => $label): ?>
                        <label class="option choice">
                            <input type="radio" name="action" value="<?= e($value) ?>" required>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <input type="hidden" name="action" value="<?= e(array_key_first($box['options'])) ?>">
            <?php endif; ?>

            <?php if (!$can_submit): ?>
            <div class="form-group">
                <label for="comment"><?= $can_terminate && !$can_decide ? 'Reason' : 'Comment' ?></label>
                <textarea id="comment" name="comment" class="form-control" rows="3"></textarea>
                <?php if ($can_decide): ?>
                    <span class="hint">Required when you return or reject.</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= e($box['button']) ?></button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section>
        <h3>History</h3>
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Stage</th><th>By</th><th>Decision</th><th>Comment</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e(date('d M Y, H:i', strtotime($h['decided_at']))) ?></td>
                    <td><?= e($statuses[$h['stage']]) ?></td>
                    <td><?= e($h['full_name']) ?></td>
                    <td><span class="badge <?= $h['decision'] === 'approved' ? 'badge-active' : ($h['decision'] === 'returned' ? 'badge-pending' : 'badge-danger') ?>"><?= e($h['decision'] === 'approved' ? 'Forwarded' : ucfirst($h['decision'])) ?></span></td>
                    <td class="pre"><?= e($h['comments'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?>
                <tr><td colspan="5" class="empty">No decisions yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

</div>

<?php include '../includes/footer.php'; ?>
