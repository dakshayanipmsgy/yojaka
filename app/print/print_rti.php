<?php
$caseId = (string) ($id ?? '');
$cases = load_rti_cases();
$case = find_rti_by_id($cases, $caseId);

if (!$case) {
    http_response_code(404);
    echo 'RTI case not found.';
    exit;
}

$pageSize = yojaka_print_page_size();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print RTI Case</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/css/print.css">
    <style>
        @page { size: <?= htmlspecialchars($pageSize); ?> portrait; }
    </style>
</head>
<body class="print-document">
    <div class="document-container">
        <h1>RTI Case #<?= htmlspecialchars($case['id'] ?? ''); ?></h1>
        <div class="meta-grid">
            <div><strong>Reference Number:</strong> <?= htmlspecialchars($case['reference_number'] ?? ''); ?></div>
            <div><strong>Applicant:</strong> <?= htmlspecialchars($case['applicant_name'] ?? ''); ?></div>
            <div><strong>Subject:</strong> <?= htmlspecialchars($case['subject'] ?? ''); ?></div>
            <div><strong>Date of Receipt:</strong> <?= htmlspecialchars($case['date_of_receipt'] ?? ''); ?></div>
            <div><strong>Reply Deadline:</strong> <?= htmlspecialchars($case['reply_deadline'] ?? ''); ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars($case['status'] ?? ''); ?></div>
            <div><strong>Assigned To:</strong> <?= htmlspecialchars($case['assigned_to'] ?? ''); ?></div>
            <div><strong>Workflow State:</strong> <?= htmlspecialchars($case['workflow_state'] ?? ''); ?></div>
            <div><strong>Created At:</strong> <?= htmlspecialchars($case['created_at'] ?? ''); ?></div>
            <div><strong>Updated At:</strong> <?= htmlspecialchars($case['updated_at'] ?? ''); ?></div>
        </div>

        <h2>Details</h2>
        <p><?= nl2br(htmlspecialchars($case['details'] ?? '')); ?></p>

        <?php if (!empty($case['reply_summary'])): ?>
            <h2>Reply</h2>
            <div class="meta-grid">
                <div><strong>Reply Date:</strong> <?= htmlspecialchars($case['reply_date'] ?? ''); ?></div>
                <div><strong>Reply Summary:</strong> <?= nl2br(htmlspecialchars($case['reply_summary'] ?? '')); ?></div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
