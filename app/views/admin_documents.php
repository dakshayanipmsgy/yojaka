<?php
require_permission('manage_templates');

$allowedCategories = [
    'meeting_minutes' => 'Meeting Minutes',
    'work_order' => 'Work Orders',
    'guc' => 'Grant Utilization Certificates',
    'letter' => 'Letters',
];

$category = $_GET['category'] ?? 'meeting_minutes';
if (!array_key_exists($category, $allowedCategories)) {
    $category = 'meeting_minutes';
}

$templates = load_document_templates();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$editingId = $_GET['id'] ?? ($_POST['id'] ?? '');
$errors = [];
$successMessage = '';
$csrfToken = $_SESSION['document_template_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['document_template_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['document_template_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $mode = $_POST['mode'] ?? 'create';
    $templateId = trim($_POST['id'] ?? '');
    $templateCategory = $_POST['category'] ?? $category;
    if (!array_key_exists($templateCategory, $allowedCategories)) {
        $templateCategory = 'meeting_minutes';
    }
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $active = !empty($_POST['active']);
    $body = trim($_POST['body'] ?? '');
    $footerNote = trim($_POST['footer_note'] ?? '');

    if ($templateId === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $templateId)) {
        $errors[] = 'Template ID is required (letters, numbers, underscores, hyphens).';
    }
    if ($name === '') {
        $errors[] = 'Template name is required.';
    }
    if ($body === '') {
        $errors[] = 'Template body is required.';
    }

    $fields = [];
    $fieldNames = $_POST['field_name'] ?? [];
    $fieldLabels = $_POST['field_label'] ?? [];
    $fieldTypes = $_POST['field_type'] ?? [];
    $fieldRequired = $_POST['field_required'] ?? [];

    if (is_array($fieldNames)) {
        foreach ($fieldNames as $idx => $fname) {
            $fname = trim($fname);
            if ($fname === '') {
                continue;
            }
            $fields[] = [
                'name' => $fname,
                'label' => trim($fieldLabels[$idx] ?? $fname),
                'type' => in_array($fieldTypes[$idx] ?? 'text', ['text', 'textarea', 'date', 'number']) ? ($fieldTypes[$idx] ?? 'text') : 'text',
                'required' => isset($fieldRequired[$idx]) && $fieldRequired[$idx] === '1',
            ];
        }
    }

    $extraSections = [];
    $extraNames = $_POST['extra_name'] ?? [];
    $extraLabels = $_POST['extra_label'] ?? [];
    $extraTypes = $_POST['extra_type'] ?? [];
    $extraRequired = $_POST['extra_required'] ?? [];

    if (is_array($extraNames)) {
        foreach ($extraNames as $idx => $ename) {
            $ename = trim($ename);
            if ($ename === '') {
                continue;
            }
            $extraSections[] = [
                'name' => $ename,
                'label' => trim($extraLabels[$idx] ?? $ename),
                'type' => in_array($extraTypes[$idx] ?? 'text', ['text', 'textarea', 'date', 'number']) ? ($extraTypes[$idx] ?? 'text') : 'text',
                'required' => isset($extraRequired[$idx]) && $extraRequired[$idx] === '1',
            ];
        }
    }

    if (empty($fields)) {
        $errors[] = 'At least one field is required.';
    }

    if ($mode === 'create' && find_document_template_by_id($templates, $templateId)) {
        $errors[] = 'Template ID already exists. Choose a unique ID.';
    }

    if ($mode === 'edit' && !find_document_template_by_id($templates, $templateId)) {
        $errors[] = 'Template not found for editing.';
    }

    if (empty($errors)) {
        $templateData = [
            'id' => $templateId,
            'category' => $templateCategory,
            'name' => $name,
            'description' => $description,
            'active' => $active,
            'fields' => $fields,
            'extra_sections' => $extraSections,
            'body' => $body,
            'footer_note' => $footerNote,
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

        if (save_document_templates($templates)) {
            $successMessage = 'Template saved successfully.';
            $action = 'list';
            $templates = load_document_templates();
        } else {
            $errors[] = 'Failed to save template. Please try again.';
        }
    }

    $editingId = $templateId;
    $category = $templateCategory;
}

$filteredTemplates = array_values(array_filter($templates, function ($tpl) use ($category) {
    return ($tpl['category'] ?? '') === $category;
}));

$currentTemplate = null;
if ($action === 'edit' && $editingId) {
    $currentTemplate = find_document_template_by_id($templates, $editingId);
    if (!$currentTemplate) {
        $errors[] = 'Template not found.';
        $action = 'list';
    }
}
?>

<div class="tabs" style="margin-bottom:10px; display:flex; gap:8px;">
    <?php foreach ($allowedCategories as $key => $label): ?>
        <a class="button <?= $key === $category ? 'active' : ''; ?>" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_documents&amp;category=<?= urlencode($key); ?>"><?= htmlspecialchars($label); ?></a>
    <?php endforeach; ?>
</div>

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
        <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_documents&amp;category=<?= urlencode($category); ?>&amp;action=create">Add New Template</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Active</th>
                    <th>Fields</th>
                    <th>Extra Sections</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filteredTemplates)): ?>
                    <tr><td colspan="7">No templates found for this category.</td></tr>
                <?php else: ?>
                    <?php foreach ($filteredTemplates as $tpl): ?>
                        <tr>
                            <td><?= htmlspecialchars($tpl['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($tpl['name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($tpl['description'] ?? ''); ?></td>
                            <td><?= !empty($tpl['active']) ? 'Yes' : 'No'; ?></td>
                            <td><?= count($tpl['fields'] ?? []); ?></td>
                            <td><?= count($tpl['extra_sections'] ?? []); ?></td>
                            <td><a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_documents&amp;category=<?= urlencode($category); ?>&amp;action=edit&amp;id=<?= urlencode($tpl['id'] ?? ''); ?>">Edit</a></td>
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
            'category' => $category,
            'name' => '',
            'description' => '',
            'active' => true,
            'fields' => [
                ['name' => 'field_one', 'label' => 'Field One', 'type' => 'text', 'required' => true],
            ],
            'extra_sections' => [],
            'body' => '',
            'footer_note' => '',
        ];
        if (!empty($errors)) {
            $template = [
                'id' => $templateId ?? $template['id'],
                'category' => $templateCategory ?? $template['category'],
                'name' => $name ?? $template['name'],
                'description' => $description ?? $template['description'],
                'active' => $active ?? $template['active'],
                'fields' => $fields ?? $template['fields'],
                'extra_sections' => $extraSections ?? $template['extra_sections'],
                'body' => $body ?? $template['body'],
                'footer_note' => $footerNote ?? $template['footer_note'],
            ];
        }
    ?>
    <div class="card">
        <h3><?= $isEdit ? 'Edit Template' : 'Create Template'; ?> (<?= htmlspecialchars($allowedCategories[$template['category']] ?? ''); ?>)</h3>
        <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_documents&amp;category=<?= urlencode($template['category']); ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="mode" value="<?= $isEdit ? 'edit' : 'create'; ?>">
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'create'; ?>">
            <div class="form-field">
                <label for="id">Template ID *</label>
                <input type="text" id="id" name="id" value="<?= htmlspecialchars($template['id']); ?>" <?= $isEdit ? 'readonly' : 'required'; ?> pattern="[A-Za-z0-9_\-]+">
                <?php if ($isEdit): ?>
                    <small>Template ID cannot be changed.</small>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="category">Category</label>
                <select id="category" name="category" <?= $isEdit ? 'readonly disabled' : ''; ?>>
                    <?php foreach ($allowedCategories as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key); ?>" <?= $template['category'] === $key ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($template['category']); ?>">
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="name">Template Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($template['name']); ?>" required>
            </div>
            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($template['description']); ?></textarea>
            </div>
            <div class="form-field">
                <label><input type="checkbox" name="active" value="1" <?= !empty($template['active']) ? 'checked' : ''; ?>> Active</label>
            </div>
            <div class="form-field">
                <label>Fields *</label>
                <div class="variables-group">
                    <?php foreach ($template['fields'] as $field): ?>
                        <div class="variable-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px;">
                            <input type="text" name="field_name[]" placeholder="Name" value="<?= htmlspecialchars($field['name'] ?? ''); ?>" required>
                            <input type="text" name="field_label[]" placeholder="Label" value="<?= htmlspecialchars($field['label'] ?? ''); ?>" required>
                            <select name="field_type[]">
                                <?php $types = ['text' => 'Text', 'textarea' => 'Textarea', 'date' => 'Date', 'number' => 'Number']; $selectedType = $field['type'] ?? 'text'; ?>
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= $key; ?>" <?= $selectedType === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="field_required[]" value="1" <?= !empty($field['required']) ? 'checked' : ''; ?>> Required</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><small>Add more rows as needed by duplicating existing ones before submitting.</small></p>
            </div>
            <div class="form-field">
                <label>Extra Sections</label>
                <div class="variables-group">
                    <?php if (!empty($template['extra_sections'])): ?>
                        <?php foreach ($template['extra_sections'] as $extra): ?>
                            <div class="variable-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px;">
                                <input type="text" name="extra_name[]" placeholder="Name" value="<?= htmlspecialchars($extra['name'] ?? ''); ?>">
                                <input type="text" name="extra_label[]" placeholder="Label" value="<?= htmlspecialchars($extra['label'] ?? ''); ?>">
                                <select name="extra_type[]">
                                    <?php $types = ['text' => 'Text', 'textarea' => 'Textarea', 'date' => 'Date', 'number' => 'Number']; $selectedType = $extra['type'] ?? 'text'; ?>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key; ?>" <?= $selectedType === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="extra_required[]" value="1" <?= !empty($extra['required']) ? 'checked' : ''; ?>> Required</label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="variable-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px;">
                            <input type="text" name="extra_name[]" placeholder="Name">
                            <input type="text" name="extra_label[]" placeholder="Label">
                            <select name="extra_type[]">
                                <?php $types = ['text' => 'Text', 'textarea' => 'Textarea', 'date' => 'Date', 'number' => 'Number']; ?>
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= $key; ?>"><?= $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="extra_required[]" value="1"> Required</label>
                        </div>
                    <?php endif; ?>
                </div>
                <p><small>Leave extra section names blank to ignore them.</small></p>
            </div>
            <div class="form-field">
                <label for="body">Template Body *</label>
                <textarea id="body" name="body" rows="8" required><?= htmlspecialchars($template['body']); ?></textarea>
                <small>Use placeholders like {{field_name}} or {{extra_name}} corresponding to the IDs defined above.</small>
            </div>
            <div class="form-field">
                <label for="footer_note">Footer Note</label>
                <textarea id="footer_note" name="footer_note" rows="3"><?= htmlspecialchars($template['footer_note']); ?></textarea>
            </div>
            <div class="actions" style="margin-top:10px; display:flex; gap:10px;">
                <button type="submit">Save Template</button>
                <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_documents&amp;category=<?= urlencode($category); ?>">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>
