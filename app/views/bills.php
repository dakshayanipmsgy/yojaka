<?php
require_login();
require_permission('create_documents');
require_module_enabled('bills');

$user = current_user();
$officeConfig = load_office_config();
$bills = load_bills();
$contractors = load_contractors();
$mode = $_GET['mode'] ?? 'list';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;
$attachmentErrors = [];
$attachmentNotice = '';
$attachmentToken = '';
$usersList = load_users();

$canViewAll = user_has_permission('view_all_records');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'create') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        echo '<div class="alert alert-danger">Security token mismatch.</div>';
    } else {
        $billNo = trim($_POST['bill_no'] ?? '');
        $billDate = trim($_POST['bill_date'] ?? date('Y-m-d'));
        $contractorId = trim($_POST['contractor_id'] ?? '');
        if ($contractorId === 'other') {
            $contractorId = '';
        }
        $contractor = trim($_POST['contractor_name'] ?? '');
        if ($contractorId !== '') {
            foreach ($contractors as $ctr) {
                if (($ctr['id'] ?? '') === $contractorId) {
                    $contractor = $ctr['name'] ?? $contractor;
                    break;
                }
            }
        }
        $workDescription = trim($_POST['work_description'] ?? '');
        $workOrderNo = trim($_POST['work_order_no'] ?? '');
        $workOrderDate = trim($_POST['work_order_date'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $status = ($_POST['status'] ?? 'Draft') === 'Final' ? 'Final' : 'Draft';

        $items = [];
        $descriptions = $_POST['item_description'] ?? [];
        $quantities = $_POST['item_quantity'] ?? [];
        $units = $_POST['item_unit'] ?? [];
        $rates = $_POST['item_rate'] ?? [];
        foreach ($descriptions as $idx => $desc) {
            $desc = trim($desc);
            if ($desc === '') {
                continue;
            }
            $items[] = [
                'sl_no' => count($items) + 1,
                'description' => $desc,
                'quantity' => (float) ($quantities[$idx] ?? 0),
                'unit' => trim($units[$idx] ?? ''),
                'rate' => (float) ($rates[$idx] ?? 0),
            ];
        }

        $deductions = [];
        $dedTypes = $_POST['deduction_type'] ?? [];
        $dedAmounts = $_POST['deduction_amount'] ?? [];
        foreach ($dedTypes as $idx => $type) {
            $type = trim($type);
            if ($type === '') {
                continue;
            }
            $deductions[] = [
                'type' => $type,
                'amount' => (float) ($dedAmounts[$idx] ?? 0),
            ];
        }

        $calculated = calculate_bill_totals($items, $deductions);
        $billId = generate_next_bill_id($bills);
        if ($billNo === '') {
            $billNo = get_id_prefix('bill', 'BILL') . '/' . date('Y') . '/' . str_pad(count($bills) + 1, 3, '0', STR_PAD_LEFT);
        }

        $newBill = [
            'id' => $billId,
            'bill_no' => $billNo,
            'bill_date' => $billDate,
            'contractor_id' => $contractorId ?: null,
            'contractor_name' => $contractor,
            'work_description' => $workDescription,
            'work_order_no' => $workOrderNo,
            'work_order_date' => $workOrderDate,
            'items' => $calculated['items'],
            'sub_total' => $calculated['sub_total'],
            'deductions' => $calculated['deductions'],
            'total_deductions' => $calculated['total_deductions'],
            'net_payable' => $calculated['net_payable'],
            'remarks' => $remarks,
            'status' => $status,
            'created_by' => $user['username'] ?? 'unknown',
            'department_id' => $user['department_id'] ?? null,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'workflow_state' => get_default_workflow_state('bills'),
            'current_approver' => null,
            'assigned_to' => $user['username'] ?? null,
            'approver_chain' => [],
            'last_action' => 'created',
            'last_action_at' => gmdate('c'),
        ];

        $bills[] = $newBill;
        save_bills($bills);
        log_event('bill_generated', $user['username'] ?? null, ['bill_id' => $billId, 'bill_no' => $billNo]);

        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=bills&mode=view&id=' . urlencode($billId));
        exit;
    }
}

if ($mode === 'view') {
    $id = $_GET['id'] ?? '';
    $bill = null;
    foreach ($bills as $entry) {
        if (($entry['id'] ?? '') === $id) {
            $bill = $entry;
            break;
        }
    }
    if (!$bill || (!$canViewAll && ($bill['created_by'] ?? '') !== ($user['username'] ?? ''))) {
        echo '<div class="alert alert-danger">Bill not found or access denied.</div>';
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['attachment_upload'])) {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!$submittedToken || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            echo '<div class="alert alert-danger">Security token mismatch.</div>';
        } else {
            if (isset($_POST['assign_to'])) {
                $assignTo = trim($_POST['assign_to']);
                $bill['assigned_to'] = $assignTo !== '' ? $assignTo : null;
                $bill['last_action'] = 'assigned';
                $bill['last_action_at'] = gmdate('c');
                $bill['updated_at'] = gmdate('c');
                foreach ($bills as &$entry) {
                    if (($entry['id'] ?? '') === $bill['id']) {
                        $entry = $bill;
                        break;
                    }
                }
                unset($entry);
                save_bills($bills);
                if (!empty($bill['assigned_to'])) {
                    create_notification($bill['assigned_to'], 'bills', $bill['id'], 'bill_assigned', 'Bill assigned to you', ($bill['bill_no'] ?? $bill['id']) . ' has been assigned.');
                }
                log_event('bill_workflow_changed', $user['username'] ?? null, ['bill_id' => $bill['id'], 'action' => 'assigned', 'assigned_to' => $assignTo]);
                header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=bills&mode=view&id=' . urlencode($bill['id']));
                exit;
            }
            if (isset($_POST['new_state'])) {
                $newState = $_POST['new_state'];
                $currentState = $bill['workflow_state'] ?? get_default_workflow_state('bills');
                if (can_transition_workflow('bills', $currentState, $newState, $user)) {
                    $bill['workflow_state'] = $newState;
                    if ($newState === 'submitted') {
                        $bill['submitted_at'] = gmdate('c');
                    }
                    if ($newState === 'approved') {
                        $bill['status'] = 'Final';
                    }
                    $bill['last_action'] = 'workflow_changed';
                    $bill['last_action_at'] = gmdate('c');
                    $bill['updated_at'] = gmdate('c');
                    foreach ($bills as &$entry) {
                        if (($entry['id'] ?? '') === $bill['id']) {
                            $entry = $bill;
                            break;
                        }
                    }
                    unset($entry);
                    save_bills($bills);
                    log_event('bill_workflow_changed', $user['username'] ?? null, ['bill_id' => $bill['id'], 'new_state' => $newState]);
                    if (!empty($bill['created_by'])) {
                        create_notification($bill['created_by'], 'bills', $bill['id'], 'bill_workflow_changed', 'Bill status updated', ($bill['bill_no'] ?? $bill['id']) . ' moved to ' . $newState);
                    }
                    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=bills&mode=view&id=' . urlencode($bill['id']));
                    exit;
                } else {
                    echo '<div class="alert alert-danger">You cannot change to that workflow state.</div>';
                }
            }
        }
    }

    $canUploadAttachments = user_has_permission('manage_bills') || user_has_permission('create_documents') || $canViewAll;
    [$attachmentErrors, $attachmentNotice, $attachmentToken] = handle_attachment_upload('bills', $bill['id'], 'bills_attachment_csrf', $canUploadAttachments);
    if (!empty($attachmentNotice) && empty($attachmentErrors)) {
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=bills&mode=view&id=' . urlencode($bill['id']));
        exit;
    }
    $billAttachments = find_attachments_for_entity('bills', $bill['id']);

    $departments = load_departments();
    $department = get_user_department(['department_id' => $bill['department_id']], $departments);

    ob_start();
    ?>
    <div class="detail-grid">
        <div><div class="muted">Bill Number</div><div class="strong"><?= htmlspecialchars($bill['bill_no']); ?></div></div>
        <div><div class="muted">Bill Date</div><div><?= htmlspecialchars(format_date_for_display($bill['bill_date'])); ?></div></div>
        <div><div class="muted">Contractor</div><div><?= htmlspecialchars($bill['contractor_name']); ?></div></div>
        <div><div class="muted">Work Order</div><div><?= htmlspecialchars($bill['work_order_no']); ?> (<?= htmlspecialchars(format_date_for_display($bill['work_order_date'])); ?>)</div></div>
        <div><div class="muted">Status</div><div class="badge"><?= htmlspecialchars($bill['status']); ?></div></div>
    </div>
    <div class="card" style="margin:1rem 0;">
        <h3>Workflow &amp; Approvals</h3>
        <p><strong>Workflow State:</strong> <?= htmlspecialchars($bill['workflow_state'] ?? get_default_workflow_state('bills')); ?></p>
        <p><strong>Assigned To:</strong> <?= htmlspecialchars($bill['assigned_to'] ?? 'Unassigned'); ?></p>
        <p><strong>Current Approver:</strong> <?= htmlspecialchars($bill['current_approver'] ?? '—'); ?></p>
        <form method="post" class="form-inline" style="gap:0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            <label>Assign to
                <select name="assign_to">
                    <option value="">Select user</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?= htmlspecialchars($u['username'] ?? ''); ?>" <?= ($bill['assigned_to'] ?? '') === ($u['username'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($u['full_name'] ?? ($u['username'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn" type="submit">Assign</button>
        </form>
        <?php $billTransitions = workflow_definitions()['bills']['transitions'][$bill['workflow_state'] ?? get_default_workflow_state('bills')] ?? []; ?>
        <?php if (!empty($billTransitions)): ?>
            <div class="form-actions" style="margin-top:0.5rem;">
                <?php foreach ($billTransitions as $state): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="new_state" value="<?= htmlspecialchars($state); ?>">
                        <button class="btn" type="submit">Mark <?= htmlspecialchars($state); ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <h3>Measurements / Items</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach ($bill['items'] as $item): ?>
                <tr>
                    <td><?= (int) ($item['sl_no'] ?? 0); ?></td>
                    <td><?= htmlspecialchars($item['description'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($item['quantity'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($item['rate'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($item['amount'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h3>Deductions</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Type</th><th>Amount</th></tr></thead>
            <tbody>
            <?php if (!empty($bill['deductions'])): ?>
                <?php foreach ($bill['deductions'] as $ded): ?>
                    <tr>
                        <td><?= htmlspecialchars($ded['type'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($ded['amount'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="2">No deductions</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="detail-grid">
        <div><div class="muted">Sub Total</div><div class="strong">₹ <?= number_format($bill['sub_total'], 2); ?></div></div>
        <div><div class="muted">Total Deductions</div><div class="strong">₹ <?= number_format($bill['total_deductions'], 2); ?></div></div>
        <div><div class="muted">Net Payable</div><div class="strong">₹ <?= number_format($bill['net_payable'], 2); ?></div></div>
        <div><div class="muted">Remarks</div><div><?= nl2br(htmlspecialchars($bill['remarks'] ?? '')); ?></div></div>
    </div>
    <?php
    $billBody = ob_get_clean();
    $wrapped = render_with_letterhead($billBody, $department ?? []);

    if (isset($_GET['download'])) {
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="bill_' . $bill['id'] . '.html"');
        echo $wrapped;
        exit;
    }
    ?>
    <div class="form-actions" style="justify-content: flex-end; margin-bottom:1rem;">
        <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills">Back to list</a>
        <button class="button" onclick="window.print(); return false;">Print</button>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&mode=view&id=<?= urlencode($bill['id']); ?>&download=1">Download HTML</a>
    </div>
    <?= $wrapped; ?>
    <div class="card" style="margin-top:1rem;">
        <h3>Attachments</h3>
        <?php if (!empty($attachmentErrors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($attachmentErrors as $err): ?>
                        <li><?= htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($attachmentNotice !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($attachmentNotice); ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Description</th><th>File</th><th>Size</th><th>Uploaded By</th><th>Uploaded At</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($billAttachments)): ?>
                        <tr><td colspan="6">No attachments yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($billAttachments as $att): ?>
                            <tr>
                                <td><?= htmlspecialchars($att['description'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($att['original_name'] ?? ''); ?></td>
                                <td><?= format_attachment_size((int) ($att['size_bytes'] ?? 0)); ?></td>
                                <td><?= htmlspecialchars($att['uploaded_by'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($att['uploaded_at'] ?? ''); ?></td>
                                <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/download_attachment.php?id=<?= urlencode($att['id'] ?? ''); ?>">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canUploadAttachments): ?>
            <form method="post" enctype="multipart/form-data" style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($attachmentToken); ?>">
                <input type="hidden" name="attachment_upload" value="1">
                <div class="form-field">
                    <label>Attachment File</label>
                    <input type="file" name="attachment_file" required>
                </div>
                <div class="form-field">
                    <label>Description</label>
                    <input type="text" name="attachment_description" placeholder="Short description">
                </div>
                <div class="form-field">
                    <label>Tags (comma separated)</label>
                    <input type="text" name="attachment_tags" placeholder="tag1, tag2">
                </div>
                <button type="submit" class="btn primary">Upload Attachment</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return;
}

if ($mode === 'create') {
    ?>
    <form method="post" class="form-stacked" id="bill-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <div class="grid">
            <div class="form-field">
                <label>Bill No.</label>
                <input type="text" name="bill_no" placeholder="Auto-generate if blank" value="<?= htmlspecialchars(get_id_prefix('bill', 'BILL') . '/' . date('Y') . '/001'); ?>">
            </div>
            <div class="form-field">
                <label>Bill Date</label>
                <input type="date" name="bill_date" value="<?= htmlspecialchars(date('Y-m-d')); ?>" required>
            </div>
            <div class="form-field">
                <label>Contractor</label>
                <select name="contractor_id">
                    <option value="">-- Select Contractor --</option>
                    <?php foreach ($contractors as $ctr): ?>
                        <option value="<?= htmlspecialchars($ctr['id'] ?? ''); ?>"><?= htmlspecialchars(($ctr['name'] ?? '') . ($ctr['category'] ? ' (' . $ctr['category'] . ')' : '')); ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other (type manually)</option>
                </select>
                <input type="text" name="contractor_name" placeholder="If Other, type contractor name" required>
            </div>
            <div class="form-field">
                <label>Work Description</label>
                <textarea name="work_description" data-ai-suggest="true" required></textarea>
                <button type="button" class="button ai-suggest-btn" data-target="work_description">Get Suggestion (Coming Soon)</button>
            </div>
            <div class="form-field">
                <label>Work Order No.</label>
                <input type="text" name="work_order_no">
            </div>
            <div class="form-field">
                <label>Work Order Date</label>
                <input type="date" name="work_order_date">
            </div>
        </div>

        <h3>Items</h3>
        <div class="table-responsive">
            <table class="table" id="items-table">
                <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th></th></tr></thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="item_description[]" required></td>
                        <td><input type="number" step="0.01" name="item_quantity[]" required></td>
                        <td><input type="text" name="item_unit[]"></td>
                        <td><input type="number" step="0.01" name="item_rate[]" required></td>
                        <td><button type="button" class="button" onclick="removeRow(this)">Remove</button></td>
                    </tr>
                </tbody>
            </table>
            <div class="form-actions"><button type="button" class="button" onclick="addItemRow()">Add Item</button></div>
        </div>

        <h3>Deductions</h3>
        <div class="table-responsive">
            <table class="table" id="deductions-table">
                <thead><tr><th>Type</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="deduction_type[]"></td>
                        <td><input type="number" step="0.01" name="deduction_amount[]"></td>
                        <td><button type="button" class="button" onclick="removeRow(this)">Remove</button></td>
                    </tr>
                </tbody>
            </table>
            <div class="form-actions"><button type="button" class="button" onclick="addDeductionRow()">Add Deduction</button></div>
        </div>

        <div class="form-field">
            <label>Remarks</label>
            <textarea name="remarks" data-ai-suggest="true"></textarea>
            <button type="button" class="button ai-suggest-btn" data-target="remarks">Get Suggestion (Coming Soon)</button>
        </div>
        <div class="form-field">
            <label>Status</label>
            <select name="status">
                <option value="Draft">Draft</option>
                <option value="Final">Final</option>
            </select>
        </div>
        <div class="form-actions">
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills">Cancel</a>
            <button type="submit" class="btn-primary">Save Bill</button>
        </div>
    </form>

    <script>
        function addItemRow() {
            const tbody = document.querySelector('#items-table tbody');
            const row = document.createElement('tr');
            row.innerHTML = '<td><input type="text" name="item_description[]" required></td>' +
                '<td><input type="number" step="0.01" name="item_quantity[]" required></td>' +
                '<td><input type="text" name="item_unit[]"></td>' +
                '<td><input type="number" step="0.01" name="item_rate[]" required></td>' +
                '<td><button type="button" class="button" onclick="removeRow(this)">Remove</button></td>';
            tbody.appendChild(row);
        }
        function addDeductionRow() {
            const tbody = document.querySelector('#deductions-table tbody');
            const row = document.createElement('tr');
            row.innerHTML = '<td><input type="text" name="deduction_type[]"></td>' +
                '<td><input type="number" step="0.01" name="deduction_amount[]"></td>' +
                '<td><button type="button" class="button" onclick="removeRow(this)">Remove</button></td>';
            tbody.appendChild(row);
        }
        function removeRow(btn) {
            const row = btn.closest('tr');
            if (row && row.parentNode.children.length > 1) {
                row.parentNode.removeChild(row);
            }
        }
    </script>
    <?php
    return;
}

// Default list mode
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$filtered = [];
foreach ($bills as $bill) {
    if (!$canViewAll && ($bill['created_by'] ?? '') !== ($user['username'] ?? '')) {
        continue;
    }
    if ($search !== '') {
        $haystack = strtolower(($bill['bill_no'] ?? '') . ' ' . ($bill['contractor_name'] ?? '') . ' ' . ($bill['work_description'] ?? '') . ' ' . ($bill['work_order_no'] ?? ''));
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }
    if ($statusFilter !== '' && ($bill['status'] ?? '') !== $statusFilter) {
        continue;
    }
    $filtered[] = $bill;
}

$pageNum = get_page_param('p');
$pagination = paginate_array($filtered, $pageNum, $config['pagination_per_page'] ?? 10);
?>
<div class="filter-bar">
    <form method="get" class="form-actions" style="gap:0.5rem;">
        <input type="hidden" name="page" value="bills">
        <input type="text" name="q" placeholder="Search bills" value="<?= htmlspecialchars($search); ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="Final" <?= $statusFilter === 'Final' ? 'selected' : ''; ?>>Final</option>
        </select>
        <button class="button" type="submit">Filter</button>
    </form>
    <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&mode=create">Create New Bill</a>
</div>
<div class="table-responsive">
    <table class="table">
        <thead><tr><th>Bill No</th><th>Date</th><th>Contractor</th><th>Work</th><th>Status</th><th>Net Payable</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pagination['items'] as $bill): ?>
            <tr>
                <td><?= htmlspecialchars($bill['bill_no'] ?? ''); ?></td>
                <td><?= htmlspecialchars(format_date_for_display($bill['bill_date'] ?? '')); ?></td>
                <td><?= htmlspecialchars($bill['contractor_name'] ?? ''); ?></td>
                <td><?= htmlspecialchars($bill['work_description'] ?? ''); ?></td>
                <td><span class="badge"><?= htmlspecialchars($bill['status'] ?? ''); ?></span></td>
                <td>₹ <?= number_format((float) ($bill['net_payable'] ?? 0), 2); ?></td>
                <td><a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&mode=view&id=<?= urlencode($bill['id'] ?? ''); ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pagination['items'])): ?>
            <tr><td colspan="7">No bills found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<div class="form-actions">
    <div>Page <?= (int) $pagination['page']; ?> of <?= (int) $pagination['total_pages']; ?></div>
    <div>
        <?php if ($pagination['page'] > 1): ?>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&p=<?= $pagination['page'] - 1; ?>&q=<?= urlencode($search); ?>&status=<?= urlencode($statusFilter); ?>">Prev</a>
        <?php endif; ?>
        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&p=<?= $pagination['page'] + 1; ?>&q=<?= urlencode($search); ?>&status=<?= urlencode($statusFilter); ?>">Next</a>
        <?php endif; ?>
    </div>
</div>
<?php
?>
