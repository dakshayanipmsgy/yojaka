<?php
require_permission('manage_users');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../departments.php';
require_once __DIR__ . '/../roles.php';

$currentUser = current_user();
$deptSlug = $currentUser ? get_current_department_slug_for_user($currentUser) : null;
$isSuperAdmin = ($currentUser['role'] ?? '') === 'superadmin';

$csrfToken = $_SESSION['user_admin_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['user_admin_csrf'] = $csrfToken;
$action = $_GET['action'] ?? 'list';
$editUsername = $_GET['edit_username'] ?? null;
$errors = [];
$notice = '';
$generatedPassword = '';

$users = load_users();
$departments = load_departments();
$activeDepartments = array_filter($departments, static function ($dept) {
    return !isset($dept['active']) || $dept['active'];
});

$permissionsConfig = load_permissions_config();
$allRoleDefs = ($permissionsConfig['roles'] ?? []) + ($permissionsConfig['custom_roles'] ?? []);
if (!$isSuperAdmin) {
    $allRoleDefs = filter_roles_for_department($allRoleDefs, $deptSlug, false);
}
$roleChoices = [];
foreach ($allRoleDefs as $roleId => $roleDef) {
    if (!$isSuperAdmin && in_array($roleId, ['superadmin', 'dept_admin'], true)) {
        continue;
    }
    $roleChoices[$roleId] = [
        'id' => $roleId,
        'label' => format_role_label($roleId, $roleDef),
    ];
}

function user_matches_department(?string $deptSlug, array $user): bool
{
    if ($deptSlug === null) {
        return true;
    }
    $username = $user['username'] ?? '';
    return str_ends_with($username, '.' . $deptSlug);
}

$visibleUsers = array_values(array_filter($users, function ($u) use ($deptSlug, $isSuperAdmin) {
    return $isSuperAdmin ? true : user_matches_department($deptSlug, $u);
}));

if ($editUsername && !$isSuperAdmin && !user_matches_department($deptSlug, ['username' => $editUsername])) {
    $errors[] = 'You can only edit users from your department.';
    $editUsername = null;
}

$editUser = $editUsername ? find_user($editUsername) : null;

function admin_users_generate_password(): string
{
    return substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(18))), 0, 12);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['user_admin_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user' && empty($errors)) {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $active = !empty($_POST['active']);
    $newPassword = $_POST['new_password'] ?? '';

    $targetUser = $username ? find_user($username) : null;
    if (!$targetUser) {
        $errors[] = 'User not found.';
    }

    if (!$isSuperAdmin && !user_matches_department($deptSlug, ['username' => $username])) {
        $errors[] = 'You can only edit users from your department.';
    }

    if (!isset($roleChoices[$role])) {
        $errors[] = 'Invalid role selected.';
    }

    if (empty($errors) && $targetUser) {
        $targetUser['full_name'] = $fullName !== '' ? $fullName : ($targetUser['full_name'] ?? $username);
        $targetUser['role'] = $role;
        $targetUser['active'] = $active;
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            } else {
                $targetUser['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $targetUser['force_password_change'] = true;
            }
        }

        if (empty($errors) && save_user($targetUser)) {
            $notice = 'User updated successfully.';
            $users = load_users();
            $visibleUsers = array_values(array_filter($users, function ($u) use ($deptSlug, $isSuperAdmin) {
                return $isSuperAdmin ? true : user_matches_department($deptSlug, $u);
            }));
            $editUser = find_user($username);
        } else {
            $errors[] = 'Unable to save user record.';
        }
    }
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $baseUsername = trim($_POST['base_username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $roleId = trim($_POST['role'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $chosenDept = $deptSlug ?? ($_POST['department_slug'] ?? '');

    if ($chosenDept === '' && !$isSuperAdmin) {
        $errors[] = 'Department is required.';
    }

    if ($baseUsername === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $baseUsername)) {
        $errors[] = 'Base username is required and may only contain letters, numbers, dots, underscores, and hyphens.';
    }

    if (!isset($roleChoices[$roleId])) {
        $errors[] = 'Invalid role selected.';
    }

    if (!$isSuperAdmin && $chosenDept && !str_ends_with($roleId, '.' . $chosenDept)) {
        $errors[] = 'Role must belong to your department.';
    }

    if ($isSuperAdmin && $chosenDept === '' && str_contains($roleId, '.')) {
        $errors[] = 'Please choose a department for department-scoped roles.';
    }

    if ($password === '') {
        $password = admin_users_generate_password();
        $generatedPassword = $password;
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (empty($errors)) {
        $deptForUser = $chosenDept ?: null;
        if ($deptForUser) {
            $baseRoleId = extract_base_role_id($roleId);
            $finalUsername = build_department_username($baseUsername, $baseRoleId, $deptForUser);
        } else {
            $finalUsername = $baseUsername;
        }

        if (user_exists($finalUsername)) {
            $errors[] = 'Username is already taken.';
        }
    }

    if (empty($errors)) {
        $now = gmdate('c');
        $newUser = [
            'username' => $finalUsername,
            'full_name' => $fullName !== '' ? $fullName : $finalUsername,
            'role' => $roleId,
            'active' => true,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'force_password_change' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'department_slug' => $deptForUser,
            'base_username' => $baseUsername,
            'base_role_id' => extract_base_role_id($roleId),
        ];

        if (save_user($newUser)) {
            $notice = 'User created successfully.';
            $users = load_users();
            $visibleUsers = array_values(array_filter($users, function ($u) use ($deptSlug, $isSuperAdmin) {
                return $isSuperAdmin ? true : user_matches_department($deptSlug, $u);
            }));
        } else {
            $errors[] = 'Unable to save user record.';
        }
    }
}

if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $username = $_POST['username'] ?? '';
    $targetUser = find_user($username);
    if (!$targetUser) {
        $errors[] = 'User not found.';
    } elseif (!$isSuperAdmin && !user_matches_department($deptSlug, $targetUser)) {
        $errors[] = 'You can only reset passwords for your department.';
    } else {
        $password = trim($_POST['password'] ?? '');
        if ($password === '') {
            $password = admin_users_generate_password();
            $generatedPassword = $password;
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if (empty($errors)) {
            $targetUser['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $targetUser['force_password_change'] = true;
            $targetUser['updated_at'] = gmdate('c');
            if (save_user($targetUser)) {
                $notice = i18n_get('users.password_reset_success');
                $generatedPassword = $generatedPassword ?: $password;
                $users = load_users();
                $visibleUsers = array_values(array_filter($users, function ($u) use ($deptSlug, $isSuperAdmin) {
                    return $isSuperAdmin ? true : user_matches_department($deptSlug, $u);
                }));
                $action = 'list';
            } else {
                $errors[] = 'Unable to reset password.';
            }
        }
    }
}

$targetUser = null;
if ($action === 'reset' && isset($_GET['username'])) {
    $targetUser = find_user($_GET['username']);
    if (!$targetUser || (!$isSuperAdmin && !user_matches_department($deptSlug, $targetUser))) {
        $errors[] = 'User not found or inaccessible.';
        $action = 'list';
    }
}
?>

<div class="flex" style="justify-content: space-between; align-items:center; margin-bottom: 10px;">
    <h2>User Administration</h2>
    <a class="btn ghost" target="_blank" href="<?= YOJAKA_BASE_URL ?>/app.php?page=help_user_roles">Help</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($notice): ?>
    <div class="alert alert-success"><?= htmlspecialchars($notice); ?></div>
<?php endif; ?>

<?php if ($generatedPassword): ?>
    <div class="alert info">
        <?= htmlspecialchars(i18n_get('users.password_reset_success')); ?><br>
        <strong><?= htmlspecialchars($generatedPassword); ?></strong>
    </div>
<?php endif; ?>

<?php if ($editUser): ?>
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header">Edit User: <?= htmlspecialchars($editUser['username']); ?></div>
        <div class="card-body">
            <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="username" value="<?= htmlspecialchars($editUser['username']); ?>">
                <input type="hidden" name="action" value="update_user">

                <div class="form-field">
                    <label><?= htmlspecialchars(i18n_get('users.username')); ?></label>
                    <input type="text" value="<?= htmlspecialchars($editUser['username']); ?>" disabled>
                </div>

                <div class="form-field">
                    <label for="full_name_edit">Full Name</label>
                    <input type="text" id="full_name_edit" name="full_name" value="<?= htmlspecialchars($editUser['full_name'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label for="role_edit">Role</label>
                    <select name="role" id="role_edit" required>
                        <?php foreach ($roleChoices as $role): ?>
                            <option value="<?= htmlspecialchars($role['id']); ?>" <?= ($editUser['role'] ?? '') === ($role['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($role['label'] ?? $role['id']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="new_password">New Password (leave blank to keep current)</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                </div>

                <div class="form-field">
                    <label><input type="checkbox" name="active" value="1" <?= !empty($editUser['active']) ? 'checked' : ''; ?>><?= htmlspecialchars(i18n_get('users.active')); ?></label>
                </div>

                <div class="form-actions">
                    <button class="btn-primary" type="submit"><?= htmlspecialchars(i18n_get('btn.save')); ?></button>
                    <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'reset' && $targetUser): ?>
    <h3><?= htmlspecialchars(i18n_get('users.reset_password')); ?> - <?= htmlspecialchars($targetUser['username']); ?></h3>
    <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=reset&username=<?= urlencode($targetUser['username']); ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="username" value="<?= htmlspecialchars($targetUser['username']); ?>">
        <div class="form-field">
            <label for="password"><?= htmlspecialchars(i18n_get('users.new_password')); ?> (leave blank to auto-generate)</label>
            <input type="text" id="password" name="password" value="">
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit"><?= htmlspecialchars(i18n_get('users.reset_password')); ?></button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users"><?= htmlspecialchars(i18n_get('btn.cancel')); ?></a>
        </div>
    </form>
<?php endif; ?>

<h3><?= htmlspecialchars(i18n_get('users.title')); ?></h3>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(i18n_get('users.username')); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.full_name')); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.role')); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.active')); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.created_at') ?? 'Created'); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.actions') ?? 'Actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($visibleUsers)): ?>
                <tr><td colspan="6">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($visibleUsers as $listedUser): ?>
                    <tr>
                        <td><?= htmlspecialchars($listedUser['username'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($listedUser['full_name'] ?? ''); ?></td>
                        <td><span class="badge"><?= htmlspecialchars($listedUser['role'] ?? ''); ?></span></td>
                        <td><?= !empty($listedUser['active']) ? i18n_get('portal.yes') : i18n_get('portal.no'); ?></td>
                        <td><?= htmlspecialchars($listedUser['created_at'] ?? ''); ?></td>
                        <td>
                            <form method="get" style="display:inline;">
                                <input type="hidden" name="page" value="admin_users">
                                <input type="hidden" name="edit_username" value="<?= htmlspecialchars($listedUser['username']); ?>">
                                <button type="submit" class="button" style="margin-bottom:0;">Edit</button>
                            </form>
                            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=reset&username=<?= urlencode($listedUser['username']); ?>"><?= htmlspecialchars(i18n_get('users.reset_password')); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3><?= htmlspecialchars(i18n_get('users.add_new')); ?></h3>
<form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=create">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
    <div class="form-field">
        <label for="base_username">Base Username</label>
        <input type="text" id="base_username" name="base_username" required>
        <div class="muted">Final username will include role and department suffix automatically.</div>
    </div>
    <div class="form-field">
        <label for="full_name"><?= htmlspecialchars(i18n_get('users.full_name')); ?></label>
        <input type="text" id="full_name" name="full_name">
    </div>
    <?php if ($isSuperAdmin): ?>
        <div class="form-field">
            <label for="department_slug">Department (optional for global users)</label>
            <select name="department_slug" id="department_slug">
                <option value="">-- Global (no suffix) --</option>
                <?php foreach ($activeDepartments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['slug'] ?? $dept['id']); ?>"><?= htmlspecialchars($dept['name'] ?? ($dept['slug'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="form-field">
        <label for="role"><?= htmlspecialchars(i18n_get('users.role')); ?></label>
        <select name="role" id="role" required>
            <?php foreach ($roleChoices as $role): ?>
                <option value="<?= htmlspecialchars($role['id']); ?>"><?= htmlspecialchars($role['label'] ?? $role['id']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label for="password"><?= htmlspecialchars(i18n_get('users.new_password')); ?> (leave blank to auto-generate)</label>
        <input type="text" id="password" name="password">
    </div>
    <div class="form-actions">
        <button class="btn-primary" type="submit"><?= htmlspecialchars(i18n_get('btn.save')); ?></button>
    </div>
</form>
