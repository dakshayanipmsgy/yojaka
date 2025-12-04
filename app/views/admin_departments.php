<?php
require_permission('manage_departments');

$departments = load_departments();
$action = $_GET['action'] ?? 'list';
$action = in_array($action, ['list', 'create', 'edit'], true) ? $action : 'list';
$errors = [];
$notice = '';
$csrfToken = $_SESSION['dept_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['dept_csrf'] = $csrfToken;

function short_text($text, $length = 60)
{
    $clean = trim((string) $text);
    if (mb_strlen($clean) <= $length) {
        return $clean;
    }
    return mb_substr($clean, 0, $length) . '…';
}

if (($action === 'create' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['dept_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $logoPath = trim($_POST['logo_path'] ?? '');
    $headerHtml = $_POST['letterhead_header_html'] ?? '';
    $footerHtml = $_POST['letterhead_footer_html'] ?? '';
    $signatory = $_POST['default_signatory_block'] ?? '';
    $isDefault = !empty($_POST['is_default']);

    if ($id === '' || preg_match('/\s/', $id)) {
        $errors[] = 'ID is required and should not contain spaces.';
    }
    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if ($action === 'create' && find_department_by_id($departments, $id)) {
        $errors[] = 'Department ID must be unique.';
    }

    if (empty($errors)) {
        $departmentData = [
            'id' => $id,
            'name' => $name,
            'address' => $address,
            'contact' => $contact,
            'logo_path' => $logoPath,
            'letterhead_header_html' => $headerHtml,
            'letterhead_footer_html' => $footerHtml,
            'default_signatory_block' => $signatory,
            'is_default' => $isDefault,
        ];

        if ($action === 'create') {
            $departments[] = $departmentData;
        } else {
            foreach ($departments as &$dept) {
                if (($dept['id'] ?? '') === $id) {
                    $dept = array_merge($dept, $departmentData);
                    break;
                }
            }
            unset($dept);
        }

        if ($isDefault) {
            foreach ($departments as &$dept) {
                $dept['is_default'] = ($dept['id'] === $id);
            }
            unset($dept);
        }

        save_departments($departments);
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=admin_departments');
        exit;
    }
}

if ($action === 'list' && isset($_GET['set_default']) && isset($_GET['id'])) {
    $submittedToken = $_GET['token'] ?? '';
    if ($submittedToken && hash_equals($_SESSION['dept_csrf'], $submittedToken)) {
        $targetId = $_GET['id'];
        foreach ($departments as &$dept) {
            $dept['is_default'] = (($dept['id'] ?? '') === $targetId);
        }
        unset($dept);
        save_departments($departments);
        $notice = 'Default department updated.';
    } else {
        $errors[] = 'Invalid request token.';
    }
}

$editDepartment = null;
if ($action === 'edit') {
    $deptId = $_GET['id'] ?? '';
    $editDepartment = $deptId ? find_department_by_id($departments, $deptId) : null;
    if (!$editDepartment) {
        $errors[] = 'Department not found.';
    }
}
?>

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

<?php if ($action === 'list'): ?>
    <div class="actions" style="margin-bottom:1rem; display:flex; justify-content: space-between; align-items:center;">
        <div><strong>Department Profiles</strong></div>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=create">Create New Department</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Default</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr><td colspan="6">No departments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($dept['name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(short_text($dept['address'] ?? '')); ?></td>
                            <td><?= htmlspecialchars(short_text($dept['contact'] ?? '')); ?></td>
                            <td><?= !empty($dept['is_default']) ? '<span class="badge">Yes</span>' : 'No'; ?></td>
                            <td>
                                <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=edit&id=<?= urlencode($dept['id']); ?>">Edit</a>
                                <?php if (empty($dept['is_default'])): ?>
                                    <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=list&set_default=1&id=<?= urlencode($dept['id']); ?>&token=<?= htmlspecialchars($csrfToken); ?>">Set Default</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($action === 'create' || ($action === 'edit' && $editDepartment)): ?>
    <?php $formData = $editDepartment ?? ['id' => '', 'name' => '', 'address' => '', 'contact' => '', 'logo_path' => '', 'letterhead_header_html' => '', 'letterhead_footer_html' => '', 'default_signatory_block' => '', 'is_default' => false]; ?>
    <form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments&action=<?= htmlspecialchars($action); ?><?= $editDepartment ? '&id=' . urlencode($editDepartment['id']) : ''; ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <div class="form-field">
            <label for="id">Department ID *</label>
            <input type="text" id="id" name="id" value="<?= htmlspecialchars($formData['id']); ?>" <?= $action === 'edit' ? 'readonly' : 'required'; ?>>
        </div>
        <div class="form-field">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($formData['name']); ?>" required>
        </div>
        <div class="form-field">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3"><?= htmlspecialchars($formData['address']); ?></textarea>
        </div>
        <div class="form-field">
            <label for="contact">Contact</label>
            <textarea id="contact" name="contact" rows="3"><?= htmlspecialchars($formData['contact']); ?></textarea>
        </div>
        <div class="form-field">
            <label for="logo_path">Logo Path</label>
            <input type="text" id="logo_path" name="logo_path" value="<?= htmlspecialchars($formData['logo_path']); ?>" placeholder="assets/images/logo.png">
        </div>
        <div class="form-field">
            <label for="letterhead_header_html">Letterhead Header HTML</label>
            <textarea id="letterhead_header_html" name="letterhead_header_html" rows="4"><?= htmlspecialchars($formData['letterhead_header_html']); ?></textarea>
        </div>
        <div class="form-field">
            <label for="letterhead_footer_html">Letterhead Footer HTML</label>
            <textarea id="letterhead_footer_html" name="letterhead_footer_html" rows="4"><?= htmlspecialchars($formData['letterhead_footer_html']); ?></textarea>
        </div>
        <div class="form-field">
            <label for="default_signatory_block">Default Signatory Block</label>
            <textarea id="default_signatory_block" name="default_signatory_block" rows="3"><?= htmlspecialchars($formData['default_signatory_block']); ?></textarea>
        </div>
        <div class="form-field">
            <label><input type="checkbox" name="is_default" value="1" <?= !empty($formData['is_default']) ? 'checked' : ''; ?>> Set as default department</label>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit">Save Department</button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments">Cancel</a>
        </div>
    </form>
<?php endif; ?>
