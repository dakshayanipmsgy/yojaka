<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';
require_once __DIR__ . '/../bills.php';
require_once __DIR__ . '/print_layout.php';

$billId = (string) ($id ?? '');
$currentUser = get_current_user();
$bills = load_bills();
$bill = find_bill_by_id($bills, $billId);
$officeId = get_current_office_id();

if (!$bill) {
    http_response_code(404);
    echo 'Bill not found.';
    exit;
}

$bill = bills_normalize_record($bill);

if (!acl_can_view($currentUser, $bill)) {
    http_response_code(403);
    echo 'You do not have access to this bill.';
    exit;
}

$status = strtolower((string) ($bill['workflow_state'] ?? $bill['status'] ?? ''));
$watermarkOverride = null;
if ($status === 'draft' || $status === 'pending') {
    $watermarkOverride = 'DRAFT';
} elseif ($status === 'approved') {
    $watermarkOverride = 'APPROVED';
}

$verificationString = '';
if (!empty($bill['id'])) {
    $verificationString = YOJAKA_BASE_URL . '/portal.php?action=verify&type=bill&id=' . urlencode((string) $bill['id']);
}
$qrImage = $verificationString !== '' ? print_qr_data_uri($verificationString) : null;

ob_start();
?>
<div class="document-title">Contractor Bill</div>

<table class="meta-table">
    <tr>
        <td><strong>Bill No:</strong> <?= htmlspecialchars($bill['bill_no'] ?? $bill['id'] ?? ''); ?></td>
        <td><strong>Date:</strong> <?= htmlspecialchars(format_date_for_display($bill['bill_date'] ?? '')); ?></td>
    </tr>
    <tr>
        <td><strong>Contractor:</strong> <?= htmlspecialchars($bill['contractor_name'] ?? ''); ?></td>
        <td><strong>Work Order:</strong> <?= htmlspecialchars($bill['work_order_no'] ?? ''); ?></td>
    </tr>
    <tr>
        <td><strong>Work Order Date:</strong> <?= htmlspecialchars(format_date_for_display($bill['work_order_date'] ?? '')); ?></td>
        <td><strong>Status:</strong> <?= htmlspecialchars($bill['status'] ?? ''); ?></td>
    </tr>
</table>

<?php $items = $bill['items'] ?? []; ?>
<?php if (!empty($items)): ?>
    <table class="content-table">
        <thead>
            <tr>
                <th style="width:5%">Sl</th>
                <th>Description</th>
                <th style="width:10%">Quantity</th>
                <th style="width:10%">Unit</th>
                <th style="width:15%">Rate</th>
                <th style="width:15%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?= (int) ($index + 1); ?></td>
                    <td><?= htmlspecialchars($item['description'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($item['quantity'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?? ''); ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($item['rate'] ?? 0), 2)); ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($item['amount'] ?? 0), 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php $deductions = $bill['deductions'] ?? []; ?>
<?php if (!empty($deductions)): ?>
    <h4>Deductions</h4>
    <table class="content-table">
        <thead>
            <tr>
                <th>Type</th>
                <th style="width:20%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deductions as $deduction): ?>
                <tr>
                    <td><?= htmlspecialchars($deduction['type'] ?? ''); ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($deduction['amount'] ?? 0), 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<table class="meta-table">
    <tr>
        <td><strong>Sub Total:</strong> <?= htmlspecialchars(number_format((float) ($bill['sub_total'] ?? 0), 2)); ?></td>
        <td><strong>Total Deductions:</strong> <?= htmlspecialchars(number_format((float) ($bill['total_deductions'] ?? 0), 2)); ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>Net Payable:</strong> <?= htmlspecialchars(number_format((float) ($bill['net_payable'] ?? 0), 2)); ?></td>
    </tr>
</table>

<?php if (!empty($bill['remarks'])): ?>
    <p><strong>Remarks:</strong><br><?= nl2br(htmlspecialchars($bill['remarks'] ?? '')); ?></p>
<?php endif; ?>

<div class="signature-block">
    <div class="signature-line"></div>
    <div>Authorized Signatory</div>
</div>

<?php if ($qrImage): ?>
    <div class="qr-block">
        <img src="<?= htmlspecialchars($qrImage); ?>" alt="QR Code"><br>
        Verify: <?= htmlspecialchars($bill['id'] ?? ''); ?>
    </div>
<?php endif; ?>
<?php
$bodyHtml = ob_get_clean();
render_print_page('Bill ' . ($bill['bill_no'] ?? $bill['id'] ?? ''), $bodyHtml, $officeId, $watermarkOverride);
