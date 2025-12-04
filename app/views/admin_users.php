<?php
require_permission('manage_users');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../departments.php';

$currentUser = current_user();

$csrfToken = $_SESSION['user_admin_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['user_admin_csrf'] = $csrfToken;
$action = $_GET['action'] ?? 'list';
$usernameParam = $_GET['username'] ?? '';
$errors = [];
$notice = '';
$generatedPassword = '';

$users = load_users();
$departmentProfiles = load_departments();
$departmentProfiles = array_column($departmentProfiles, null, 'id');
$deptNames = [];
foreach ($departmentProfiles as $deptId => $dept) {
    $deptNames[$deptId] = $dept['name'] ?? $deptId;
}
$offices = load_offices_registry();
$officeNames = [];
foreach ($offices as $office) {
    $officeNames[$office['id']] = $office['name'] ?? ($office['short_name'] ?? $office['id']);
}
if (empty($officeNames)) {
    $officeNames[get_default_office_id()] = 'Default Office';
}
$allRoles = get_all_roles_for_dropdown();
$roleOptions = array_column($allRoles, 'id');
if (empty($roleOptions) && !empty($config['roles_permissions'])) {
    $roleOptions = array_keys($config['roles_permissions']);
    $allRoles = array_map(static function ($roleId) {
        return [
            'id' => $roleId,
            'label' => ucfirst(str_replace('_', ' ', $roleId)),
        ];
    }, $roleOptions);
}

function admin_users_generate_password(): string
{
    return substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(12))), 0, 12);
}

function admin_users_count_active_admins(array $users): int
{
    $count = 0;
    foreach ($users as $user) {
        if (($user['role'] ?? '') === 'admin' && !empty($user['active'])) {
            $count++;
        }
    }
    return $count;
}

function admin_users_validate_role(string $role, array $roleOptions): bool
{
    return in_array($role, $roleOptions, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['user_admin_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $departmentId = trim($_POST['department_id'] ?? '');
    $officeId = trim($_POST['office_id'] ?? get_default_office_id());
    $active = !empty($_POST['active']);
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        $errors[] = 'Username is required and may only contain letters, numbers, dots, underscores, and hyphens.';
    }
    if (user_exists($username)) {
        $errors[] = 'Username is already taken.';
    }
    if (!admin_users_validate_role($role, $roleOptions)) {
        $errors[] = 'Invalid role selected.';
    }
    if ($departmentId !== '' && !isset($deptNames[$departmentId])) {
        $errors[] = 'Invalid department selected.';
    }
    if ($officeId === '') {
        $officeId = get_default_office_id();
    }

    if ($password === '') {
        $password = admin_users_generate_password();
        $generatedPassword = $password;
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (empty($errors)) {
        $now = gmdate('c');
        $newUser = [
            'username' => $username,
            'full_name' => $fullName !== '' ? $fullName : $username,
            'role' => $role,
            'department_id' => $departmentId ?: null,
            'office_id' => $officeId,
            'active' => $active,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'force_password_change' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (save_user($newUser)) {
            $notice = 'User created successfully.';
            log_event('user_created', $_SESSION['username'] ?? null, ['target' => $username, 'role' => $role]);
            write_audit_log('user', $username, 'created', ['created_by' => $_SESSION['username'] ?? 'system']);
            $users = load_users();
        } else {
            $errors[] = 'Unable to save user record.';
        }
    }
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $username = $_POST['username'] ?? '';
    $targetUser = find_user($username);
    if (!$targetUser) {
        $errors[] = 'User not found.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $departmentId = trim($_POST['department_id'] ?? '');
        $officeId = trim($_POST['office_id'] ?? get_default_office_id());
        $active = !empty($_POST['active']);

        if (!admin_users_validate_role($role, $roleOptions)) {
            $errors[] = 'Invalid role selected.';
        }
        if ($departmentId !== '' && !isset($deptNames[$departmentId])) {
            $errors[] = 'Invalid department selected.';
        }
        $adminCount = admin_users_count_active_admins($users);
        $demotingLastAdmin = (($targetUser['role'] ?? '') === 'admin' && $role !== 'admin' && $adminCount <= 1) || (($targetUser['role'] ?? '') === 'admin' && !$active && $adminCount <= 1);
        if ($demotingLastAdmin) {
            $errors[] = 'Cannot remove admin role or deactivate the last active admin user.';
        }

        if (empty($errors)) {
            $targetUser['full_name'] = $fullName !== '' ? $fullName : ($targetUser['full_name'] ?? $username);
            $targetUser['role'] = $role;
            $targetUser['department_id'] = $departmentId ?: null;
            $targetUser['office_id'] = $officeId;
            $targetUser['active'] = $active;
            $targetUser['updated_at'] = gmdate('c');
            if (save_user($targetUser)) {
                $notice = 'User updated successfully.';
                log_event('user_updated', $_SESSION['username'] ?? null, ['updated_username' => $username]);
                write_audit_log('user', $username, 'updated', ['updated_by' => $_SESSION['username'] ?? 'system']);
                $users = load_users();
            } else {
                $errors[] = 'Unable to save user record.';
            }
        }
    }
}

if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $username = $_POST['username'] ?? '';
    $targetUser = find_user($username);
    if (!$targetUser) {
        $errors[] = 'User not found.';
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
                log_event('user_password_reset', $_SESSION['username'] ?? null, ['target' => $username]);
                write_audit_log('user', $username, 'password_reset', ['reset_by' => $_SESSION['username'] ?? 'system']);
                $users = load_users();
                $action = 'list';
            } else {
                $errors[] = 'Unable to reset password.';
            }
        }
    }
}

$targetUser = null;
if (in_array($action, ['edit', 'reset'], true) && $usernameParam) {
    $targetUser = find_user($usernameParam);
    if (!$targetUser) {
        $errors[] = 'User not found.';
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

<?php if ($action === 'edit' && $targetUser): ?>
    <h3><?= htmlspecialchars(i18n_get('users.title')); ?> - <?= htmlspecialchars(i18n_get('btn.save')); ?></h3>
    <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=edit&username=<?= urlencode($targetUser['username']); ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <div class="form-field">
            <label><?= htmlspecialchars(i18n_get('users.username')); ?></label>
            <input type="text" value="<?= htmlspecialchars($targetUser['username']); ?>" disabled>
            <input type="hidden" name="username" value="<?= htmlspecialchars($targetUser['username']); ?>">
        </div>
        <div class="form-field">
            <label for="full_name"><?= htmlspecialchars(i18n_get('users.full_name')); ?></label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($targetUser['full_name'] ?? ''); ?>">
        </div>
        <div class="form-field">
            <label for="role"><?= htmlspecialchars(i18n_get('users.role')); ?></label>
            <select name="role" id="role" required>
                <?php foreach ($allRoles as $role): ?>
                    <option value="<?= htmlspecialchars($role['id']); ?>" <?= ($targetUser['role'] ?? '') === ($role['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($role['label'] ?? $role['id']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="department_id"><?= htmlspecialchars(i18n_get('users.department')); ?></label>
            <select name="department_id" id="department_id">
                <option value="">--</option>
                <?php foreach ($departmentProfiles as $deptId => $dept): ?>
                    <option value="<?= htmlspecialchars($deptId); ?>" <?= ($targetUser['department_id'] ?? '') === $deptId ? 'selected' : ''; ?>><?= htmlspecialchars($dept['name'] ?? $deptId); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="office_id"><?= htmlspecialchars(i18n_get('users.office')); ?></label>
            <select name="office_id" id="office_id">
                <?php foreach ($officeNames as $id => $name): ?>
                    <option value="<?= htmlspecialchars($id); ?>" <?= ($targetUser['office_id'] ?? '') === $id ? 'selected' : ''; ?>><?= htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label><input type="checkbox" name="active" value="1" <?= !empty($targetUser['active']) ? 'checked' : ''; ?>> <?= htmlspecialchars(i18n_get('users.active')); ?></label>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit"><?= htmlspecialchars(i18n_get('btn.save')); ?></button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users"><?= htmlspecialchars(i18n_get('btn.cancel')); ?></a>
        </div>
    </form>
<?php elseif ($action === 'reset' && $targetUser): ?>
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
                <th><?= htmlspecialchars(i18n_get('users.department')); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.active')); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.created_at') ?? 'Created'); ?></th>
                <th><?= htmlspecialchars(i18n_get('users.actions') ?? 'Actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="7">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $listedUser): ?>
                    <tr>
                        <td><?= htmlspecialchars($listedUser['username'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($listedUser['full_name'] ?? ''); ?></td>
                        <td><span class="badge"><?= htmlspecialchars($listedUser['role'] ?? ''); ?></span></td>
                        <?php 
                        $deptId = $listedUser['department_id'] ?? ''; 
                        $deptName = $departmentProfiles[$deptId]['name'] ?? ''; 
                        ?>
                        <td><?= htmlspecialchars($deptName); ?></td>
                        <td><?= !empty($listedUser['active']) ? i18n_get('portal.yes') : i18n_get('portal.no'); ?></td>
                        <td><?= htmlspecialchars($listedUser['created_at'] ?? ''); ?></td>
                        <td>
                            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=edit&username=<?= urlencode($listedUser['username']); ?>"><?= htmlspecialchars(i18n_get('btn.save')); ?></a>
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
        <label for="username"><?= htmlspecialchars(i18n_get('users.username')); ?></label>
        <input type="text" id="username" name="username" required>
    </div>
    <div class="form-field">
        <label for="full_name"><?= htmlspecialchars(i18n_get('users.full_name')); ?></label>
        <input type="text" id="full_name" name="full_name">
    </div>
    <div class="form-field">
        <label for="role"><?= htmlspecialchars(i18n_get('users.role')); ?></label>
        <select name="role" id="role" required>
            <?php foreach ($allRoles as $role): ?>
                <option value="<?= htmlspecialchars($role['id']); ?>"><?= htmlspecialchars($role['label'] ?? $role['id']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label for="office_id"><?= htmlspecialchars(i18n_get('users.office')); ?></label>
        <select name="office_id" id="office_id">
            <?php foreach ($officeNames as $id => $name): ?>
                <option value="<?= htmlspecialchars($id); ?>" <?= $id === get_default_office_id() ? 'selected' : ''; ?>><?= htmlspecialchars($name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label for="department_id"><?= htmlspecialchars(i18n_get('users.department')); ?></label>
        <select name="department_id" id="department_id">
            <option value="">--</option>
            <?php foreach ($departmentProfiles as $deptId => $dept): ?>
                <option value="<?= htmlspecialchars($deptId); ?>"><?= htmlspecialchars($dept['name'] ?? $deptId); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label for="password"><?= htmlspecialchars(i18n_get('users.new_password')); ?> (leave blank to auto-generate)</label>
        <input type="text" id="password" name="password">
    </div>
    <div class="form-field">
        <label><input type="checkbox" name="active" value="1" checked> <?= htmlspecialchars(i18n_get('users.active')); ?></label>
    </div>
    <div class="form-actions">
        <button class="btn-primary" type="submit"><?= htmlspecialchars(i18n_get('btn.save')); ?></button>
    </div>
</form>
