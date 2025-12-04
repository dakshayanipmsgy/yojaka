<?php
$reportId = (string) ($id ?? '');
$reports = load_inspection_reports();
$report = null;
foreach ($reports as $item) {
    if (($item['id'] ?? '') === $reportId) {
        $report = $item;
        break;
    }
}

if (!$report) {
    http_response_code(404);
    echo 'Inspection report not found.';
    exit;
}

$template = null;
foreach (load_inspection_templates() as $tpl) {
    if (($tpl['id'] ?? '') === ($report['template_id'] ?? '')) {
        $template = $tpl;
        break;
    }
}

$pageSize = yojaka_print_page_size();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Inspection Report</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/css/print.css">
    <style>
        @page { size: <?= htmlspecialchars($pageSize); ?> portrait; }
    </style>
</head>
<body class="print-document">
    <div class="document-container">
        <h1>Inspection Report #<?= htmlspecialchars($report['id'] ?? ''); ?></h1>
        <div class="meta-grid">
            <div><strong>Template:</strong> <?= htmlspecialchars($report['template_name'] ?? ''); ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars($report['status'] ?? ''); ?></div>
            <div><strong>Created At:</strong> <?= htmlspecialchars($report['created_at'] ?? ''); ?></div>
            <div><strong>Updated At:</strong> <?= htmlspecialchars($report['updated_at'] ?? ''); ?></div>
        </div>

        <?php $fields = $report['fields'] ?? []; ?>
        <?php if (!empty($fields)): ?>
            <h2>Details</h2>
            <div class="meta-grid">
                <?php foreach ($fields as $name => $value): ?>
                    <?php
                    $label = $name;
                    if ($template) {
                        foreach ($template['fields'] ?? [] as $fieldDef) {
                            if (($fieldDef['name'] ?? '') === $name) {
                                $label = $fieldDef['label'] ?? $label;
                                break;
                            }
                        }
                    }
                    ?>
                    <div><strong><?= htmlspecialchars($label); ?>:</strong> <?= htmlspecialchars($value ?? ''); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $checklist = $report['checklist_statuses'] ?? []; ?>
        <?php if (!empty($checklist)): ?>
            <h2>Checklist</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:10%">Sl</th>
                        <th>Item</th>
                        <th style="width:15%">Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checklist as $idx => $item): ?>
                        <?php
                        $label = $item['code'] ?? '';
                        if ($template) {
                            foreach ($template['checklist'] ?? [] as $chk) {
                                if (($chk['code'] ?? '') === ($item['code'] ?? '')) {
                                    $label = $chk['label'] ?? $label;
                                    break;
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td><?= (int) ($idx + 1); ?></td>
                            <td><?= htmlspecialchars($label); ?></td>
                            <td><?= htmlspecialchars($item['status'] ?? ''); ?></td>
                            <td><?= nl2br(htmlspecialchars($item['remarks'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php $photos = $report['photos'] ?? []; ?>
        <?php if (!empty($photos)): ?>
            <h2>Photos</h2>
            <ul>
                <?php foreach ($photos as $photo): ?>
                    <li><strong><?= htmlspecialchars($photo['label'] ?? ''); ?>:</strong> <?= htmlspecialchars($photo['path_or_ref'] ?? ''); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($template['footer_note'])): ?>
            <div class="muted" style="margin-top:1rem;"><?= htmlspecialchars($template['footer_note']); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
