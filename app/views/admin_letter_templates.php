<?php
require_permission('manage_templates');
$templates = load_letter_templates();
$action = $_GET['action'] ?? ($_POST['mode'] ?? 'list');
$editingId = $_GET['id'] ?? ($_POST['id'] ?? '');
$errors = [];
$successMessage = '';
$csrfToken = $_SESSION['template_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['template_csrf_token'] = $csrfToken;
$submittedTemplate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['template_csrf_token'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $mode = $_POST['mode'] ?? 'create';
    $templateId = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $active = !empty($_POST['active']);
    $body = trim($_POST['body'] ?? '');

    if ($templateId === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $templateId)) {
        $errors[] = 'Template ID is required (letters, numbers, underscores, hyphens).';
    }
    if ($name === '') {
        $errors[] = 'Template name is required.';
    }
    if ($body === '') {
        $errors[] = 'Template body is required.';
    }

    $variables = [];
    $varNames = $_POST['var_name'] ?? [];
    $varLabels = $_POST['var_label'] ?? [];
    $varTypes = $_POST['var_type'] ?? [];
    $varRequired = $_POST['var_required'] ?? [];

    if (is_array($varNames)) {
        foreach ($varNames as $idx => $varName) {
            $varName = trim($varName);
            if ($varName === '') {
                continue;
            }
            $variables[] = [
                'name' => $varName,
                'label' => trim($varLabels[$idx] ?? $varName),
                'type' => in_array($varTypes[$idx] ?? 'text', ['text', 'textarea', 'date', 'number']) ? ($varTypes[$idx] ?? 'text') : 'text',
                'required' => isset($varRequired[$idx]) && $varRequired[$idx] === '1',
            ];
        }
    }

    if (empty($variables)) {
        $errors[] = 'At least one variable is required.';
    }

    if ($mode === 'create' && find_letter_template_by_id($templates, $templateId)) {
        $errors[] = 'Template ID already exists. Choose a unique ID.';
    }

    if (empty($errors)) {
        $templateData = [
            'id' => $templateId,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'active' => $active,
            'variables' => $variables,
            'body' => $body,
        ];

        if ($mode === 'edit') {
            foreach ($templates as $index => $tpl) {
                if (($tpl['id'] ?? '') === $templateId) {
                    $templates[$index] = $templateData;
                    break;
                }
            }
        } else {
            $templates[] = $templateData;
        }

        if (save_letter_templates($templates)) {
            $successMessage = 'Template saved successfully.';
            $action = 'list';
            $templates = load_letter_templates();
        } else {
            $errors[] = 'Failed to save template. Please try again.';
        }
    }

    $submittedTemplate = [
        'id' => $templateId,
        'name' => $name,
        'description' => $description,
        'category' => $category,
        'active' => $active,
        'variables' => $variables,
        'body' => $body,
    ];
}

$currentTemplate = null;
if ($action === 'edit' && $editingId) {
    $currentTemplate = find_letter_template_by_id($templates, $editingId);
    if (!$currentTemplate) {
        $errors[] = 'Template not found.';
        $action = 'list';
    }
}
?>

<?php if ($successMessage): ?>
    <div class="alert success"><?= htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if (!empty($errors) && $action !== 'list'): ?>
    <div class="alert error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="actions" style="margin-bottom:15px;">
        <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_letter_templates&amp;action=create">Add New Template</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Active</th>
                    <th>Variables</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr><td colspan="6">No templates found.</td></tr>
                <?php else: ?>
                    <?php foreach ($templates as $tpl): ?>
                        <tr>
                            <td><?= htmlspecialchars($tpl['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($tpl['name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($tpl['category'] ?? ''); ?></td>
                            <td><?= !empty($tpl['active']) ? 'Yes' : 'No'; ?></td>
                            <td><?= count($tpl['variables'] ?? []); ?></td>
                            <td><a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_letter_templates&amp;action=edit&amp;id=<?= urlencode($tpl['id'] ?? ''); ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <?php
        $isEdit = ($action === 'edit' && $currentTemplate);
        $template = $isEdit ? $currentTemplate : [
            'id' => '',
            'name' => '',
            'description' => '',
            'category' => '',
            'active' => true,
            'variables' => [
                ['name' => 'recipient_name', 'label' => 'Recipient Name', 'type' => 'text', 'required' => true],
            ],
            'body' => '',
        ];
        if ($submittedTemplate && $action !== 'list') {
            $template = $submittedTemplate;
        }
    ?>
    <div class="card">
        <h3><?= $isEdit ? 'Edit Template' : 'Create Template'; ?></h3>
        <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_letter_templates">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="mode" value="<?= $isEdit ? 'edit' : 'create'; ?>">
            <div class="form-field">
                <label for="id">Template ID *</label>
                <input type="text" id="id" name="id" value="<?= htmlspecialchars($template['id']); ?>" <?= $isEdit ? 'readonly' : 'required'; ?> pattern="[A-Za-z0-9_\-]+">
                <?php if ($isEdit): ?>
                    <small>Template ID cannot be changed.</small>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="name">Template Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($template['name']); ?>" required>
            </div>
            <div class="form-field">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($template['category']); ?>">
            </div>
            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($template['description']); ?></textarea>
            </div>
            <div class="form-field">
                <label><input type="checkbox" name="active" value="1" <?= !empty($template['active']) ? 'checked' : ''; ?>> Active</label>
            </div>
            <div class="form-field">
                <label>Variables *</label>
                <div class="variables-group">
                    <?php foreach ($template['variables'] as $idx => $variable): ?>
                        <div class="variable-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px;">
                            <input type="text" name="var_name[]" placeholder="Name" value="<?= htmlspecialchars($variable['name'] ?? ''); ?>" required>
                            <input type="text" name="var_label[]" placeholder="Label" value="<?= htmlspecialchars($variable['label'] ?? ''); ?>" required>
                            <select name="var_type[]">
                                <?php
                                    $types = ['text' => 'Text', 'textarea' => 'Textarea', 'date' => 'Date', 'number' => 'Number'];
                                    $selectedType = $variable['type'] ?? 'text';
                                    foreach ($types as $key => $label):
                                ?>
                                    <option value="<?= $key; ?>" <?= $selectedType === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="var_required[]" value="1" <?= !empty($variable['required']) ? 'checked' : ''; ?>> Required</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><small>Add more rows as needed by duplicating existing ones before submitting.</small></p>
            </div>
            <div class="form-field">
                <label for="body">Template Body *</label>
                <textarea id="body" name="body" rows="8" required><?= htmlspecialchars($template['body']); ?></textarea>
                <small>Use placeholders like {{variable_name}} that match the IDs defined above.</small>
            </div>
            <div class="actions" style="margin-top:10px; display:flex; gap:10px;">
                <button type="submit">Save Template</button>
                <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_letter_templates">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>
