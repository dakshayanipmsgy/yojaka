<?php
$billId = (string) ($id ?? '');
$bills = load_bills();
$bill = find_bill_by_id($bills, $billId);

if (!$bill) {
    http_response_code(404);
    echo 'Bill not found.';
    exit;
}

$departments = load_departments();
$department = get_user_department(['department_id' => $bill['department_id'] ?? null], $departments);
$pageSize = yojaka_print_page_size();

ob_start();
?>
<div class="document-container">
    <h1>Bill #<?= htmlspecialchars($bill['bill_no'] ?? $bill['id'] ?? ''); ?></h1>
    <div class="meta-grid">
        <div><strong>Date:</strong> <?= htmlspecialchars(format_date_for_display($bill['bill_date'] ?? '')); ?></div>
        <div><strong>Contractor:</strong> <?= htmlspecialchars($bill['contractor_name'] ?? ''); ?></div>
        <div><strong>Work Order:</strong> <?= htmlspecialchars($bill['work_order_no'] ?? ''); ?></div>
        <div><strong>Work Order Date:</strong> <?= htmlspecialchars(format_date_for_display($bill['work_order_date'] ?? '')); ?></div>
        <div><strong>Status:</strong> <?= htmlspecialchars($bill['status'] ?? ''); ?></div>
    </div>

    <?php $items = $bill['items'] ?? []; ?>
    <?php if (!empty($items)): ?>
        <h2>Items</h2>
        <table class="table">
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
        <h2>Deductions</h2>
        <table class="table">
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

    <div class="totals">
        <div><strong>Sub Total:</strong> <?= htmlspecialchars(number_format((float) ($bill['sub_total'] ?? 0), 2)); ?></div>
        <div><strong>Total Deductions:</strong> <?= htmlspecialchars(number_format((float) ($bill['total_deductions'] ?? 0), 2)); ?></div>
        <div><strong>Net Payable:</strong> <?= htmlspecialchars(number_format((float) ($bill['net_payable'] ?? 0), 2)); ?></div>
    </div>

    <?php if (!empty($bill['remarks'])): ?>
        <h3>Remarks</h3>
        <p><?= nl2br(htmlspecialchars($bill['remarks'] ?? '')); ?></p>
    <?php endif; ?>
</div>
<?php
$billContent = ob_get_clean();
$documentBody = $department ? render_with_letterhead($billContent, $department, true) : $billContent;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Bill</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/css/print.css">
    <style>
        @page { size: <?= htmlspecialchars($pageSize); ?> portrait; }
    </style>
</head>
<body class="print-document">
<?= $documentBody; ?>
</body>
</html>
