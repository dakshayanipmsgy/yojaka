<?php
require_once __DIR__ . '/print_layout.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';

$currentUser = get_current_user();

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

$report = acl_normalize($report);
if (!acl_can_view($currentUser, $report)) {
    http_response_code(403);
    echo 'You do not have access to this inspection report.';
    exit;
}

$template = null;
foreach (load_inspection_templates() as $tpl) {
    if (($tpl['id'] ?? '') === ($report['template_id'] ?? '')) {
        $template = $tpl;
        break;
    }
}

$officeId = get_current_office_id();
$status = strtolower((string) ($report['status'] ?? ''));
$watermarkOverride = $status === 'draft' ? 'DRAFT' : null;

$verificationString = !empty($report['id']) ? YOJAKA_BASE_URL . '/portal.php?action=verify&type=inspection&id=' . urlencode((string) $report['id']) : '';
$qrImage = $verificationString !== '' ? print_qr_data_uri($verificationString) : null;

ob_start();
?>
<div class="document-title">Inspection Report</div>

<table class="meta-table">
    <tr>
        <td><strong>Report ID:</strong> <?= htmlspecialchars($report['id'] ?? ''); ?></td>
        <td><strong>Status:</strong> <?= htmlspecialchars($report['status'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Template:</strong> <?= htmlspecialchars($report['template_name'] ?? ''); ?></td>
        <td><strong>Updated At:</strong> <?= htmlspecialchars($report['updated_at'] ?? $report['created_at'] ?? ''); ?></td>
    </tr>
</table>

<?php $fields = $report['fields'] ?? []; ?>
<?php if (!empty($fields)): ?>
    <h4>Inspection Details</h4>
    <table class="meta-table">
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
            <tr>
                <td style="width:35%"><strong><?= htmlspecialchars($label); ?>:</strong></td>
                <td><?= nl2br(htmlspecialchars((string) $value)); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php $checklist = $report['checklist_statuses'] ?? []; ?>
<?php if (!empty($checklist)): ?>
    <h4>Checklist</h4>
    <table class="content-table">
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
    <h4>Photos</h4>
    <table class="meta-table">
        <?php foreach ($photos as $photo): ?>
            <tr>
                <td style="width:35%"><strong><?= htmlspecialchars($photo['label'] ?? ''); ?>:</strong></td>
                <td><?= htmlspecialchars($photo['path_or_ref'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if (!empty($template['footer_note'])): ?>
    <p><?= htmlspecialchars($template['footer_note']); ?></p>
<?php endif; ?>

<div class="signature-block">
    <div class="signature-line"></div>
    <div>Inspection Officer</div>
</div>

<?php if ($qrImage): ?>
    <div class="qr-block">
        <img src="<?= htmlspecialchars($qrImage); ?>" alt="QR Code"><br>
        Verify: <?= htmlspecialchars($report['id'] ?? ''); ?>
    </div>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
render_print_page('Inspection Report ' . ($report['id'] ?? ''), $bodyHtml, $officeId, $watermarkOverride);
