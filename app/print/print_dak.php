<?php
$dakId = (string) ($id ?? '');
$entries = load_dak_entries();
$entry = null;
foreach ($entries as $item) {
    if (($item['id'] ?? '') === $dakId) {
        $entry = $item;
        break;
    }
}

if (!$entry) {
    http_response_code(404);
    echo 'Dak entry not found.';
    exit;
}

$pageSize = yojaka_print_page_size();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Dak Entry</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/css/print.css">
    <style>
        @page { size: <?= htmlspecialchars($pageSize); ?> portrait; }
    </style>
</head>
<body class="print-document">
    <div class="document-container">
        <h1>Dak Entry #<?= htmlspecialchars($entry['id'] ?? ''); ?></h1>
        <div class="meta-grid">
            <div><strong>Reference No:</strong> <?= htmlspecialchars($entry['reference_no'] ?? ''); ?></div>
            <div><strong>Received From:</strong> <?= htmlspecialchars($entry['received_from'] ?? ''); ?></div>
            <div><strong>Subject:</strong> <?= htmlspecialchars($entry['subject'] ?? ''); ?></div>
            <div><strong>Date Received:</strong> <?= htmlspecialchars($entry['date_received'] ?? ''); ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars($entry['status'] ?? ''); ?></div>
            <div><strong>Assigned To:</strong> <?= htmlspecialchars($entry['assigned_to'] ?? ''); ?></div>
            <div><strong>Current Holder:</strong> <?= htmlspecialchars($entry['current_holder'] ?? ($entry['assigned_to'] ?? '')); ?></div>
            <div><strong>Workflow State:</strong> <?= htmlspecialchars($entry['workflow_state'] ?? ''); ?></div>
            <div><strong>Created At:</strong> <?= htmlspecialchars($entry['created_at'] ?? ''); ?></div>
            <div><strong>Updated At:</strong> <?= htmlspecialchars($entry['updated_at'] ?? ''); ?></div>
        </div>

        <?php if (!empty($entry['details'])): ?>
            <h2>Details</h2>
            <p><?= nl2br(htmlspecialchars($entry['details'] ?? '')); ?></p>
        <?php endif; ?>

        <?php $movements = $entry['movements'] ?? []; ?>
        <?php if (!empty($movements)): ?>
            <h2>Movement History</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Action</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $move): ?>
                        <tr>
                            <td><?= htmlspecialchars(format_date_for_display($move['timestamp'] ?? '')); ?></td>
                            <td><?= htmlspecialchars($move['from_user'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($move['to_user'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($move['action'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($move['remark'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
