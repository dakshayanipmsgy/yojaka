<?php
require_once __DIR__ . '/print_layout.php';

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

$officeId = get_current_office_id();
$status = strtolower((string) ($entry['workflow_state'] ?? $entry['status'] ?? ''));
$watermarkOverride = $status === 'draft' ? 'DRAFT' : null;

$verificationString = !empty($entry['id']) ? YOJAKA_BASE_URL . '/portal.php?action=verify&type=dak&id=' . urlencode((string) $entry['id']) : '';
$qrImage = $verificationString !== '' ? print_qr_data_uri($verificationString) : null;

ob_start();
?>
<div class="document-title">Dak Note</div>

<table class="meta-table">
    <tr>
        <td><strong>Dak ID:</strong> <?= htmlspecialchars($entry['id'] ?? ''); ?></td>
        <td><strong>Reference No:</strong> <?= htmlspecialchars($entry['reference_no'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Received From:</strong> <?= htmlspecialchars($entry['received_from'] ?? ''); ?></td>
        <td><strong>Date Received:</strong> <?= htmlspecialchars($entry['date_received'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Current Holder:</strong> <?= htmlspecialchars($entry['current_holder'] ?? ($entry['assigned_to'] ?? '')); ?></td>
        <td><strong>Status:</strong> <?= htmlspecialchars($entry['status'] ?? ''); ?></td>
    </tr>
</table>

<h4>Subject</h4>
<p><?= nl2br(htmlspecialchars($entry['subject'] ?? '')); ?></p>

<?php if (!empty($entry['details'])): ?>
    <h4>Note</h4>
    <p><?= nl2br(htmlspecialchars($entry['details'] ?? '')); ?></p>
<?php endif; ?>

<?php $movements = $entry['movements'] ?? []; ?>
<?php if (!empty($movements)): ?>
    <h4>Movement History</h4>
    <table class="content-table">
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

<div class="signature-block">
    <div class="signature-line"></div>
    <div>Authorized Signatory</div>
</div>

<?php if ($qrImage): ?>
    <div class="qr-block">
        <img src="<?= htmlspecialchars($qrImage); ?>" alt="QR Code"><br>
        Verify: <?= htmlspecialchars($entry['id'] ?? ''); ?>
    </div>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
render_print_page('Dak ' . ($entry['id'] ?? ''), $bodyHtml, $officeId, $watermarkOverride);
