<?php
require_once __DIR__ . '/print_layout.php';

$caseId = (string) ($id ?? '');
$cases = load_rti_cases();
$case = find_rti_by_id($cases, $caseId);

if (!$case) {
    http_response_code(404);
    echo 'RTI case not found.';
    exit;
}

$officeId = get_current_office_id();
$status = strtolower((string) ($case['workflow_state'] ?? $case['status'] ?? ''));
$watermarkOverride = $status === 'draft' ? 'DRAFT' : null;

$verificationString = !empty($case['id']) ? YOJAKA_BASE_URL . '/portal.php?action=verify&type=rti&id=' . urlencode((string) $case['id']) : '';
$qrImage = $verificationString !== '' ? print_qr_data_uri($verificationString) : null;

ob_start();
?>
<div class="document-title">RTI Reply</div>

<table class="meta-table">
    <tr>
        <td><strong>Case ID:</strong> <?= htmlspecialchars($case['id'] ?? ''); ?></td>
        <td><strong>Reference Number:</strong> <?= htmlspecialchars($case['reference_number'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Applicant:</strong> <?= htmlspecialchars($case['applicant_name'] ?? ''); ?></td>
        <td><strong>Date of Receipt:</strong> <?= htmlspecialchars($case['date_of_receipt'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Reply Deadline:</strong> <?= htmlspecialchars($case['reply_deadline'] ?? ''); ?></td>
        <td><strong>Status:</strong> <?= htmlspecialchars($case['status'] ?? ''); ?></td>
    </tr>
</table>

<h4>Subject</h4>
<p><?= nl2br(htmlspecialchars($case['subject'] ?? '')); ?></p>

<h4>Details</h4>
<p><?= nl2br(htmlspecialchars($case['details'] ?? '')); ?></p>

<?php if (!empty($case['reply_summary'])): ?>
    <h4>Reply</h4>
    <table class="meta-table">
        <tr>
            <td style="width:30%"><strong>Reply Date:</strong></td>
            <td><?= htmlspecialchars($case['reply_date'] ?? ''); ?></td>
        </tr>
        <tr>
            <td><strong>Reply Summary:</strong></td>
            <td><?= nl2br(htmlspecialchars($case['reply_summary'] ?? '')); ?></td>
        </tr>
    </table>
<?php endif; ?>

<div class="signature-block">
    <div class="signature-line"></div>
    <div>Public Information Officer</div>
</div>

<?php if ($qrImage): ?>
    <div class="qr-block">
        <img src="<?= htmlspecialchars($qrImage); ?>" alt="QR Code"><br>
        Verify: <?= htmlspecialchars($case['id'] ?? ''); ?>
    </div>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
render_print_page('RTI Reply ' . ($case['id'] ?? ''), $bodyHtml, $officeId, $watermarkOverride);
