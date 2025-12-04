<?php
$downloadData = $_SESSION['last_letter_download'] ?? null;
if (!$downloadData) {
    http_response_code(404);
    echo 'No generated letter available for printing.';
    exit;
}

$pageSize = yojaka_print_page_size();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Letter</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/css/print.css">
    <style>
        @page { size: <?= htmlspecialchars($pageSize); ?> portrait; }
    </style>
</head>
<body class="print-document">
    <div class="document-container">
        <h1><?= htmlspecialchars($downloadData['template_name'] ?? 'Letter'); ?></h1>
        <div class="meta-grid">
            <div><strong>Generated At:</strong> <?= htmlspecialchars($downloadData['generated_at'] ?? ''); ?></div>
        </div>
        <div class="print-body">
            <?= $downloadData['content'] ?? ''; ?>
        </div>
    </div>
</body>
</html>
