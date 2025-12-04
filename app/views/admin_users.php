<?php
require_permission('manage_users');
$users = load_users();
$departments = load_departments();
$deptNames = [];
foreach ($departments as $dept) {
    $deptNames[$dept['id']] = $dept['name'] ?? $dept['id'];
}
$action = $_GET['action'] ?? 'list';
$errors = [];
$notice = '';
$csrfToken = $_SESSION['user_admin_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['user_admin_csrf'] = $csrfToken;

if ($action === 'edit') {
    $username = $_GET['username'] ?? '';
    $targetUser = $username ? find_user_by_username($username) : null;
    if (!$targetUser) {
        $errors[] = 'User not found.';
        $action = 'list';
    }
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['user_admin_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $username = $_POST['username'] ?? '';
    $usersList = $users;
    $role = $_POST['role'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $departmentId = $_POST['department_id'] ?? '';
    $active = !empty($_POST['active']);

    if (!isset($config['roles_permissions'][$role])) {
        $errors[] = 'Invalid role selected.';
    }
    if (!isset($deptNames[$departmentId])) {
        $errors[] = 'Invalid department selected.';
    }

    if (empty($errors)) {
        foreach ($usersList as &$user) {
            if (($user['username'] ?? '') === $username) {
                $user['role'] = $role;
                $user['department_id'] = $departmentId;
                $user['active'] = $active;
                if ($fullName !== '') {
                    $user['full_name'] = $fullName;
                }
                break;
            }
        }
        unset($user);
        save_users($usersList);
        log_event('user_updated', $_SESSION['username'] ?? null, ['updated_username' => $username]);
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=admin_users');
        exit;
    }
    $users = $usersList;
    $targetUser = find_user_by_username($username);
}
?>

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

<?php if ($action === 'edit' && !empty($targetUser)): ?>
    <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=edit&username=<?= urlencode($targetUser['username']); ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <div class="form-field">
            <label>Username</label>
            <input type="text" value="<?= htmlspecialchars($targetUser['username']); ?>" disabled>
            <input type="hidden" name="username" value="<?= htmlspecialchars($targetUser['username']); ?>">
        </div>
        <div class="form-field">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($targetUser['full_name'] ?? ''); ?>">
        </div>
        <div class="form-field">
            <label for="role">Role</label>
            <select name="role" id="role" required>
                <?php foreach (array_keys($config['roles_permissions'] ?? []) as $roleKey): ?>
                    <option value="<?= htmlspecialchars($roleKey); ?>" <?= ($targetUser['role'] ?? '') === $roleKey ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst($roleKey)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="department_id">Department</label>
            <select name="department_id" id="department_id" required>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['id']); ?>" <?= ($targetUser['department_id'] ?? '') === ($dept['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($dept['name'] ?? $dept['id']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label><input type="checkbox" name="active" value="1" <?= !empty($targetUser['active']) ? 'checked' : ''; ?>> Active</label>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit">Save User</button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users">Cancel</a>
        </div>
    </form>
<?php else: ?>
    <div class="info">
        <p>Manage user roles and department associations.</p>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Active</th>
                    <th>Created At</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($user['username'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($user['full_name'] ?? ''); ?></td>
                            <td><span class="badge"><?= htmlspecialchars($user['role'] ?? ''); ?></span></td>
                            <td><?= htmlspecialchars($deptNames[$user['department_id'] ?? ''] ?? ''); ?></td>
                            <td><?= !empty($user['active']) ? 'Yes' : 'No'; ?></td>
                            <td><?= htmlspecialchars($user['created_at'] ?? ''); ?></td>
                            <td><a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users&action=edit&username=<?= urlencode($user['username']); ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
