<?php
$recordId = (string) ($id ?? '');
$records = load_document_records('work_order');
$record = null;
foreach ($records as $item) {
    if (($item['id'] ?? '') === $recordId) {
        $record = $item;
        break;
    }
}

if (!$record) {
    http_response_code(404);
    echo 'Work order not found.';
    exit;
}

$pageSize = yojaka_print_page_size();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Work Order</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/css/print.css">
    <style>
        @page { size: <?= htmlspecialchars($pageSize); ?> portrait; }
    </style>
</head>
<body class="print-document">
    <div class="document-container">
        <h1>Work Order #<?= htmlspecialchars($record['id'] ?? ''); ?></h1>
        <div class="meta-grid">
            <div><strong>Template:</strong> <?= htmlspecialchars($record['template_name'] ?? ''); ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars($record['status'] ?? ''); ?></div>
            <div><strong>Created At:</strong> <?= htmlspecialchars($record['created_at'] ?? ''); ?></div>
        </div>
        <div class="print-body">
            <?= $record['rendered_body'] ?? ''; ?>
        </div>
    </div>
</body>
</html>
