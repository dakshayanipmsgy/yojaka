<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../roles.php';
require_once __DIR__ . '/../users.php';
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

$usersList = load_users();
$users = [];
foreach ($usersList as $u) {
    if (!isset($u['username'])) {
        continue;
    }
    $users[$u['username']] = $u;
}

$config = load_permissions_config();
$allRoles = $config['roles'] ?? [];
$departmentRoles = [];
if ($deptSlug !== null) {
    foreach ($allRoles as $roleId => $roleDef) {
        if (substr($roleId, -strlen('.' . $deptSlug)) === '.' . $deptSlug) {
            $departmentRoles[$roleId] = $roleDef;
        }
    }
}

function filter_users_by_department(array $users, ?string $deptSlug): array
{
    if ($deptSlug === null) {
        return $users;
    }

    $filtered = [];
    foreach ($users as $username => $user) {
        if (substr($username, -strlen('.' . $deptSlug)) === '.' . $deptSlug) {
            $filtered[$username] = $user;
        }
    }
    return $filtered;
}

$visibleUsers = $isSuperAdmin ? $users : filter_users_by_department($users, $deptSlug);

$errors = [];
$success = '';
$generatedPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isDeptAdmin) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $fullName = trim($_POST['full_name'] ?? '');
        $baseUsername = trim($_POST['base_username'] ?? '');
        $roleId = trim($_POST['role_id'] ?? '');
        $active = !empty($_POST['active']);
        $password = $_POST['password'] ?? '';

        if ($baseUsername === '' || strpos($baseUsername, '.') !== false) {
            $errors[] = 'Invalid base username.';
        }
        if ($roleId === '' || !isset($departmentRoles[$roleId])) {
            $errors[] = 'Invalid role selection.';
        }

        if (empty($errors)) {
            $roleParts = explode('.', $roleId, 2);
            $baseRoleId = $roleParts[0];
            $finalUsername = $baseUsername . '.' . $baseRoleId . '.' . $deptSlug;

            if (isset($users[$finalUsername])) {
                $errors[] = 'User with that name/role already exists.';
            } else {
                if ($password === '') {
                    $password = bin2hex(random_bytes(6));
                    $generatedPassword = $password;
                }

                $now = date('c');
                $usersList[] = [
                    'id' => next_user_id($usersList),
                    'username' => $finalUsername,
                    'full_name' => $fullName !== '' ? $fullName : $finalUsername,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $roleId,
                    'active' => $active,
                    'force_password_change' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (save_users($usersList)) {
                    $success = "User {$finalUsername} created.";
                    $usersList = load_users();
                    $users = [];
                    foreach ($usersList as $u) {
                        if (!isset($u['username'])) {
                            continue;
                        }
                        $users[$u['username']] = $u;
                    }
                    $visibleUsers = filter_users_by_department($users, $deptSlug);
                } else {
                    $errors[] = 'Unable to save user.';
                }
            }
        }
    }

    if ($action === 'update_user') {
        $username = $_POST['username'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $roleId = trim($_POST['role_id'] ?? '');
        $active = !empty($_POST['active']);
        $password = $_POST['password'] ?? '';

        if (!isset($users[$username])) {
            $errors[] = 'User not found.';
        } elseif (substr($username, -strlen('.' . $deptSlug)) !== '.' . $deptSlug) {
            $errors[] = 'Cannot edit user from another department.';
        } elseif (!isset($departmentRoles[$roleId])) {
            $errors[] = 'Invalid role selection.';
        }

        if (empty($errors)) {
            foreach ($usersList as &$record) {
                if (($record['username'] ?? '') !== $username) {
                    continue;
                }
                $record['full_name'] = $fullName !== '' ? $fullName : ($record['full_name'] ?? $username);
                $record['role'] = $roleId;
                $record['active'] = $active;
                $record['updated_at'] = date('c');
                if ($password !== '') {
                    $record['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $record['force_password_change'] = true;
                }
            }
            unset($record);

            if (save_users($usersList)) {
                $success = 'User updated.';
                $usersList = load_users();
                $users = [];
                foreach ($usersList as $u) {
                    if (!isset($u['username'])) {
                        continue;
                    }
                    $users[$u['username']] = $u;
                }
                $visibleUsers = filter_users_by_department($users, $deptSlug);
            } else {
                $errors[] = 'Unable to save user.';
            }
        }
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

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($generatedPassword): ?>
    <div class="alert info">
        Temporary password: <strong><?= htmlspecialchars($generatedPassword); ?></strong>
    </div>
<?php endif; ?>

<?php if ($isDeptAdmin): ?>
    <div class="card" style="margin-bottom: 1rem;">
        <div class="card-header">Add New User</div>
        <div class="card-body">
            <form method="post" class="form-stacked">
                <input type="hidden" name="action" value="create_user" />
                <div class="form-field">
                    <label for="base_username">Base Username</label>
                    <input type="text" id="base_username" name="base_username" required />
                    <div class="muted">Final username will be constructed as baseUser.baseRole.<?= htmlspecialchars($deptSlug ?? ''); ?></div>
                </div>
                <div class="form-field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" />
                </div>
                <div class="form-field">
                    <label for="role_id">Role</label>
                    <select name="role_id" id="role_id" required>
                        <?php foreach ($departmentRoles as $roleId => $roleDef): ?>
                            <option value="<?= htmlspecialchars($roleId); ?>"><?= htmlspecialchars($roleDef['label'] ?? $roleId); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label><input type="checkbox" name="active" value="1" checked /> Active</label>
                </div>
                <div class="form-field">
                    <label for="password">Password (leave blank to auto-generate)</label>
                    <input type="text" id="password" name="password" />
                </div>
                <div class="form-actions">
                    <button class="btn-primary" type="submit">Create User</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<h3>Existing Users</h3>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($visibleUsers)): ?>
                <tr><td colspan="5">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($visibleUsers as $username => $user): ?>
                    <?php $roleId = $user['role'] ?? ''; ?>
                    <?php $roleLabel = $allRoles[$roleId]['label'] ?? $roleId; ?>
                    <tr>
                        <td><?= htmlspecialchars($username); ?></td>
                        <td><?= htmlspecialchars($user['full_name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($roleLabel); ?></td>
                        <td><?= !empty($user['active']) ? 'Yes' : 'No'; ?></td>
                        <td>
                            <?php if ($isDeptAdmin): ?>
                                <details>
                                    <summary>Edit</summary>
                                    <form method="post" class="form-stacked" style="margin-top: 0.5rem;">
                                        <input type="hidden" name="action" value="update_user" />
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($username); ?>" />
                                        <div class="form-field">
                                            <label for="full_name_<?= htmlspecialchars($username); ?>">Full Name</label>
                                            <input type="text" id="full_name_<?= htmlspecialchars($username); ?>" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? ''); ?>" />
                                        </div>
                                        <div class="form-field">
                                            <label for="role_<?= htmlspecialchars($username); ?>">Role</label>
                                            <select name="role_id" id="role_<?= htmlspecialchars($username); ?>" required>
                                                <?php foreach ($departmentRoles as $deptRoleId => $roleDef): ?>
                                                    <option value="<?= htmlspecialchars($deptRoleId); ?>" <?= $deptRoleId === $roleId ? 'selected' : ''; ?>><?= htmlspecialchars($roleDef['label'] ?? $deptRoleId); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-field">
                                            <label><input type="checkbox" name="active" value="1" <?= !empty($user['active']) ? 'checked' : ''; ?> /> Active</label>
                                        </div>
                                        <div class="form-field">
                                            <label for="password_<?= htmlspecialchars($username); ?>">New Password (optional)</label>
                                            <input type="text" id="password_<?= htmlspecialchars($username); ?>" name="password" />
                                        </div>
                                        <div class="form-actions">
                                            <button class="btn" type="submit">Update</button>
                                        </div>
                                    </form>
                                </details>
                            <?php else: ?>
                                <span class="muted">Read only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($isSuperAdmin && !$isDeptAdmin): ?>
    <div class="alert info" style="margin-top: 1rem;">Superadmin view is read-only.</div>
<?php endif; ?>
