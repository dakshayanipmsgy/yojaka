<?php
require_permission('manage_users');
require_once __DIR__ . '/../roles.php';

$currentUser = current_user();
$isSuperAdmin = ($currentUser['role'] ?? '') === 'superadmin';
$deptSlug = $currentUser ? get_current_department_slug_for_user($currentUser) : null;

$permissionsConfig = load_permissions_config();
$messages = [];
$errors = [];

function collect_permissions_from_config(array $config): array
{
    $perms = [];
    foreach ($config as $roleDef) {
        $normalized = normalize_role_definition($roleDef);
        foreach ($normalized['permissions'] as $p) {
            $perms[] = $p;
        }
    }
    return array_values(array_unique($perms));
}

$availablePermissions = collect_permissions_from_config(($permissionsConfig['roles'] ?? []) + ($permissionsConfig['custom_roles'] ?? []));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_role') {
        $baseRole = strtolower(trim($_POST['role_name'] ?? ''));
        $label = trim($_POST['role_label'] ?? '');
        $perms = $_POST['permissions'] ?? [];
        if ($baseRole === '' || !preg_match('/^[a-z0-9_\.]+$/', $baseRole)) {
            $errors[] = 'Role name is required and should contain only letters, numbers, underscores or dots.';
        } else {
            $roleId = $baseRole;
            if (!$isSuperAdmin) {
                if ($deptSlug === null) {
                    $errors[] = 'Department context missing for role creation.';
                } else {
                    $roleId = make_department_role_id($baseRole, $deptSlug);
                }
            }
            if (empty($errors)) {
                $permissionsConfig['roles'][$roleId] = [
                    'label' => $label !== '' ? $label : ucfirst(str_replace('_', ' ', $baseRole)),
                    'permissions' => array_values(array_unique(array_filter($perms))),
                ];
                save_permissions_config($permissionsConfig);
                $messages[] = 'Role saved.';
            }
        }
    } elseif ($action === 'delete_role') {
        $roleName = trim($_POST['role_name'] ?? '');
        $canDelete = $isSuperAdmin || ($deptSlug && str_ends_with($roleName, '.' . $deptSlug));
        if ($canDelete && isset($permissionsConfig['roles'][$roleName])) {
            unset($permissionsConfig['roles'][$roleName]);
            save_permissions_config($permissionsConfig);
            $messages[] = 'Role removed.';
        } else {
            $errors[] = 'Unable to delete role.';
        }
    }
    $permissionsConfig = load_permissions_config();
    $availablePermissions = collect_permissions_from_config(($permissionsConfig['roles'] ?? []) + ($permissionsConfig['custom_roles'] ?? []));
}

$rolePool = $permissionsConfig['roles'] ?? [];
if (!$isSuperAdmin) {
    $rolePool = filter_roles_for_department($rolePool, $deptSlug, false);
}
?>
<div class="alert info">
    <?php if ($isSuperAdmin): ?>
        Define global roles (e.g., superadmin, dept_admin) or other shared permissions.
    <?php else: ?>
        Roles will be saved with your department suffix (e.g., <code>ee.<?= htmlspecialchars($deptSlug ?? 'dept'); ?></code>).
    <?php endif; ?>
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
            <label>Role name (base ID)</label>
            <input type="text" name="role_name" required />
            <label>Display label (optional)</label>
            <input type="text" name="role_label" />
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
</div>
<hr>
<h3>Existing Roles</h3>
<table class="table">
    <thead>
        <tr><th>Role</th><th>Label</th><th>Permissions</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($rolePool as $name => $def): ?>
            <?php $normalized = normalize_role_definition($def); ?>
            <tr>
                <td><?= htmlspecialchars($name); ?></td>
                <td><?= htmlspecialchars($normalized['label'] ?? ''); ?></td>
                <td><?= htmlspecialchars(implode(', ', $normalized['permissions'])); ?></td>
                <td>
                    <?php if ($isSuperAdmin || ($deptSlug && str_ends_with($name, '.' . $deptSlug))): ?>
                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete role?');">
                            <input type="hidden" name="action" value="delete_role" />
                            <input type="hidden" name="role_name" value="<?= htmlspecialchars($name); ?>" />
                            <button class="btn danger" type="submit">Delete</button>
                        </form>
                    <?php else: ?>
                        <span class="muted">-</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
