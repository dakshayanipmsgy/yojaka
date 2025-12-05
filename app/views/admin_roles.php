<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../roles.php';
require_once __DIR__ . '/../departments.php';

require_login();

$currentUser = get_current_user();
$isSuperAdmin = is_superadmin($currentUser);
$deptSlug = $currentUser ? get_current_department_slug($currentUser) : null;
$isDeptAdmin = $deptSlug !== null && user_is_department_admin($currentUser, $deptSlug);

if (!$isSuperAdmin && !$isDeptAdmin) {
    http_response_code(403);
    include __DIR__ . '/access_denied.php';
    return;
}

$permissionsConfig = load_permissions_config();
$rolesConfig = $permissionsConfig['roles'] ?? [];
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

$availablePermissions = collect_permissions_from_config($rolesConfig + ($permissionsConfig['custom_roles'] ?? []));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isDeptAdmin) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_role') {
        $baseRoleId = trim($_POST['base_role_id'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $perms = $_POST['permissions'] ?? [];

        if ($baseRoleId === '' || strpos($baseRoleId, '.') !== false) {
            $errors[] = 'Invalid base role ID.';
        } else {
            $roleId = $baseRoleId . '.' . $deptSlug;
            if (isset($rolesConfig[$roleId])) {
                $errors[] = 'Role already exists.';
            } else {
                $rolesConfig[$roleId] = [
                    'label' => $label !== '' ? $label : $baseRoleId,
                    'permissions' => array_values($perms),
                ];
                $permissionsConfig['roles'] = $rolesConfig;
                save_permissions_config($permissionsConfig);
                $messages[] = "Role {$roleId} created.";
            }
        }
    } elseif ($action === 'update_role') {
        $roleId = $_POST['role_id'] ?? '';
        $label = trim($_POST['label'] ?? '');
        $perms = $_POST['permissions'] ?? [];

        if (!isset($rolesConfig[$roleId])) {
            $errors[] = 'Role not found.';
        } elseif (substr($roleId, -strlen('.' . $deptSlug)) !== '.' . $deptSlug) {
            $errors[] = 'Cannot edit role outside your department.';
        } else {
            $rolesConfig[$roleId] = [
                'label' => $label,
                'permissions' => array_values($perms),
            ];
            $permissionsConfig['roles'] = $rolesConfig;
            save_permissions_config($permissionsConfig);
            $messages[] = 'Role updated.';
        }
    }

    $permissionsConfig = load_permissions_config();
    $rolesConfig = $permissionsConfig['roles'] ?? [];
    $availablePermissions = collect_permissions_from_config($rolesConfig + ($permissionsConfig['custom_roles'] ?? []));
}

$visibleRoles = [];
if ($isSuperAdmin) {
    foreach ($rolesConfig as $roleId => $roleDef) {
        if (!str_contains($roleId, '.') || str_starts_with($roleId, 'dept_admin.')) {
            $visibleRoles[$roleId] = $roleDef;
        }
    }
} elseif ($deptSlug !== null) {
    foreach ($rolesConfig as $roleId => $roleDef) {
        if (substr($roleId, -strlen('.' . $deptSlug)) === '.' . $deptSlug) {
            $visibleRoles[$roleId] = $roleDef;
        }
    }
}
?>

<div class="alert info">
    <?php if ($isSuperAdmin): ?>
        Manage global role definitions. Department-specific roles are created by each department admin.
    <?php else: ?>
        Create and edit roles scoped to your department (saved as <code>baseRoleId.<?= htmlspecialchars($deptSlug ?? ''); ?></code>).
    <?php endif; ?>
</div>
<?php foreach ($messages as $msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<?php if ($isDeptAdmin): ?>
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header">Create Department Role</div>
        <div class="card-body">
            <form method="post" class="form-stacked">
                <input type="hidden" name="action" value="create_role" />
                <div class="form-field">
                    <label for="base_role_id">Base Role ID</label>
                    <input type="text" name="base_role_id" id="base_role_id" required />
                </div>
                <div class="form-field">
                    <label for="label">Label</label>
                    <input type="text" name="label" id="label" />
                </div>
                <div class="form-field">
                    <label>Permissions</label>
                    <div class="checkbox-group">
                        <?php foreach ($availablePermissions as $perm): ?>
                            <label style="display:block;">
                                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm); ?>" /> <?= htmlspecialchars($perm); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="btn primary" type="submit">Create Role</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<h3>Existing Roles</h3>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr><th>Role</th><th>Label</th><th>Permissions</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if (empty($visibleRoles)): ?>
                <tr><td colspan="4">No roles available.</td></tr>
            <?php else: ?>
                <?php foreach ($visibleRoles as $roleId => $roleDef): ?>
                    <?php $normalized = normalize_role_definition($roleDef); ?>
                    <tr>
                        <td><?= htmlspecialchars($roleId); ?></td>
                        <td><?= htmlspecialchars($normalized['label'] ?? ''); ?></td>
                        <td><?= htmlspecialchars(implode(', ', $normalized['permissions'])); ?></td>
                        <td>
                            <?php $canEdit = $isSuperAdmin || ($isDeptAdmin && substr($roleId, -strlen('.' . $deptSlug)) === '.' . $deptSlug); ?>
                            <?php if ($canEdit): ?>
                                <details>
                                    <summary>Edit</summary>
                                    <form method="post" class="form-stacked" style="margin-top: 0.5rem;">
                                        <input type="hidden" name="action" value="update_role" />
                                        <input type="hidden" name="role_id" value="<?= htmlspecialchars($roleId); ?>" />
                                        <div class="form-field">
                                            <label>Label</label>
                                            <input type="text" name="label" value="<?= htmlspecialchars($normalized['label'] ?? ''); ?>" />
                                        </div>
                                        <div class="form-field">
                                            <label>Permissions</label>
                                            <div class="checkbox-group">
                                                <?php foreach ($availablePermissions as $perm): ?>
                                                    <label style="display:block;">
                                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm); ?>" <?= in_array($perm, $normalized['permissions'], true) ? 'checked' : ''; ?> /> <?= htmlspecialchars($perm); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <button class="btn" type="submit">Update</button>
                                    </form>
                                </details>
                            <?php else: ?>
                                <span class="muted">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
