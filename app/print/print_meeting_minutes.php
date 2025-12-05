<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';
require_once __DIR__ . '/../meeting_minutes.php';
require_once __DIR__ . '/print_layout.php';

$recordId = (string) ($id ?? '');
$currentUser = yojaka_current_user();
$record = find_meeting_minutes_record($recordId);

if (!$record) {
    http_response_code(404);
    echo 'Meeting minutes not found.';
    exit;
}

$record = meeting_minutes_normalize_record($record);

if (!acl_can_view($currentUser, $record)) {
    http_response_code(403);
    echo 'You do not have access to these meeting minutes.';
    exit;
}

$officeId = get_current_office_id();
$status = strtolower((string) ($record['status'] ?? ''));
$watermarkOverride = $status === 'draft' ? 'DRAFT' : null;

$verificationString = !empty($record['id']) ? YOJAKA_BASE_URL . '/portal.php?action=verify&type=meeting_minutes&id=' . urlencode((string) $record['id']) : '';
$qrImage = $verificationString !== '' ? print_qr_data_uri($verificationString) : null;

ob_start();
?>
<div class="document-title">Meeting Minutes</div>

<table class="meta-table">
    <tr>
        <td><strong>Minutes ID:</strong> <?= htmlspecialchars($record['id'] ?? ''); ?></td>
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
    <div>Chairperson / Secretary</div>
</div>

<?php if ($qrImage): ?>
    <div class="qr-block">
        <img src="<?= htmlspecialchars($qrImage); ?>" alt="QR Code"><br>
        Verify: <?= htmlspecialchars($record['id'] ?? ''); ?>
    </div>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
render_print_page('Meeting Minutes ' . ($record['id'] ?? ''), $bodyHtml, $officeId, $watermarkOverride);
