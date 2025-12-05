<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../roles.php';
require_once __DIR__ . '/../departments.php';
require_once __DIR__ . '/../permissions_catalog.php';

require_login();

$currentUser = yojaka_current_user();
$deptSlug = $currentUser ? get_current_department_slug($currentUser) : null;
$isSuperAdmin = $currentUser ? is_superadmin_user($currentUser) : false;
$canManageRoles = $isSuperAdmin || ($currentUser && has_permission($currentUser, 'dept.roles.manage'));

if (!$canManageRoles) {
    http_response_code(403);
    include __DIR__ . '/access_denied.php';
    return;
}

$catalog = load_permissions_catalog();
$catalogPermissions = $catalog['permissions'] ?? [];
$availablePermissionKeys = array_keys($catalogPermissions);
sort($availablePermissionKeys);

$rolesData = load_roles_permissions();
$rolesConfig = $rolesData['roles'] ?? [];
$messages = [];
$errors = [];

function normalize_permissions(array $selected, array $allowedKeys, array &$errors): array
{
    $final = [];
    foreach ($selected as $key) {
        if (!in_array($key, $allowedKeys, true)) {
            $errors[] = 'Invalid permission key: ' . htmlspecialchars($key);
            continue;
        }
        $final[] = $key;
    }
    return array_values(array_unique($final));
}

function permission_label(string $key, array $catalogPermissions): string
{
    $meta = $catalogPermissions[$key] ?? [];
    $label = $meta['label'] ?? $key;
    $module = $meta['module'] ?? null;
    return $module ? ($label . ' (' . $module . ')') : $label;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $deptSlug !== null) {
    $action = $_POST['action'] ?? '';
    $label = trim($_POST['label'] ?? '');
    $selectedPermissions = $_POST['permissions'] ?? [];
    $normalizedPermissions = normalize_permissions((array) $selectedPermissions, $availablePermissionKeys, $errors);

    if ($action === 'create_role') {
        $baseRoleId = trim($_POST['base_role_id'] ?? '');
        if ($baseRoleId === '' || strpos($baseRoleId, '.') !== false) {
            $errors[] = 'Invalid base role ID.';
        } else {
            $roleId = $baseRoleId . '.' . $deptSlug;
            if (isset($rolesConfig[$roleId])) {
                $errors[] = 'Role already exists.';
            }
        }

        if (empty($errors)) {
            $rolesConfig[$roleId] = [
                'label' => $label !== '' ? $label : $baseRoleId,
                'permissions' => $normalizedPermissions,
            ];
            save_roles_permissions(['roles' => $rolesConfig]);
            $messages[] = "Role {$roleId} created.";
            $rolesData = load_roles_permissions();
            $rolesConfig = $rolesData['roles'] ?? [];
        }
    } elseif ($action === 'update_role') {
        $roleId = $_POST['role_id'] ?? '';
        if (!isset($rolesConfig[$roleId])) {
            $errors[] = 'Role not found.';
        } elseif (!$isSuperAdmin && substr($roleId, -strlen('.' . $deptSlug)) !== '.' . $deptSlug) {
            $errors[] = 'Cannot edit role outside your department.';
        }

        if (empty($errors)) {
            $rolesConfig[$roleId] = [
                'label' => $label !== '' ? $label : $roleId,
                'permissions' => $normalizedPermissions,
            ];
            save_roles_permissions(['roles' => $rolesConfig]);
            $messages[] = 'Role updated.';
            $rolesData = load_roles_permissions();
            $rolesConfig = $rolesData['roles'] ?? [];
        }
    }
}

$visibleRoles = [];
if ($isSuperAdmin) {
    $visibleRoles = $rolesConfig;
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
        Edit roles scoped to your department (saved as <code>baseRoleId.<?= htmlspecialchars($deptSlug ?? ''); ?></code>).
    <?php endif; ?>
</div>
<?php foreach ($messages as $msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<?php if ($deptSlug !== null): ?>
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
                        <?php foreach ($availablePermissionKeys as $permKey): ?>
                            <label style="display:block;">
                                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permKey); ?>" />
                                <?= htmlspecialchars(permission_label($permKey, $catalogPermissions)); ?>
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
                    <?php $rolePermissions = $roleDef['permissions'] ?? []; ?>
                    <tr>
                        <td><?= htmlspecialchars($roleId); ?></td>
                        <td><?= htmlspecialchars($roleDef['label'] ?? $roleId); ?></td>
                        <td>
                            <?php if (empty($rolePermissions)): ?>
                                <span class="muted">No permissions</span>
                            <?php else: ?>
                                <ul style="margin:0; padding-left: 1.2rem;">
                                    <?php foreach ($rolePermissions as $permKey): ?>
                                        <li><?= htmlspecialchars(permission_label($permKey, $catalogPermissions)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $canEdit = $isSuperAdmin || ($deptSlug !== null && substr($roleId, -strlen('.' . $deptSlug)) === '.' . $deptSlug); ?>
                            <?php if ($canEdit): ?>
                                <details>
                                    <summary>Edit</summary>
                                    <form method="post" class="form-stacked" style="margin-top: 0.5rem;">
                                        <input type="hidden" name="action" value="update_role" />
                                        <input type="hidden" name="role_id" value="<?= htmlspecialchars($roleId); ?>" />
                                        <div class="form-field">
                                            <label>Label</label>
                                            <input type="text" name="label" value="<?= htmlspecialchars($roleDef['label'] ?? $roleId); ?>" />
                                        </div>
                                        <div class="form-field">
                                            <label>Permissions</label>
                                            <div class="checkbox-group">
                                                <?php foreach ($availablePermissionKeys as $permKey): ?>
                                                    <label style="display:block;">
                                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permKey); ?>" <?= in_array($permKey, $rolePermissions, true) ? 'checked' : ''; ?> />
                                                        <?= htmlspecialchars(permission_label($permKey, $catalogPermissions)); ?>
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
