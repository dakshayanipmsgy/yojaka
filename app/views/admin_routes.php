<?php
require_login();
require_permission('manage_office_config');
$currentOfficeId = get_current_office_id();
$routes = load_routes($currentOfficeId);
$positions = load_positions($currentOfficeId);
$departments = load_departments();
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_route') {
        $routeId = trim($_POST['route_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $fileType = trim($_POST['file_type'] ?? '');
        $departmentId = trim($_POST['department_id'] ?? '');
        $allowSkip = isset($_POST['allow_skip']);
        $allowBackward = isset($_POST['allow_backward']);
        $nodePositions = $_POST['node_position_id'] ?? [];
        $nodeRoles = $_POST['node_role'] ?? [];
        $nodes = [];
        foreach ($nodePositions as $index => $posId) {
            if ($posId === '') { continue; }
            $nodes[] = [
                'id' => 'NODE-' . ($index + 1),
                'position_id' => $posId,
                'role' => $nodeRoles[$index] ?? '',
                'order' => $index + 1,
            ];
        }
        if ($name === '' || $fileType === '' || empty($nodes)) {
            $errors[] = 'Name, file type, and at least one node are required.';
        }
        if (empty($errors)) {
            $isUpdate = $routeId !== '';
            if ($isUpdate) {
                foreach ($routes as &$r) {
                    if (($r['id'] ?? '') === $routeId) {
                        $r['name'] = $name;
                        $r['file_type'] = $fileType;
                        $r['department_id'] = $departmentId;
                        $r['nodes'] = $nodes;
                        $r['allow_skip'] = $allowSkip;
                        $r['allow_backward'] = $allowBackward;
                        $r['active'] = true;
                        break;
                    }
                }
                unset($r);
            } else {
                $routes[] = [
                    'id' => generate_route_id($routes),
                    'office_id' => $currentOfficeId,
                    'name' => $name,
                    'file_type' => $fileType,
                    'department_id' => $departmentId,
                    'nodes' => $nodes,
                    'allow_skip' => $allowSkip,
                    'allow_backward' => $allowBackward,
                    'active' => true,
                ];
            }
            save_routes($currentOfficeId, $routes);
            log_event('route_template_created', current_user()['username'] ?? null, ['route' => $routeId ?: 'new']);
            $success = 'Route saved successfully.';
        }
    }
}
?>
<div class="card">
    <div class="flex" style="justify-content: space-between; align-items:center;">
        <h3>File Route Templates</h3>
        <a class="btn ghost" target="_blank" href="<?= YOJAKA_BASE_URL ?>/app.php?page=help_user_roles">Help</a>
    </div>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert error"><?= htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>
    <table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>File Type</th><th>Department</th><th>Nodes</th></tr></thead>
        <tbody>
            <?php foreach ($routes as $route): ?>
                <tr>
                    <td><?= htmlspecialchars($route['id'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($route['name'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($route['file_type'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($route['department_id'] ?? ''); ?></td>
                    <td><?= count($route['nodes'] ?? []); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post">
        <input type="hidden" name="action" value="save_route">
        <label>Route ID (for editing)
            <input type="text" name="route_id">
        </label>
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>File Type
            <input type="text" name="file_type" placeholder="estimation, bill, dak" required>
        </label>
        <label>Department
            <select name="department_id">
                <option value="">(Any)</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['id'] ?? ''); ?>"><?= htmlspecialchars($dept['name'] ?? ''); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="muted">Define nodes in order:</div>
        <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="form-grid">
                <label>Position
                    <select name="node_position_id[]">
                        <option value="">(skip)</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['id'] ?? ''); ?>"><?= htmlspecialchars($pos['title'] ?? $pos['id']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Role
                    <input type="text" name="node_role[]" placeholder="initiator/review/approval">
                </label>
            </div>
        <?php endfor; ?>
        <label class="checkbox"><input type="checkbox" name="allow_skip"> Allow skipping nodes</label>
        <label class="checkbox"><input type="checkbox" name="allow_backward"> Allow backward movement</label>
        <button type="submit" class="btn primary">Save Route</button>
    </form>
</div>
