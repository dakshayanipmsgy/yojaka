<?php
require_login();
$user = current_user();
global $config;
$moduleFilter = strtolower(trim($_GET['module'] ?? 'all'));
$onlyOverdue = !empty($_GET['only_overdue']);
$action = $_GET['action'] ?? null;
$csrfToken = $_SESSION['csrf_token'] ?? ($_SESSION['csrf_token'] = bin2hex(random_bytes(16)));
$taskMessage = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!$submitted || !hash_equals($csrfToken, $submitted)) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        if ($action === 'accept_file' || $action === 'reject_file') {
            $fileId = $_POST['file_id'] ?? '';
            $entries = load_dak_entries();
            foreach ($entries as &$entry) {
                if (($entry['id'] ?? '') !== $fileId) {
                    continue;
                }
                $remarks = trim($_POST['remarks'] ?? '');
                if ($action === 'accept_file') {
                    if (file_flow_accept_handover($entry, $user['username'] ?? '', $remarks)) {
                        append_dak_movement($entry, 'accepted', $entry['assigned_to'] ?? null, $user['username'] ?? null, $remarks, [
                            'status' => 'accepted',
                            'accepted_by' => $user['username'] ?? null,
                            'accepted_at' => gmdate('c'),
                            'rejected_reason' => null,
                        ]);
                        $entry['updated_at'] = gmdate('c');
                        $taskMessage = 'File accepted successfully.';
                        write_audit_log('dak', $entry['id'], 'file_accepted');
                    } else {
                        $errors[] = 'Unable to accept this file.';
                    }
                } else {
                    $reason = trim($_POST['reason'] ?? '');
                    if ($reason === '') {
                        $errors[] = 'Reason is required to reject a handover.';
                        break;
                    }
                    if (file_flow_reject_handover($entry, $user['username'] ?? '', $reason)) {
                        append_dak_movement($entry, 'rejected', $user['username'] ?? null, $entry['assigned_to'] ?? null, $reason, [
                            'status' => 'rejected',
                            'accepted_by' => null,
                            'accepted_at' => null,
                            'rejected_reason' => $reason,
                        ]);
                        $entry['updated_at'] = gmdate('c');
                        $taskMessage = 'File rejection recorded.';
                        write_audit_log('dak', $entry['id'], 'file_rejected', ['reason' => $reason]);
                    } else {
                        $errors[] = 'Unable to reject this handover.';
                    }
                }
                break;
            }
            unset($entry);
            if (empty($errors)) {
                save_dak_entries($entries);
                header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=my_tasks');
                exit;
            }
        }
    }
}

$rtiTasks = [];
$dakTasks = [];
$billTasks = [];
$pendingAcceptance = [];

$rtiCases = load_rti_cases();
foreach ($rtiCases as $case) {
    $case = enrich_workflow_defaults('rti', $case);
    $isAssigned = ($case['assigned_to'] ?? null) === ($user['username'] ?? null);
    $isApprover = ($case['current_approver'] ?? null) === ($user['username'] ?? null);
    $terminal = $case['workflow_state'] === 'closed';
    if (($isAssigned || $isApprover) && !$terminal) {
        $overdue = is_overdue_generic($case['reply_deadline'] ?? null);
        if ($onlyOverdue && !$overdue) {
            continue;
        }
        $rtiTasks[] = [
            'id' => $case['id'] ?? '',
            'subject' => $case['subject'] ?? '',
            'state' => $case['workflow_state'],
            'due' => $case['reply_deadline'] ?? '',
            'overdue' => $overdue,
        ];
    }
}

$dakEntries = load_dak_entries();
foreach ($dakEntries as $entry) {
    $entry = enrich_workflow_defaults('dak', $entry);
    $isAssigned = ($entry['assigned_to'] ?? null) === ($user['username'] ?? null);
    $waitingAcceptance = !empty($entry['pending_acceptance']) && ($entry['current_holder'] ?? null) === ($user['username'] ?? null);
    $terminal = $entry['workflow_state'] === 'closed' || ($entry['status'] ?? '') === 'Closed';
    if ($waitingAcceptance) {
        $pendingAcceptance[] = [
            'id' => $entry['id'] ?? '',
            'subject' => $entry['subject'] ?? '',
            'state' => $entry['workflow_state'] ?? ($entry['status'] ?? ''),
            'holder' => $entry['current_holder'] ?? '',
        ];
    } elseif ($isAssigned && !$terminal) {
        $dueDate = compute_due_date($entry['date_received'] ?? '', ($config['sla']['dak_process_days'] ?? ($config['dak_overdue_days'] ?? 7)));
        $overdue = is_overdue_generic($dueDate);
        if ($onlyOverdue && !$overdue) {
            continue;
        }
        $dakTasks[] = [
            'id' => $entry['id'] ?? '',
            'subject' => $entry['subject'] ?? '',
            'state' => $entry['workflow_state'] ?? ($entry['status'] ?? ''),
            'due' => $dueDate,
            'overdue' => $overdue,
        ];
    }
}

$bills = load_bills();
foreach ($bills as $bill) {
    $bill = enrich_workflow_defaults('bills', $bill);
    $isAssigned = ($bill['assigned_to'] ?? null) === ($user['username'] ?? null);
    $isApprover = ($bill['current_approver'] ?? null) === ($user['username'] ?? null);
    $terminal = in_array($bill['workflow_state'], ['approved', 'rejected', 'paid'], true);
    if (($isAssigned || $isApprover) && !$terminal) {
        $base = $bill['submitted_at'] ?? $bill['created_at'] ?? null;
        $dueDate = $base ? compute_due_date(substr($base, 0, 10), ($config['sla']['bill_approval_days'] ?? 10)) : null;
        $overdue = is_overdue_generic($dueDate);
        if ($onlyOverdue && !$overdue) {
            continue;
        }
        $billTasks[] = [
            'id' => $bill['id'] ?? '',
            'subject' => $bill['work_description'] ?? '',
            'state' => $bill['workflow_state'],
            'due' => $dueDate,
            'overdue' => $overdue,
            'bill_no' => $bill['bill_no'] ?? '',
        ];
    }
}
?>
<div class="filter-bar">
    <form method="get" class="form-inline">
        <input type="hidden" name="page" value="my_tasks">
        <label>Module
            <select name="module" onchange="this.form.submit()">
                <option value="all" <?= $moduleFilter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="rti" <?= $moduleFilter === 'rti' ? 'selected' : ''; ?>>RTI</option>
                <option value="dak" <?= $moduleFilter === 'dak' ? 'selected' : ''; ?>>Dak</option>
                <option value="bills" <?= $moduleFilter === 'bills' ? 'selected' : ''; ?>>Bills</option>
            </select>
        </label>
        <label>
            <input type="checkbox" name="only_overdue" value="1" <?= $onlyOverdue ? 'checked' : ''; ?>> Only overdue / due soon
        </label>
        <button type="submit" class="btn">Apply</button>
    </form>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($taskMessage): ?>
    <div class="alert success"><?= htmlspecialchars($taskMessage); ?></div>
<?php endif; ?>

<?php if (!empty($pendingAcceptance)): ?>
    <h3>Files awaiting my acceptance</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pendingAcceptance as $task): ?>
                <tr>
                    <td><?= htmlspecialchars($task['id']); ?></td>
                    <td><?= htmlspecialchars($task['subject']); ?></td>
                    <td><?= htmlspecialchars($task['state']); ?></td>
                    <td style="display:flex; gap:0.5rem;">
                        <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=my_tasks&action=accept_file" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="file_id" value="<?= htmlspecialchars($task['id']); ?>">
                            <input type="hidden" name="remarks" value="">
                            <button class="btn primary" type="submit">Accept</button>
                        </form>
                        <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=my_tasks&action=reject_file" style="margin:0; display:flex; gap:0.25rem; align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="file_id" value="<?= htmlspecialchars($task['id']); ?>">
                            <input type="text" name="reason" placeholder="Reason" required>
                            <button class="btn" type="submit">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($moduleFilter === 'all' || $moduleFilter === 'rti'): ?>
    <h3>RTI Tasks</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Subject</th><th>Workflow</th><th>Due</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($rtiTasks)): ?>
                <tr><td colspan="5">No RTI tasks.</td></tr>
            <?php else: ?>
                <?php foreach ($rtiTasks as $task): ?>
                    <tr class="<?= $task['overdue'] ? 'warn' : ''; ?>">
                        <td><?= htmlspecialchars($task['id']); ?></td>
                        <td><?= htmlspecialchars($task['subject']); ?></td>
                        <td><?= htmlspecialchars($task['state']); ?></td>
                        <td><?= htmlspecialchars($task['due'] ?? ''); ?><?= $task['overdue'] ? ' <span class="badge warn">Overdue</span>' : ''; ?></td>
                        <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti&mode=view&id=<?= urlencode($task['id']); ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($moduleFilter === 'all' || $moduleFilter === 'dak'): ?>
    <h3>Dak Tasks</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Subject</th><th>Workflow</th><th>Due</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($dakTasks)): ?>
                <tr><td colspan="5">No dak tasks.</td></tr>
            <?php else: ?>
                <?php foreach ($dakTasks as $task): ?>
                    <tr class="<?= $task['overdue'] ? 'warn' : ''; ?>">
                        <td><?= htmlspecialchars($task['id']); ?></td>
                        <td><?= htmlspecialchars($task['subject']); ?></td>
                        <td><?= htmlspecialchars($task['state']); ?></td>
                        <td><?= htmlspecialchars($task['due'] ?? ''); ?><?= $task['overdue'] ? ' <span class="badge warn">Overdue</span>' : ''; ?></td>
                        <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=view&id=<?= urlencode($task['id']); ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($moduleFilter === 'all' || $moduleFilter === 'bills'): ?>
    <h3>Bill Tasks</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Bill No</th><th>Work</th><th>Workflow</th><th>Due</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($billTasks)): ?>
                <tr><td colspan="5">No bill tasks.</td></tr>
            <?php else: ?>
                <?php foreach ($billTasks as $task): ?>
                    <tr class="<?= $task['overdue'] ? 'warn' : ''; ?>">
                        <td><?= htmlspecialchars($task['bill_no'] ?: $task['id']); ?></td>
                        <td><?= htmlspecialchars($task['subject']); ?></td>
                        <td><?= htmlspecialchars($task['state']); ?></td>
                        <td><?= htmlspecialchars($task['due'] ?? ''); ?><?= $task['overdue'] ? ' <span class="badge warn">Overdue</span>' : ''; ?></td>
                        <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills&mode=view&id=<?= urlencode($task['id']); ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
