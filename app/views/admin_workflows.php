<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';
require_once __DIR__ . '/../workflow.php';
require_once __DIR__ . '/../critical_actions.php';
require_once __DIR__ . '/../departments.php';

$user = current_user();
[,, $deptSlug] = acl_parse_username_parts($user['username'] ?? null);
$isSuperAdmin = ($user['role'] ?? '') === 'superadmin';
$isDeptAdmin = strpos($user['role'] ?? '', 'dept_admin.') === 0;

if ($isSuperAdmin) {
    echo '<p>Superadmin cannot directly edit department workflows. Approve critical actions from the superadmin dashboard.</p>';
    return;
}

if (!$isDeptAdmin || !$deptSlug) {
    echo '<p>Only department admins can configure workflows.</p>';
    return;
}

$messages = [];
$workflows = load_workflows_for_department($deptSlug);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['wf_action'] ?? '';
    if ($action === 'create') {
        $module = trim($_POST['module'] ?? '');
        $workflowId = trim($_POST['workflow_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($module && $workflowId && $name) {
            if (isset($workflows[$workflowId])) {
                $messages[] = 'Workflow ID already exists.';
            } else {
                $now = date('c');
                $workflows[$workflowId] = [
                    'id' => $workflowId,
                    'module' => $module,
                    'name' => $name,
                    'description' => $description,
                    'department_slug' => $deptSlug,
                    'steps' => [
                        [
                            'id' => 'start',
                            'label' => 'Start',
                            'allowed_roles' => ["dept_admin.$deptSlug"],
                            'allow_assign_to_any_user_of_role' => true,
                            'default_due_days' => 3,
                            'allow_return_to' => [],
                            'allow_forward_to' => ['complete'],
                            'is_terminal' => false,
                        ],
                        [
                            'id' => 'complete',
                            'label' => 'Complete',
                            'allowed_roles' => [],
                            'allow_assign_to_any_user_of_role' => false,
                            'default_due_days' => 0,
                            'allow_return_to' => ['start'],
                            'allow_forward_to' => [],
                            'is_terminal' => true,
                        ],
                    ],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (save_workflows_for_department($deptSlug, $workflows)) {
                    $messages[] = 'Workflow created.';
                } else {
                    $messages[] = 'Failed to save workflow.';
                }
            }
        } else {
            $messages[] = 'Module, ID, and Name are required.';
        }
    } elseif ($action === 'request_delete') {
        $workflowId = trim($_POST['workflow_id'] ?? '');
        if ($workflowId && isset($workflows[$workflowId])) {
            $payload = [
                'workflow_id' => $workflowId,
            ];
            queue_critical_action([
                'department' => $deptSlug,
                'type' => 'workflow.delete',
                'requested_by' => $user['username'] ?? null,
                'payload' => $payload,
            ]);
            $messages[] = 'Deletion request queued for superadmin approval.';
        } else {
            $messages[] = 'Workflow not found.';
        }
    }
    $workflows = load_workflows_for_department($deptSlug);
}
?>
<div class="alert info">
    Department: <strong><?= htmlspecialchars($deptSlug); ?></strong>. Changes to workflow steps may require superadmin approval.
</div>
<?php foreach ($messages as $msg): ?>
    <div class="alert"><?= htmlspecialchars($msg); ?></div>
<?php endforeach; ?>
<h3>Existing Workflows</h3>
<table class="table">
    <thead>
        <tr>
            <th>Module</th>
            <th>ID</th>
            <th>Name</th>
            <th>Steps</th>
            <th>Updated</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($workflows)): ?>
        <tr><td colspan="6">No workflows defined.</td></tr>
    <?php else: ?>
        <?php foreach ($workflows as $wf): ?>
            <tr>
                <td><?= htmlspecialchars($wf['module'] ?? ''); ?></td>
                <td><?= htmlspecialchars($wf['id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($wf['name'] ?? ''); ?></td>
                <td><?= count($wf['steps'] ?? []); ?></td>
                <td><?= htmlspecialchars($wf['updated_at'] ?? $wf['created_at'] ?? ''); ?></td>
                <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('Queue deletion for superadmin approval?');">
                        <input type="hidden" name="wf_action" value="request_delete" />
                        <input type="hidden" name="workflow_id" value="<?= htmlspecialchars($wf['id'] ?? ''); ?>" />
                        <button type="submit">Request Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<h3>Create New Workflow</h3>
<form method="post">
    <input type="hidden" name="wf_action" value="create" />
    <div class="form-group">
        <label>Module</label>
        <select name="module" required>
            <option value="">Select module</option>
            <option value="rti">RTI Cases</option>
            <option value="dak">Dak &amp; File Movement</option>
            <option value="inspection">Inspection</option>
            <option value="meeting_minutes">Meeting Minutes</option>
            <option value="work_orders">Work Orders</option>
            <option value="guc">Grant Utilization Certificates</option>
            <option value="bills">Contractor Bills</option>
        </select>
    </div>
    <div class="form-group">
        <label>Workflow ID</label>
        <input type="text" name="workflow_id" required placeholder="slug_format" />
    </div>
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" required />
    </div>
    <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="2"></textarea>
    </div>
    <p>Step editing requires superadmin approval via critical actions. This form seeds a basic two-step workflow you can refine later.</p>
    <button type="submit">Create Workflow</button>
</form>
