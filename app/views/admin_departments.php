<?php
require_role('superadmin');
require_once __DIR__ . '/../departments.php';
require_once __DIR__ . '/../users.php';
require_once __DIR__ . '/../roles.php';

$departments = load_departments();
$action = $_GET['action'] ?? 'list';
$action = in_array($action, ['list', 'create', 'edit', 'toggle'], true) ? $action : 'list';
$errors = [];
$notice = '';
$generatedAdmin = null;
$csrfToken = $_SESSION['dept_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['dept_csrf'] = $csrfToken;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && in_array($action, ['create', 'edit'], true)) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['dept_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $name = trim($_POST['name'] ?? '');
    $active = !empty($_POST['active']);
    $description = trim($_POST['description'] ?? '');
    $slug = $action === 'edit' ? ($_POST['slug'] ?? '') : '';

    if ($name === '') {
        $errors[] = 'Department name is required.';
    }

    if ($action === 'create') {
        $slug = slugify_department_name($name, $departments);
    }

    if ($slug === '') {
        $errors[] = 'Unable to generate department slug.';
    }

    if (empty($errors)) {
        $departments[$slug] = [
            'id' => $slug,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'active' => $active,
        ];
        save_departments($departments);

        if ($action === 'create') {
            $adminUsername = 'admin.' . $slug;
            $password = substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(18))), 0, 12);
            $adminUser = [
                'username' => $adminUsername,
                'full_name' => $name . ' Admin',
                'role' => 'dept_admin',
                'active' => true,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'department_slug' => $slug,
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ];
            save_user($adminUser);
            $generatedAdmin = ['username' => $adminUsername, 'password' => $password];
            $notice = 'Department created. Initial admin user generated.';
        } else {
            $notice = 'Department updated.';
        }
    }
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $slug = $_POST['slug'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['dept_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    } elseif (!isset($departments[$slug])) {
        $errors[] = 'Department not found.';
    } else {
        $departments[$slug]['active'] = empty($departments[$slug]['active']);
        save_departments($departments);
        $notice = 'Department status updated.';
        $action = 'list';
    }
}

$editDepartment = null;
if ($action === 'edit') {
    $slug = $_GET['slug'] ?? '';
    if ($slug && isset($departments[$slug])) {
        $editDepartment = $departments[$slug];
    } else {
        $errors[] = 'Department not found.';
        $action = 'list';
    }
}
?>

<div class="flex" style="justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h2>Departments</h2>
    <?php if ($action === 'list'): ?>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=create">Create Department</a>
    <?php endif; ?>
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
        <strong>Initial Admin Credentials</strong><br>
        Username: <?= htmlspecialchars($generatedAdmin['username']); ?><br>
        Password: <?= htmlspecialchars($generatedAdmin['password']); ?><br>
        Please share these with the department admin securely.
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Slug</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr><td colspan="5">No departments defined.</td></tr>
                <?php else: ?>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['slug'] ?? $dept['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($dept['name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($dept['description'] ?? ''); ?></td>
                            <td><?= !empty($dept['active']) ? '<span class="badge">Active</span>' : '<span class="badge warn">Inactive</span>'; ?></td>
                            <td>
                                <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=edit&slug=<?= urlencode($dept['slug'] ?? ''); ?>">Edit</a>
                                <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=toggle" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($dept['slug'] ?? ''); ?>">
                                    <button type="submit" class="button"><?= !empty($dept['active']) ? 'Deactivate' : 'Activate'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($action === 'create' || $editDepartment): ?>
    <?php $formData = $editDepartment ?? ['name' => '', 'description' => '', 'slug' => '', 'active' => true]; ?>
    <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=<?= htmlspecialchars($action); ?><?= $editDepartment ? '&slug=' . urlencode($formData['slug']) : ''; ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <?php if ($editDepartment): ?>
            <input type="hidden" name="slug" value="<?= htmlspecialchars($formData['slug']); ?>">
        <?php endif; ?>
        <div class="form-field">
            <label for="name">Department Name *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($formData['name']); ?>" required>
        </div>
        <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?= htmlspecialchars($formData['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-field">
            <label><input type="checkbox" name="active" value="1" <?= !empty($formData['active']) ? 'checked' : ''; ?>> Active</label>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit">Save</button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments">Cancel</a>
        </div>
    </form>
<?php endif; ?>
