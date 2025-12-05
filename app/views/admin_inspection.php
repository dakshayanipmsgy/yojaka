<?php
require_permission('manage_inspection');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';

$currentUser = get_current_user();
$user = $currentUser;
$templates = load_inspection_templates();
$reports = load_inspection_reports();
$sub = $_GET['sub'] ?? 'templates';
$sub = in_array($sub, ['templates', 'template_create', 'template_edit', 'reports'], true) ? $sub : 'templates';
$errors = [];
$notice = '';

$csrfToken = $_SESSION['inspection_admin_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['inspection_admin_csrf'] = $csrfToken;

function inspection_admin_sanitize($value): string
{
    return trim((string) $value);
}

if (in_array($sub, ['template_create', 'template_edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['inspection_admin_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please try again.';
    }

    $templateId = inspection_admin_sanitize($_POST['id'] ?? '');
    $name = inspection_admin_sanitize($_POST['name'] ?? '');
    $description = inspection_admin_sanitize($_POST['description'] ?? '');
    $category = inspection_admin_sanitize($_POST['category'] ?? '');
    $active = isset($_POST['active']);

    if ($templateId === '') {
        $errors[] = 'Template ID is required.';
    }
    if ($name === '') {
        $errors[] = 'Template name is required.';
    }

    if ($sub === 'template_create') {
        foreach ($templates as $template) {
            if (($template['id'] ?? '') === $templateId) {
                $errors[] = 'Template ID already exists.';
                break;
            }
        }
    }

    $fields = [];
    $fieldNames = $_POST['field_name'] ?? [];
    $fieldLabels = $_POST['field_label'] ?? [];
    $fieldTypes = $_POST['field_type'] ?? [];
    $fieldRequired = $_POST['field_required'] ?? [];
    if (is_array($fieldNames)) {
        foreach ($fieldNames as $index => $fname) {
            $fname = inspection_admin_sanitize($fname);
            $flabel = inspection_admin_sanitize($fieldLabels[$index] ?? '');
            $ftype = inspection_admin_sanitize($fieldTypes[$index] ?? 'text');
            $freq = isset($fieldRequired[$index]);
            if ($fname !== '' && $flabel !== '') {
                if (!in_array($ftype, ['text', 'textarea', 'date', 'number'], true)) {
                    $ftype = 'text';
                }
                $fields[] = [
                    'name' => $fname,
                    'label' => $flabel,
                    'type' => $ftype,
                    'required' => $freq,
                ];
            }
        }
    }

    $checklist = [];
    $checkCodes = $_POST['check_code'] ?? [];
    $checkLabels = $_POST['check_label'] ?? [];
    $checkDefaults = $_POST['check_default_status'] ?? [];
    if (is_array($checkCodes)) {
        foreach ($checkCodes as $index => $code) {
            $codeSanitized = inspection_admin_sanitize($code);
            $labelSanitized = inspection_admin_sanitize($checkLabels[$index] ?? '');
            $defaultStatus = inspection_admin_sanitize($checkDefaults[$index] ?? 'NA');
            if ($codeSanitized !== '' && $labelSanitized !== '') {
                if (!in_array($defaultStatus, valid_inspection_statuses(), true)) {
                    $defaultStatus = 'NA';
                }
                $checklist[] = [
                    'code' => $codeSanitized,
                    'label' => $labelSanitized,
                    'default_status' => $defaultStatus,
                ];
            }
        }
    }

    if (empty($errors)) {
        $newTemplate = [
            'id' => $templateId,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'active' => $active,
            'fields' => $fields,
            'checklist' => $checklist,
            'footer_note' => inspection_admin_sanitize($_POST['footer_note'] ?? ''),
        ];

        $existing = find_inspection_template_by_id($templates, $templateId);
        if ($existing) {
            foreach ($templates as $index => $template) {
                if (($template['id'] ?? '') === $templateId) {
                    $templates[$index] = $newTemplate;
                    break;
                }
            }
        } else {
            $templates[] = $newTemplate;
        }

        save_inspection_templates($templates);
        $notice = 'Template saved successfully.';
        $sub = 'templates';
    }
}

if ($sub === 'reports' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['inspection_admin_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $id = inspection_admin_sanitize($_POST['id'] ?? '');
        $newStatus = inspection_admin_sanitize($_POST['new_status'] ?? '');
        if (!in_array($newStatus, ['Open', 'Closed'], true)) {
            $errors[] = 'Invalid status provided.';
        } else {
            $report = $id ? find_inspection_report_by_id($reports, $id) : null;
            if ($report) {
                $report = acl_normalize($report);
            }
            if (!$report) {
                $errors[] = 'Report not found.';
            } elseif (!acl_can_edit($currentUser, $report)) {
                $errors[] = 'You are not allowed to update this inspection report.';
            } else {
                $report['status'] = $newStatus;
                $report['updated_at'] = gmdate('c');
                $report['closed_at'] = $newStatus === 'Closed' ? gmdate('c') : null;
                update_inspection_report($reports, $report);
                save_inspection_reports($reports);
                log_event('inspection_updated', $user['username'] ?? null, [
                    'inspection_id' => $report['id'],
                    'new_status' => $newStatus,
                ]);
                $notice = 'Report status updated.';
            }
        }
    }
}

// Reload data after modifications
$templates = load_inspection_templates();
$reports = load_inspection_reports();

$currentTemplate = null;
if ($sub === 'template_edit') {
    $editId = $_GET['id'] ?? '';
    $currentTemplate = $editId ? find_inspection_template_by_id($templates, $editId) : null;
    if (!$currentTemplate) {
        $errors[] = 'Template not found.';
        $sub = 'templates';
    }
}

$visibleReports = [];
foreach ($reports as $reportRecord) {
    $reportRecord = acl_normalize($reportRecord);
    if (acl_can_view($currentUser, $reportRecord)) {
        $visibleReports[] = $reportRecord;
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

<div class="tabs" style="margin-bottom: 1rem;">
    <a class="tab<?= $sub === 'templates' ? ' active' : ''; ?>" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection&sub=templates">Templates</a>
    <a class="tab<?= $sub === 'reports' ? ' active' : ''; ?>" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection&sub=reports">Reports</a>
</div>

<?php if ($sub === 'templates'): ?>
    <div class="actions" style="margin-bottom: 1rem; display:flex; justify-content: space-between; align-items: center; gap: 1rem;">
        <div><strong>Inspection Templates</strong></div>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection&sub=template_create">Create New Template</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Active</th>
                    <th># Fields</th>
                    <th># Checklist Items</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr><td colspan="7">No templates available.</td></tr>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><?= htmlspecialchars($template['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($template['name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($template['category'] ?? ''); ?></td>
                            <td><?= !empty($template['active']) ? 'Yes' : 'No'; ?></td>
                            <td><?= count($template['fields'] ?? []); ?></td>
                            <td><?= count($template['checklist'] ?? []); ?></td>
                            <td><a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection&sub=template_edit&id=<?= urlencode($template['id'] ?? ''); ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif (in_array($sub, ['template_create', 'template_edit'], true)): ?>
    <?php $isEdit = $sub === 'template_edit'; ?>
    <h3><?= $isEdit ? 'Edit Template' : 'Create Template'; ?></h3>
    <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection&sub=<?= $isEdit ? 'template_edit&id=' . urlencode($currentTemplate['id'] ?? '') : 'template_create'; ?>" class="form-stacked">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <div class="form-grid">
            <div class="form-field">
                <label for="id">Template ID *</label>
                <input type="text" id="id" name="id" value="<?= htmlspecialchars($isEdit ? ($currentTemplate['id'] ?? '') : ($_POST['id'] ?? '')); ?>" <?= $isEdit ? 'readonly' : 'required'; ?>>
            </div>
            <div class="form-field">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? ($currentTemplate['name'] ?? '')); ?>" required>
            </div>
            <div class="form-field">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($_POST['category'] ?? ($currentTemplate['category'] ?? '')); ?>">
            </div>
            <div class="form-field">
                <label for="active">Active</label>
                <?php $activeChecked = isset($_POST['active']) ? 'checked' : (!isset($_POST['active']) && !empty($currentTemplate['active']) ? 'checked' : ''); ?>
                <input type="checkbox" id="active" name="active" value="1" <?= $activeChecked; ?>>
            </div>
        </div>
        <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= htmlspecialchars($_POST['description'] ?? ($currentTemplate['description'] ?? '')); ?></textarea>
        </div>

        <h4>Fields</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Label</th>
                        <th>Type</th>
                        <th>Required</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $fieldRows = $_POST['field_name'] ?? ($currentTemplate['fields'] ?? [ ['name' => '', 'label' => '', 'type' => 'text', 'required' => false] ]); ?>
                    <?php for ($i = 0; $i < max(3, count($fieldRows)); $i++): ?>
                        <?php
                        $row = $fieldRows[$i] ?? ['name' => '', 'label' => '', 'type' => 'text', 'required' => false];
                        if (is_string($row)) {
                            $row = [
                                'name' => $_POST['field_name'][$i] ?? '',
                                'label' => $_POST['field_label'][$i] ?? '',
                                'type' => $_POST['field_type'][$i] ?? 'text',
                                'required' => isset($_POST['field_required'][$i]),
                            ];
                        }
                        ?>
                        <tr>
                            <td><input type="text" name="field_name[]" value="<?= htmlspecialchars($row['name'] ?? ''); ?>" placeholder="field_key"></td>
                            <td><input type="text" name="field_label[]" value="<?= htmlspecialchars($row['label'] ?? ''); ?>" placeholder="Field label"></td>
                            <td>
                                <?php $selectedType = $row['type'] ?? 'text'; ?>
                                <select name="field_type[]">
                                    <?php foreach (['text', 'textarea', 'date', 'number'] as $type): ?>
                                        <option value="<?= $type; ?>" <?= $selectedType === $type ? 'selected' : ''; ?>><?= ucfirst($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="checkbox" name="field_required[]" value="1" <?= !empty($row['required']) ? 'checked' : ''; ?>></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <h4>Checklist Items</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Label</th>
                        <th>Default Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $checkRows = $_POST['check_code'] ?? ($currentTemplate['checklist'] ?? [['code' => '', 'label' => '', 'default_status' => 'NA']]); ?>
                    <?php for ($i = 0; $i < max(3, count($checkRows)); $i++): ?>
                        <?php
                        $row = $checkRows[$i] ?? ['code' => '', 'label' => '', 'default_status' => 'NA'];
                        if (is_string($row)) {
                            $row = [
                                'code' => $_POST['check_code'][$i] ?? '',
                                'label' => $_POST['check_label'][$i] ?? '',
                                'default_status' => $_POST['check_default_status'][$i] ?? 'NA',
                            ];
                        }
                        ?>
                        <tr>
                            <td><input type="text" name="check_code[]" value="<?= htmlspecialchars($row['code'] ?? ''); ?>" placeholder="item_code"></td>
                            <td><input type="text" name="check_label[]" value="<?= htmlspecialchars($row['label'] ?? ''); ?>" placeholder="Item label"></td>
                            <td>
                                <?php $defaultStatus = $row['default_status'] ?? 'NA'; ?>
                                <select name="check_default_status[]">
                                    <?php foreach (valid_inspection_statuses() as $status): ?>
                                        <option value="<?= htmlspecialchars($status); ?>" <?= $defaultStatus === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="form-field">
            <label for="footer_note">Footer Note</label>
            <textarea id="footer_note" name="footer_note" placeholder="Shown at the end of reports."><?= htmlspecialchars($_POST['footer_note'] ?? ($currentTemplate['footer_note'] ?? '')); ?></textarea>
        </div>

        <div class="form-actions">
            <button class="btn-primary" type="submit">Save Template</button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection">Cancel</a>
        </div>
    </form>
<?php elseif ($sub === 'reports'): ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Template</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visibleReports)): ?>
                    <tr><td colspan="6">No inspection reports yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($visibleReports as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($report['template_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($report['created_by'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($report['status'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($report['created_at'] ?? ''); ?></td>
                            <td>
                                <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=view&id=<?= urlencode($report['id'] ?? ''); ?>">View</a>
                                <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection&sub=reports" style="display:inline-block; margin-left:0.25rem;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($report['id'] ?? ''); ?>">
                                    <select name="new_status">
                                        <option value="Open" <?= ($report['status'] ?? '') === 'Open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="Closed" <?= ($report['status'] ?? '') === 'Closed' ? 'selected' : ''; ?>>Closed</option>
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
<?php endif; ?>
