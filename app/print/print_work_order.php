<?php
require_once __DIR__ . '/print_layout.php';

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

$officeId = get_current_office_id();
$status = strtolower((string) ($record['status'] ?? ''));
$watermarkOverride = $status === 'draft' ? 'DRAFT' : null;

$verificationString = !empty($record['id']) ? YOJAKA_BASE_URL . '/portal.php?action=verify&type=work_order&id=' . urlencode((string) $record['id']) : '';
$qrImage = $verificationString !== '' ? print_qr_data_uri($verificationString) : null;

ob_start();
?>
<div class="document-title">Work Order</div>

<table class="meta-table">
    <tr>
        <td><strong>Order ID:</strong> <?= htmlspecialchars($record['id'] ?? ''); ?></td>
        <td><strong>Status:</strong> <?= htmlspecialchars($record['status'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Template:</strong> <?= htmlspecialchars($record['template_name'] ?? ''); ?></td>
        <td><strong>Created At:</strong> <?= htmlspecialchars($record['created_at'] ?? ''); ?></td>
    </tr>
</table>

<div class="document-body-content">
    <?= $record['rendered_body'] ?? ''; ?>
</div>

<div class="signature-block">
    <div class="signature-line"></div>
    <div>Issuing Authority</div>
</div>

<?php if ($qrImage): ?>
    <div class="qr-block">
        <img src="<?= htmlspecialchars($qrImage); ?>" alt="QR Code"><br>
        Verify: <?= htmlspecialchars($record['id'] ?? ''); ?>
    </div>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
render_print_page('Work Order ' . ($record['id'] ?? ''), $bodyHtml, $officeId, $watermarkOverride);
