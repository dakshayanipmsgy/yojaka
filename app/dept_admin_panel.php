<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/critical_actions.php';

require_login();
$deptSlug = $_SESSION['department_id'] ?? null;
$dept = $deptSlug ? (load_departments()[$deptSlug] ?? null) : null;
if (!$deptSlug || !$dept) {
    include __DIR__ . '/views/access_denied.php';
    exit;
}

if (!user_has_permission('dept.manage_roles')) {
    include __DIR__ . '/views/access_denied.php';
    exit;
}

$isArchived = ($dept['status'] ?? 'active') === 'archived';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isArchived) {
    if (isset($_POST['create_role'])) {
        $baseId = trim((string) ($_POST['role_name'] ?? ''));
        $label = trim((string) ($_POST['role_label'] ?? $baseId));
        if ($baseId !== '') {
            create_department_role($deptSlug, $baseId, $label, []);
            $message = 'Role created';
        }
    }
    if (isset($_POST['request_role_delete'])) {
        $roleId = $_POST['role_id'] ?? '';
        request_role_delete($deptSlug, $roleId, $_SESSION['username']);
        $message = 'Delete request submitted';
    }
    if (isset($_POST['request_role_update'])) {
        $roleId = $_POST['role_id'] ?? '';
        $permissions = array_filter(array_map('trim', explode(',', (string) ($_POST['permissions'] ?? ''))));
        request_role_change($deptSlug, $roleId, ['permissions' => $permissions], $_SESSION['username']);
        $message = 'Update request submitted';
    }
    if (isset($_POST['create_user'])) {
        $base = trim((string) ($_POST['base_username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? $base));
        $password = bin2hex(random_bytes(6));
        $roles = $_POST['roles'] ?? [];
        $roles = array_filter($roles);
        $userRoles = [];
        foreach ($roles as $r) {
            $userRoles[] = $base . '.' . $r;
        }
        create_department_user($deptSlug, $base, $fullName, $password, $userRoles);
        $message = 'User created with password ' . $password;
    }
    if (isset($_POST['request_password_reset'])) {
        $base = $_POST['base_username'] ?? '';
        $newPass = bin2hex(random_bytes(6));
        request_user_password_reset($deptSlug, $base, $_SESSION['username'], $newPass);
        $message = 'Password reset requested';
    }
}

$roles = list_department_roles_with_labels($deptSlug);
$users = load_department_users($deptSlug);
?>
<!DOCTYPE html>
<html>
<head><title>Department Admin</title></head>
<body>
<h1>Department Admin Panel (<?php echo htmlspecialchars($deptSlug, ENT_QUOTES, 'UTF-8'); ?>)</h1>
<?php if ($isArchived): ?><p>Department is archived. All operations are read-only.</p><?php endif; ?>
<?php if ($message): ?><p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<section>
    <h2>Roles</h2>
    <?php if (!$isArchived): ?>
    <form method="post">
        <input type="text" name="role_name" placeholder="Role code" required>
        <input type="text" name="role_label" placeholder="Label">
        <button type="submit" name="create_role">Create Role</button>
    </form>
    <?php endif; ?>
    <table border="1" cellpadding="4">
        <tr><th>ID</th><th>Label</th><th>Permissions</th><th>Actions</th></tr>
        <?php foreach ($roles as $role): ?>
            <tr>
                <td><?php echo htmlspecialchars($role['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($role['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(implode(', ', $role['permissions']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if (!$isArchived): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="text" name="permissions" placeholder="comma separated permissions">
                        <button type="submit" name="request_role_update">Request Update</button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="request_role_delete">Request Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>
<section>
    <h2>Users</h2>
    <?php if (!$isArchived): ?>
    <form method="post">
        <input type="text" name="base_username" placeholder="Base username" required>
        <input type="text" name="full_name" placeholder="Full name">
        <label>Roles:
            <select name="roles[]" multiple>
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo htmlspecialchars($role['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($role['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="create_user">Create User</button>
    </form>
    <?php endif; ?>
    <table border="1" cellpadding="4">
        <tr><th>Username</th><th>Name</th><th>Roles</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['base_username'] . '.' . $deptSlug, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($u['full_name'] ?? $u['base_username'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(implode(', ', $u['roles']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo !empty($u['active']) ? 'Active' : 'Inactive'; ?></td>
                <td>
                    <?php if (!$isArchived): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="base_username" value="<?php echo htmlspecialchars($u['base_username'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" name="request_password_reset">Reset Password (Request)</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>
</body>
</html>
