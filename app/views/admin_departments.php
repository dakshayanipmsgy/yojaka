<?php
require_login();
require_once __DIR__ . '/../departments.php';
require_once __DIR__ . '/../users.php';
require_once __DIR__ . '/../roles.php';

$currentUser = current_user();
if (!is_superadmin($currentUser)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$departments = load_departments();
$errors = [];
$notice = '';
$generatedAdmin = null;
$csrfToken = $_SESSION['dept_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['dept_csrf'] = $csrfToken;

$statusLabels = [
    'active' => 'Active',
    'suspended' => 'Suspended',
    'archived' => 'Archived',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['dept_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $action = $_POST['form_action'] ?? '';

    if ($action === 'create_department' && empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $errors[] = 'Department name is required.';
        }

        if (empty($errors)) {
            $departments = load_departments();
            $slug = make_unique_department_slug($name, $departments);
            $now = date('c');

            $departments[$slug] = [
                'id' => $slug,
                'slug' => $slug,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'status' => 'active',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (!save_departments($departments)) {
                $errors[] = 'Unable to save department record.';
            } else {
                $deptAdminRoleId = ensure_department_admin_role($slug);
                $adminInfo = create_department_admin_user($slug, $name, $deptAdminRoleId);
                if (!empty($adminInfo['created'])) {
                    $generatedAdmin = $adminInfo;
                }
                $notice = 'Department created successfully.';
                $departments = load_departments();
            }
        }
    }

    if ($action === 'update_status' && empty($errors)) {
        $slug = $_POST['slug'] ?? '';
        $newStatus = $_POST['status'] ?? '';

        if (!isset($departments[$slug])) {
            $errors[] = 'Department not found.';
        } elseif (!isset($statusLabels[$newStatus])) {
            $errors[] = 'Invalid status selected.';
        } else {
            $departments[$slug]['status'] = $newStatus;
            $departments[$slug]['active'] = ($newStatus === 'active');
            $departments[$slug]['updated_at'] = date('c');

            if (!save_departments($departments)) {
                $errors[] = 'Unable to update department status.';
            } else {
                $notice = 'Department status updated.';
                $departments = load_departments();
            }
        }
    }
}

$displayDepartments = $departments;
uasort($displayDepartments, static function ($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});
?>

<div class="flex" style="justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h2>Departments</h2>
    <a class="btn-primary" href="#create-department">Create Department</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($notice): ?>
    <div class="alert alert-success"><?= htmlspecialchars($notice); ?></div>
<?php endif; ?>

<?php if ($generatedAdmin): ?>
    <div class="alert info">
        <strong>Initial Department Admin Credentials</strong><br>
        Username: <?= htmlspecialchars($generatedAdmin['username']); ?><br>
        Password: <?= htmlspecialchars($generatedAdmin['password_plain']); ?><br>
        Please share securely. The user will be asked to change the password on first login.
    </div>
<?php endif; ?>

<div class="table-responsive" style="margin-bottom: 2rem;">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Code</th>
                <th>Status</th>
                <th>Created</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($displayDepartments)): ?>
                <tr><td colspan="7">No departments defined.</td></tr>
            <?php else: ?>
                <?php foreach ($displayDepartments as $dept): ?>
                    <?php $status = $dept['status'] ?? 'active'; ?>
                    <tr>
                        <td><?= htmlspecialchars($dept['name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($dept['slug'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($dept['code'] ?? ''); ?></td>
                        <td>
                            <?php if ($status === 'active'): ?>
                                <span class="badge">Active</span>
                            <?php elseif ($status === 'suspended'): ?>
                                <span class="badge warn">Suspended</span>
                            <?php else: ?>
                                <span class="badge warn">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($dept['created_at'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($dept['updated_at'] ?? ''); ?></td>
                        <td>
                            <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments" class="form-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="form_action" value="update_status">
                                <input type="hidden" name="slug" value="<?= htmlspecialchars($dept['slug'] ?? ''); ?>">
                                <select name="status">
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value); ?>" <?= $value === $status ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="create-department" class="panel">
    <h3>Create Department</h3>
    <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="form_action" value="create_department">
        <div class="form-field">
            <label for="name">Department Name *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? ''); ?>" required>
        </div>
        <div class="form-field">
            <label for="code">Code</label>
            <input type="text" id="code" name="code" value="<?= htmlspecialchars($_POST['code'] ?? ''); ?>">
        </div>
        <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit">Create Department</button>
        </div>
    </form>
</div>
