<?php
require_login();
$user = current_user();
global $config;
$moduleFilter = strtolower(trim($_GET['module'] ?? 'all'));
$onlyOverdue = !empty($_GET['only_overdue']);

$rtiTasks = [];
$dakTasks = [];
$billTasks = [];

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
    $terminal = $entry['workflow_state'] === 'closed' || ($entry['status'] ?? '') === 'Closed';
    if ($isAssigned && !$terminal) {
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
