<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/critical_actions.php';

require_login();
if (!is_superadmin()) {
    include __DIR__ . '/views/access_denied.php';
    exit;
}

$departments = load_departments();
$actions = load_critical_actions();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_department'])) {
        $name = trim((string) ($_POST['department_name'] ?? ''));
        if ($name !== '') {
            $dept = create_department(['name' => $name]);
            $role = create_department_role($dept['slug'], 'dept_admin', 'Department Admin', ['role.create', 'role.request_update', 'role.request_delete', 'user.create', 'user.request_edit', 'template.create', 'template.edit']);
            $admin = create_department_admin_user($dept['slug'], $dept['name'], $role['id']);
            $message = 'Department created with admin user ' . $admin['username'] . ' (password: ' . $admin['password_plain'] . ')';
            $departments = load_departments();
        }
    }
    if (isset($_POST['set_status'])) {
        $slug = $_POST['dept_slug'] ?? '';
        $status = $_POST['status'] ?? '';
        if (set_department_status($slug, $status)) {
            $message = 'Updated department state to ' . $status;
            $departments = load_departments();
        }
    }
    if (isset($_POST['approve_action'])) {
        approve_critical_action($_POST['action_id']);
        $actions = load_critical_actions();
    }
    if (isset($_POST['reject_action'])) {
        reject_critical_action($_POST['action_id']);
        $actions = load_critical_actions();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Superadmin Panel</title>
</head>
<body>
<h1>Superadmin Panel</h1>
<?php if ($message): ?><p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<section>
    <h2>Create Department</h2>
    <form method="post">
        <label>Name <input type="text" name="department_name" required></label>
        <button type="submit" name="create_department">Create</button>
    </form>
</section>
<section>
    <h2>Departments</h2>
    <table border="1" cellpadding="4">
        <tr><th>Slug</th><th>Name</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($departments as $dept): ?>
            <tr>
                <td><?php echo htmlspecialchars($dept['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($dept['name'] ?? $dept['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($dept['status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="dept_slug" value="<?php echo htmlspecialchars($dept['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                        <select name="status">
                            <option value="active">Activate</option>
                            <option value="suspended">Suspend</option>
                            <option value="archived">Archive</option>
                        </select>
                        <button type="submit" name="set_status">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>
<section>
    <h2>Pending Critical Actions</h2>
    <table border="1" cellpadding="4">
        <tr><th>ID</th><th>Department</th><th>Type</th><th>Requested By</th><th>Payload</th><th>Status</th><th>Decision</th></tr>
        <?php foreach ($actions as $action): ?>
            <tr>
                <td><?php echo htmlspecialchars($action['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($action['department'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($action['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($action['requested_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><pre><?php echo htmlspecialchars(json_encode($action['payload'] ?? [], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></pre></td>
                <td><?php echo htmlspecialchars($action['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if (($action['status'] ?? '') === 'pending'): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action_id" value="<?php echo htmlspecialchars($action['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="approve_action">Approve</button>
                        <button type="submit" name="reject_action">Reject</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>
</body>
</html>
