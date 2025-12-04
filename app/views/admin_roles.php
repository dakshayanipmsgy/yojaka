<?php
require_permission('manage_users');
$permissionsConfig = load_permissions_config();
$messages = [];
$errors = [];
$availablePermissions = [];
foreach ($permissionsConfig['roles'] as $perms) {
    foreach ($perms as $p) {
        $availablePermissions[] = $p;
    }
}
$availablePermissions = array_values(array_unique($availablePermissions));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_role') {
        $name = strtolower(trim($_POST['role_name'] ?? ''));
        $perms = $_POST['permissions'] ?? [];
        if ($name === '') {
            $errors[] = 'Role name is required.';
        } else {
            $permissionsConfig['custom_roles'][$name] = array_values(array_unique(array_filter($perms)));
            save_permissions_config($permissionsConfig);
            $messages[] = 'Role saved.';
        }
    } elseif ($action === 'assign_user') {
        $username = trim($_POST['username'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $users = load_users();
        $updated = false;
        foreach ($users as &$u) {
            if (strcasecmp($u['username'] ?? '', $username) === 0) {
                $u['role'] = $role;
                $updated = true;
                break;
            }
        }
        unset($u);
        if ($updated) {
            save_users($users);
            $messages[] = 'User role updated.';
        } else {
            $errors[] = 'User not found.';
        }
    } elseif ($action === 'delete_role') {
        $name = trim($_POST['role_name'] ?? '');
        if (isset($permissionsConfig['custom_roles'][$name])) {
            unset($permissionsConfig['custom_roles'][$name]);
            save_permissions_config($permissionsConfig);
            $messages[] = 'Role removed.';
        }
    }
    $permissionsConfig = load_permissions_config();
}

$users = load_users();
?>
<div class="alert info">
    Define custom roles and assign permissions beyond the built-in set.
</div>
<?php foreach ($messages as $msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
<?php endforeach; ?>
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
    <section>
        <h3>Create / Update Role</h3>
        <form method="post" class="form-stacked">
            <input type="hidden" name="action" value="create_role" />
            <label>Role name</label>
            <input type="text" name="role_name" required />
            <label>Permissions</label>
            <div class="checkbox-group">
                <?php foreach ($availablePermissions as $perm): ?>
                    <label style="display:block;">
                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm); ?>" /> <?= htmlspecialchars($perm); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="btn primary" type="submit">Save Role</button>
        </form>
    </section>
    <section>
        <h3>Assign Role to User</h3>
        <form method="post" class="form-stacked">
            <input type="hidden" name="action" value="assign_user" />
            <label>Username</label>
            <input type="text" name="username" required />
            <label>Role</label>
            <select name="role" required>
                <?php foreach (array_keys($permissionsConfig['roles'] + $permissionsConfig['custom_roles']) as $roleName): ?>
                    <option value="<?= htmlspecialchars($roleName); ?>"><?= htmlspecialchars($roleName); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Assign</button>
        </form>
    </section>
</div>
<hr>
<h3>Existing Roles</h3>
<table class="table">
    <thead>
        <tr><th>Role</th><th>Permissions</th><th>Type</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($permissionsConfig['roles'] as $name => $perms): ?>
            <tr>
                <td><?= htmlspecialchars($name); ?></td>
                <td><?= htmlspecialchars(implode(', ', $perms)); ?></td>
                <td>Built-in</td>
                <td>-</td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($permissionsConfig['custom_roles'] as $name => $perms): ?>
            <tr>
                <td><?= htmlspecialchars($name); ?></td>
                <td><?= htmlspecialchars(implode(', ', $perms)); ?></td>
                <td>Custom</td>
                <td>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete role?');">
                        <input type="hidden" name="action" value="delete_role" />
                        <input type="hidden" name="role_name" value="<?= htmlspecialchars($name); ?>" />
                        <button class="btn danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Users</h3>
<table class="table">
    <thead>
        <tr><th>Username</th><th>Full Name</th><th>Role</th></tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username'] ?? ''); ?></td>
                <td><?= htmlspecialchars($u['full_name'] ?? ''); ?></td>
                <td><?= htmlspecialchars($u['role'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
