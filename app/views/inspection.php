<?php
require_login();

$user = current_user();
$canManageInspection = user_has_permission('manage_inspection');
$canCreateDocuments = user_has_permission('create_documents');
$canViewAll = user_has_permission('view_all_records');
if (!$canManageInspection && !$canCreateDocuments && !user_has_permission('view_reports_basic')) {
    require_permission('manage_inspection');
}
$templates = load_inspection_templates();
$reports = load_inspection_reports();
$mode = $_GET['mode'] ?? 'list';
$mode = in_array($mode, ['list', 'create', 'view'], true) ? $mode : 'list';
$errors = [];
$notice = '';
$departments = load_departments();

$csrfToken = $_SESSION['inspection_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['inspection_csrf_token'] = $csrfToken;

if ($mode === 'create' && !$canManageInspection && !$canCreateDocuments) {
    require_permission('manage_inspection');
}

function sanitize_text($value): string
{
    return trim((string) $value);
}

if ($mode === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'submit_report') {
    if (!$canManageInspection && !$canCreateDocuments) {
        require_permission('manage_inspection');
    }
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['inspection_csrf_token'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please try again.';
    }

    $templateId = sanitize_text($_POST['template_id'] ?? '');
    $template = $templateId ? find_inspection_template_by_id($templates, $templateId) : null;
    if (!$template || !($template['active'] ?? false)) {
        $errors[] = 'Selected inspection template is not available.';
    }

    $fieldValues = [];
    if ($template) {
        foreach ($template['fields'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            $fieldValue = sanitize_text($_POST['field'][$name] ?? '');
            if (($field['required'] ?? false) && $fieldValue === '') {
                $errors[] = ($field['label'] ?? $name) . ' is required.';
            }
            $fieldValues[$name] = $fieldValue;
        }
    }

    $checklistStatuses = [];
    if ($template) {
        $validStatuses = valid_inspection_statuses();
        foreach ($template['checklist'] ?? [] as $item) {
            $code = $item['code'] ?? '';
            $status = sanitize_text($_POST['check_status'][$code] ?? ($item['default_status'] ?? 'NA'));
            $remarks = sanitize_text($_POST['check_remarks'][$code] ?? '');
            if (!in_array($status, $validStatuses, true)) {
                $status = 'NA';
            }
            $checklistStatuses[] = [
                'code' => $code,
                'status' => $status,
                'remarks' => $remarks,
            ];
        }
    }

    $photos = [];
    $photoLabels = $_POST['photo_label'] ?? [];
    $photoPaths = $_POST['photo_path'] ?? [];
    if (is_array($photoLabels) && is_array($photoPaths)) {
        foreach ($photoLabels as $index => $label) {
            $labelText = sanitize_text($label);
            $pathText = sanitize_text($photoPaths[$index] ?? '');
            if ($labelText !== '' || $pathText !== '') {
                $photos[] = [
                    'label' => $labelText,
                    'path_or_ref' => $pathText,
                ];
            }
        }
    }

    if (empty($errors) && $template) {
        $now = gmdate('c');
        $reportId = generate_next_inspection_id($reports);
        $titleParts = [$template['name'] ?? 'Inspection Report'];
        if (!empty($fieldValues)) {
            $titleParts[] = reset($fieldValues);
        }
        $newReport = [
            'id' => $reportId,
            'template_id' => $template['id'] ?? '',
            'template_name' => $template['name'] ?? '',
            'title' => implode(' - ', array_filter($titleParts)),
            'fields' => $fieldValues,
            'checklist_statuses' => $checklistStatuses,
            'photos' => $photos,
            'status' => 'Open',
            'created_by' => $user['username'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'closed_at' => null,
        ];
        $reports[] = $newReport;
        save_inspection_reports($reports);
        log_event('inspection_created', $user['username'] ?? null, [
            'inspection_id' => $newReport['id'],
            'template_id' => $newReport['template_id'],
        ]);
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=inspection&mode=view&id=' . urlencode($newReport['id']));
        exit;
    }
}

if ($mode === 'view' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_report') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['inspection_csrf_token'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $id = sanitize_text($_POST['id'] ?? '');
        $report = $id ? find_inspection_report_by_id($reports, $id) : null;
        if (!$report) {
            $errors[] = 'Inspection report not found.';
        } elseif (!$canViewAll && ($report['created_by'] ?? '') !== ($user['username'] ?? '')) {
            $errors[] = 'You are not allowed to update this inspection report.';
        } elseif (($report['status'] ?? '') === 'Closed') {
            $notice = 'Report is already closed.';
        } else {
            $report['status'] = 'Closed';
            $report['updated_at'] = gmdate('c');
            $report['closed_at'] = gmdate('c');
            update_inspection_report($reports, $report);
            save_inspection_reports($reports);
            log_event('inspection_updated', $user['username'] ?? null, [
                'inspection_id' => $report['id'],
                'new_status' => 'Closed',
            ]);
            $notice = 'Inspection report marked as closed.';
        }
    }
}

if ($mode === 'view') {
    $id = $_GET['id'] ?? ($_POST['id'] ?? '');
    $report = $id ? find_inspection_report_by_id($reports, $id) : null;
    if (!$report) {
        $errors[] = 'Inspection report not found.';
    } elseif (!$canViewAll && ($report['created_by'] ?? '') !== ($user['username'] ?? '')) {
        $errors[] = 'You are not allowed to view this inspection report.';
        $report = null;
    }
    $template = $report ? find_inspection_template_by_id($templates, $report['template_id'] ?? '') : null;
}

if ($mode === 'list') {
    if ($canViewAll || $canManageInspection) {
        $visibleReports = $reports;
    } else {
        $visibleReports = array_filter($reports, function ($report) use ($user) {
            return ($report['created_by'] ?? null) === ($user['username'] ?? null);
        });
    }
}

$activeTemplates = array_filter($templates, function ($template) {
    return !empty($template['active']);
});
$selectedTemplateId = sanitize_text($_POST['template_id'] ?? ($_GET['template_id'] ?? ''));
$selectedTemplate = $selectedTemplateId ? find_inspection_template_by_id($templates, $selectedTemplateId) : null;
if ($selectedTemplate && !($selectedTemplate['active'] ?? false)) {
    $selectedTemplate = null;
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

<?php if ($mode === 'list'): ?>
    <div class="actions" style="margin-bottom: 1rem; display:flex; justify-content: space-between; align-items: center; gap: 1rem;">
        <div><strong>Your inspection reports</strong> <?= ($user['role'] ?? '') === 'admin' ? '(all reports shown for admin)' : ''; ?></div>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=create">Create New Inspection Report</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Template</th>
                    <th>Date of Inspection</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visibleReports)): ?>
                    <tr><td colspan="7">No inspection reports found.</td></tr>
                <?php else: ?>
                    <?php foreach ($visibleReports as $reportRow): ?>
                        <tr>
                            <td><?= htmlspecialchars($reportRow['id']); ?></td>
                            <td><?= htmlspecialchars($reportRow['title'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($reportRow['template_name'] ?? ''); ?></td>
                            <?php $dateField = $reportRow['fields']['date_of_inspection'] ?? ''; ?>
                            <td><?= htmlspecialchars($dateField); ?></td>
                            <td><span class="badge <?= ($reportRow['status'] ?? '') === 'Closed' ? 'badge-soft' : 'badge-success'; ?>"><?= htmlspecialchars($reportRow['status'] ?? ''); ?></span></td>
                            <td><?= htmlspecialchars($reportRow['created_at'] ?? ''); ?></td>
                            <td><a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=view&id=<?= urlencode($reportRow['id']); ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($mode === 'create'): ?>
    <h3>Select Inspection Template</h3>
    <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php">
        <input type="hidden" name="page" value="inspection">
        <input type="hidden" name="mode" value="create">
        <div class="form-field">
            <label for="template_id">Choose template *</label>
            <select name="template_id" id="template_id" required>
                <option value="">-- Select template --</option>
                <?php foreach ($activeTemplates as $template): ?>
                    <option value="<?= htmlspecialchars($template['id']); ?>" <?= $selectedTemplateId === ($template['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($template['name'] ?? $template['id']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit">Load Template</button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection">Cancel</a>
        </div>
    </form>

    <?php if ($selectedTemplate): ?>
        <hr>
        <h3>Fill Inspection Details</h3>
        <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=create&template_id=<?= urlencode($selectedTemplate['id']); ?>" class="form-stacked">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="template_id" value="<?= htmlspecialchars($selectedTemplate['id']); ?>">
            <input type="hidden" name="step" value="submit_report">
            <div class="form-grid">
                <?php foreach ($selectedTemplate['fields'] ?? [] as $field): ?>
                    <div class="form-field">
                        <label><?= htmlspecialchars($field['label'] ?? $field['name']); ?><?= ($field['required'] ?? false) ? ' *' : ''; ?></label>
                        <?php $name = $field['name'] ?? ''; $type = $field['type'] ?? 'text'; $value = $_POST['field'][$name] ?? ''; ?>
                        <?php if ($type === 'textarea'): ?>
                            <textarea name="field[<?= htmlspecialchars($name); ?>]" <?= ($field['required'] ?? false) ? 'required' : ''; ?>><?= htmlspecialchars($value); ?></textarea>
                        <?php else: ?>
                            <input type="<?= htmlspecialchars($type); ?>" name="field[<?= htmlspecialchars($name); ?>]" value="<?= htmlspecialchars($value); ?>" <?= ($field['required'] ?? false) ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($selectedTemplate['checklist'])): ?>
                <h4>Checklist</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sl. No.</th>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $validStatuses = valid_inspection_statuses(); ?>
                            <?php foreach ($selectedTemplate['checklist'] as $index => $item): ?>
                                <?php $code = $item['code'] ?? ''; ?>
                                <tr>
                                    <td><?= (int) ($index + 1); ?></td>
                                    <td><?= htmlspecialchars($item['label'] ?? $code); ?></td>
                                    <td>
                                        <select name="check_status[<?= htmlspecialchars($code); ?>]">
                                            <?php foreach ($validStatuses as $statusOption): ?>
                                                <?php $sel = ($_POST['check_status'][$code] ?? ($item['default_status'] ?? 'NA')) === $statusOption ? 'selected' : ''; ?>
                                                <option value="<?= htmlspecialchars($statusOption); ?>" <?= $sel; ?>><?= htmlspecialchars($statusOption); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><textarea name="check_remarks[<?= htmlspecialchars($code); ?>]" placeholder="Remarks"><?= htmlspecialchars($_POST['check_remarks'][$code] ?? ''); ?></textarea></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h4>Photo References (optional)</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Path or Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td><input type="text" name="photo_label[]" value="<?= htmlspecialchars($_POST['photo_label'][$i] ?? ''); ?>" placeholder="Description"></td>
                                <td><input type="text" name="photo_path[]" value="<?= htmlspecialchars($_POST['photo_path'][$i] ?? ''); ?>" placeholder="Path or reference"></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button class="btn-primary" type="submit">Save Inspection Report</button>
                <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
<?php elseif ($mode === 'view' && !empty($report)): ?>
    <?php
    $reportContent = function () use ($report, $template) {
        ob_start();
        ?>
        <div class="report-view">
            <div style="display:flex; justify-content: space-between; align-items:center;">
                <div>
                    <h2>Inspection Report – <?= htmlspecialchars($report['template_name'] ?? ''); ?></h2>
                    <div class="muted">Report ID: <?= htmlspecialchars($report['id'] ?? ''); ?></div>
                    <div class="muted">Status: <?= htmlspecialchars($report['status'] ?? ''); ?></div>
                </div>
                <div class="muted">Created at: <?= htmlspecialchars($report['created_at'] ?? ''); ?></div>
            </div>
            <h3>Basic Details</h3>
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <?php foreach (($template['fields'] ?? []) as $field): ?>
                            <?php $name = $field['name'] ?? ''; ?>
                            <tr>
                                <th><?= htmlspecialchars($field['label'] ?? $name); ?></th>
                                <td><?= nl2br(htmlspecialchars($report['fields'][$name] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($report['checklist_statuses'])): ?>
                <h3>Checklist</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sl. No.</th>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['checklist_statuses'] as $index => $item): ?>
                                <?php
                                $label = $item['code'] ?? '';
                                if ($template) {
                                    foreach ($template['checklist'] ?? [] as $chk) {
                                        if (($chk['code'] ?? '') === ($item['code'] ?? '')) {
                                            $label = $chk['label'] ?? $label;
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= (int) ($index + 1); ?></td>
                                    <td><?= htmlspecialchars($label); ?></td>
                                    <td><?= htmlspecialchars($item['status'] ?? ''); ?></td>
                                    <td><?= nl2br(htmlspecialchars($item['remarks'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($report['photos'])): ?>
                <h3>Photo References</h3>
                <ul>
                    <?php foreach ($report['photos'] as $photo): ?>
                        <li><strong><?= htmlspecialchars($photo['label'] ?? ''); ?>:</strong> <?= htmlspecialchars($photo['path_or_ref'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($template['footer_note'])): ?>
                <div class="muted" style="margin-top:1rem;"><?= htmlspecialchars($template['footer_note']); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    };

    $reportDepartment = get_user_department($user, $departments);
    $wrappedReport = $reportContent();
    if ($reportDepartment) {
        $wrappedReport = render_with_letterhead($wrappedReport, $reportDepartment, true);
    }

    if (isset($_GET['download']) && $_GET['download'] == '1') {
        $html = $wrappedReport;
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . ($report['id'] ?? 'inspection') . '.html"');
        echo $html;
        exit;
    }
    ?>
    <div style="margin-bottom:1rem; display:flex; gap: 0.5rem;">
        <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection">Back to list</a>
        <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=view&id=<?= urlencode($report['id']); ?>&download=1">Download HTML</a>
        <button class="btn-primary" onclick="window.print(); return false;">Print</button>
        <?php if (($report['status'] ?? '') !== 'Closed' && (($report['created_by'] ?? '') === ($user['username'] ?? '') || $canViewAll || $canManageInspection)): ?>
            <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection&mode=view&id=<?= urlencode($report['id']); ?>" style="display:inline-block;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="close_report">
                <input type="hidden" name="id" value="<?= htmlspecialchars($report['id']); ?>">
                <button type="submit" class="button">Mark as Closed</button>
            </form>
        <?php endif; ?>
    </div>
    <?= $wrappedReport; ?>
<?php endif; ?>
