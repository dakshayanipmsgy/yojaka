<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../acl.php';
require_once __DIR__ . '/../work_orders.php';

require_login();
require_permission('create_documents');

$category = 'work_order';
$allTemplates = load_document_templates();
$templates = get_templates_by_category($allTemplates, $category);
$departments = load_departments();
$currentUser = yojaka_current_user();
$user = $currentUser;
$userDepartment = get_user_department($user, $departments);
$allRecords = load_work_orders();
$records = [];

foreach ($allRecords as $rec) {
    if (acl_can_view($currentUser, $rec)) {
        $records[] = $rec;
    }
}

$mode = $_GET['mode'] ?? 'list';
$action = $_GET['action'] ?? null;
$downloadRequested = ($action === 'download_html') || (isset($_GET['download']) && $_GET['download'] === '1');
$errors = [];
$csrfToken = $_SESSION['work_order_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['work_order_csrf'] = $csrfToken;
$attachmentErrors = [];
$attachmentNotice = '';
$attachmentToken = '';

if ($mode === 'view') {
    $id = $_GET['id'] ?? '';
    $record = null;
    foreach ($allRecords as $rec) {
        if (($rec['id'] ?? '') === $id) {
            $record = $rec;
            break;
        }
    }
    if (!$record) {
        echo '<p class="error">Record not found.</p>';
        return;
    }

    $record = work_order_normalize_record($record);

    if (!acl_can_view($currentUser, $record)) {
        http_response_code(403);
        echo '<p class="error">You do not have access to this record.</p>';
        return;
    }

    $canUploadAttachments = user_has_permission('create_documents') || user_has_permission('view_all_records');
    [$attachmentErrors, $attachmentNotice, $attachmentToken] = handle_attachment_upload('documents', $record['id'], 'work_order_attachment_csrf', $canUploadAttachments);
    if (!empty($attachmentNotice) && empty($attachmentErrors)) {
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=work_orders&mode=view&id=' . urlencode($record['id']));
        exit;
    }
    $recordAttachments = find_attachments_for_entity('documents', $record['id']);

    if ($downloadRequested) {
        $filename = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($record['id'] ?? 'work_order')) ?: 'work_order';
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        echo "<html><head><meta charset=\"UTF-8\"><title>Work Order</title><link rel=\"stylesheet\" href=\"" . YOJAKA_BASE_URL . "/assets/css/style.css\"></head><body>";
        echo $record['rendered_body'] ?? '';
        echo '</body></html>';
        exit;
    }
    ?>
    <div class="card">
        <h3>Work Order #<?= htmlspecialchars($record['id'] ?? ''); ?></h3>
        <div><strong>Template:</strong> <?= htmlspecialchars($record['template_name'] ?? ''); ?></div>
        <div><strong>Status:</strong> <?= htmlspecialchars($record['status'] ?? ''); ?></div>
        <div><strong>Created At:</strong> <?= htmlspecialchars($record['created_at'] ?? ''); ?></div>
        <div class="letter-preview" style="margin-top:10px; padding:10px; border:1px solid #ddd; background:#fafafa;">
            <?= $record['rendered_body'] ?? ''; ?>
        </div>
        <div class="actions" style="margin-top:10px; display:flex; gap:10px;">
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=print_document&amp;type=work_order&amp;id=<?= urlencode($record['id'] ?? ''); ?>" target="_blank">Print</a>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=work_orders&amp;mode=view&amp;id=<?= urlencode($record['id'] ?? ''); ?>&amp;download=1">Download HTML</a>
        </div>
    </div>
    <div class="card" style="margin-top:1rem;">
        <h3>Attachments</h3>
        <?php if (!empty($attachmentErrors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($attachmentErrors as $err): ?>
                        <li><?= htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($attachmentNotice !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($attachmentNotice); ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Description</th><th>File</th><th>Size</th><th>Uploaded By</th><th>Uploaded At</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($recordAttachments)): ?>
                        <tr><td colspan="6">No attachments yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recordAttachments as $att): ?>
                            <tr>
                                <td><?= htmlspecialchars($att['description'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($att['original_name'] ?? ''); ?></td>
                                <td><?= format_attachment_size((int) ($att['size_bytes'] ?? 0)); ?></td>
                                <td><?= htmlspecialchars($att['uploaded_by'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($att['uploaded_at'] ?? ''); ?></td>
                                <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/download_attachment.php?id=<?= urlencode($att['id'] ?? ''); ?>">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canUploadAttachments): ?>
            <form method="post" enctype="multipart/form-data" style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($attachmentToken); ?>">
                <input type="hidden" name="attachment_upload" value="1">
                <div class="form-field">
                    <label>Attachment File</label>
                    <input type="file" name="attachment_file" required>
                </div>
                <div class="form-field">
                    <label>Description</label>
                    <input type="text" name="attachment_description" placeholder="Short description">
                </div>
                <div class="form-field">
                    <label>Tags (comma separated)</label>
                    <input type="text" name="attachment_tags" placeholder="tag1, tag2">
                </div>
                <button type="submit" class="btn primary">Upload Attachment</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'create') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['work_order_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $selectedTemplateId = $_POST['template_id'] ?? '';
    $selectedTemplate = $selectedTemplateId ? find_document_template_by_id($templates, $selectedTemplateId) : null;
    if (!$selectedTemplate || empty($selectedTemplate['active'])) {
        $errors[] = 'Selected template is not available.';
    }

    $fieldValues = [];
    $extraValues = [];

    if ($selectedTemplate) {
        foreach ($selectedTemplate['fields'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            $value = trim($_POST['field'][$name] ?? '');
            if (!empty($field['required']) && $value === '') {
                $errors[] = ($field['label'] ?? $name) . ' is required.';
            }
            $fieldValues[$name] = $value;
        }
        foreach ($selectedTemplate['extra_sections'] ?? [] as $extra) {
            $name = $extra['name'] ?? '';
            $value = trim($_POST['extra'][$name] ?? '');
            if (!empty($extra['required']) && $value === '') {
                $errors[] = ($extra['label'] ?? $name) . ' is required.';
            }
            $extraValues[$name] = $value;
        }
    }

    if (empty($errors) && $selectedTemplate) {
        $allValues = array_merge($fieldValues, $extraValues);
        $mergedBody = render_document_body($selectedTemplate, $allValues);
        $withLetterhead = $userDepartment ? render_with_letterhead($mergedBody, $userDepartment, true) : $mergedBody;
        $now = gmdate('c');
        $docId = generate_document_id($category, $allRecords);
        $record = [
            'id' => $docId,
            'template_id' => $selectedTemplate['id'] ?? '',
            'template_name' => $selectedTemplate['name'] ?? '',
            'category' => $category,
            'department_id' => $userDepartment['id'] ?? '',
            'fields' => $fieldValues,
            'extra_sections' => $extraValues,
            'rendered_body' => $withLetterhead,
            'status' => 'Final',
            'created_by' => $user['username'] ?? '',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $record = acl_initialize_new($record, $currentUser);
        $allRecords[] = $record;
        save_work_orders($allRecords);
        log_event('work_order_generated', $user['username'] ?? null, [
            'id' => $docId,
            'template_id' => $selectedTemplate['id'] ?? '',
        ]);
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=work_orders&mode=view&id=' . urlencode($docId));
        exit;
    }
}

if ($mode === 'create') {
    $selectedTemplateId = $_POST['template_id'] ?? ($_GET['template_id'] ?? '');
    $selectedTemplate = $selectedTemplateId ? find_document_template_by_id($templates, $selectedTemplateId) : null;
    $activeTemplates = array_filter($templates, function ($tpl) {
        return !empty($tpl['active']);
    });
    ?>
    <div class="form-grid">
        <div class="card">
            <h3>Select Work Order Template</h3>
            <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php">
                <input type="hidden" name="page" value="work_orders">
                <input type="hidden" name="mode" value="create">
                <label for="template_id">Available Templates</label>
                <select name="template_id" id="template_id" required>
                    <option value="">-- Choose a template --</option>
                    <?php foreach ($activeTemplates as $tpl): ?>
                        <option value="<?= htmlspecialchars($tpl['id']); ?>" <?= ($tpl['id'] === $selectedTemplateId) ? 'selected' : ''; ?>><?= htmlspecialchars($tpl['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Use Template</button>
            </form>
            <?php if ($selectedTemplate && !empty($selectedTemplate['active'])): ?>
                <div class="template-summary">
                    <h4><?= htmlspecialchars($selectedTemplate['name']); ?></h4>
                    <p><?= nl2br(htmlspecialchars($selectedTemplate['description'] ?? '')); ?></p>
                </div>
            <?php elseif ($selectedTemplateId && (!$selectedTemplate || empty($selectedTemplate['active']))): ?>
                <p class="error">Selected template is unavailable.</p>
            <?php endif; ?>
        </div>

        <?php if ($selectedTemplate && empty($selectedTemplate['active']) === false): ?>
        <div class="card">
            <h3>Fill Work Order Details</h3>
            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=work_orders&amp;mode=create">
                <input type="hidden" name="template_id" value="<?= htmlspecialchars($selectedTemplate['id']); ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <?php foreach ($selectedTemplate['fields'] as $field): ?>
                    <?php
                        $name = $field['name'];
                        $label = $field['label'] ?? $name;
                        $type = $field['type'] ?? 'text';
                        $required = !empty($field['required']);
                        $value = $_POST['field'][$name] ?? '';
                    ?>
                    <div class="form-field">
                        <label for="field_<?= htmlspecialchars($name); ?>"><?= htmlspecialchars($label); ?><?= $required ? ' *' : ''; ?></label>
                        <?php if ($type === 'textarea'): ?>
                            <textarea id="field_<?= htmlspecialchars($name); ?>" name="field[<?= htmlspecialchars($name); ?>]" <?= $required ? 'required' : ''; ?> data-ai-suggest="true"><?= htmlspecialchars($value); ?></textarea>
                            <button type="button" class="button ai-suggest-btn" data-target="field_<?= htmlspecialchars($name); ?>">Get Suggestion (Coming Soon)</button>
                        <?php else: ?>
                            <input id="field_<?= htmlspecialchars($name); ?>" type="<?= htmlspecialchars($type); ?>" name="field[<?= htmlspecialchars($name); ?>]" value="<?= htmlspecialchars($value); ?>" <?= $required ? 'required' : ''; ?> />
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($selectedTemplate['extra_sections'])): ?>
                    <h4>Additional Sections</h4>
                    <?php foreach ($selectedTemplate['extra_sections'] as $extra): ?>
                        <?php
                            $name = $extra['name'];
                            $label = $extra['label'] ?? $name;
                            $type = $extra['type'] ?? 'text';
                            $required = !empty($extra['required']);
                            $value = $_POST['extra'][$name] ?? '';
                        ?>
                        <div class="form-field">
                            <label for="extra_<?= htmlspecialchars($name); ?>"><?= htmlspecialchars($label); ?><?= $required ? ' *' : ''; ?></label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea id="extra_<?= htmlspecialchars($name); ?>" name="extra[<?= htmlspecialchars($name); ?>]" <?= $required ? 'required' : ''; ?> data-ai-suggest="true"><?= htmlspecialchars($value); ?></textarea>
                                <button type="button" class="button ai-suggest-btn" data-target="extra_<?= htmlspecialchars($name); ?>">Get Suggestion (Coming Soon)</button>
                            <?php else: ?>
                                <input id="extra_<?= htmlspecialchars($name); ?>" type="<?= htmlspecialchars($type); ?>" name="extra[<?= htmlspecialchars($name); ?>]" value="<?= htmlspecialchars($value); ?>" <?= $required ? 'required' : ''; ?> />
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button type="submit">Generate Work Order</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return;
}

$search = $_GET['q'] ?? '';
$filtered = filter_document_records($records, $search, ['id', 'template_name', 'status', 'created_by']);

usort($filtered, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

$page = get_page_param('p');
$pagination = paginate_array($filtered, $page, $config['pagination_per_page'] ?? 10);
?>
<div class="actions" style="display:flex; justify-content: space-between; margin-bottom:10px; gap:10px; align-items:center;">
    <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php" style="display:flex; gap:6px; align-items:center;">
        <input type="hidden" name="page" value="work_orders">
        <input type="text" name="q" placeholder="Search work orders" value="<?= htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>
    <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=work_orders&amp;mode=create">Create New Work Order</a>
</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Template</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pagination['items'])): ?>
                <tr><td colspan="5">No work orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($pagination['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($item['template_name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($item['status'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($item['created_at'] ?? ''); ?></td>
                        <td><a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=work_orders&amp;mode=view&amp;id=<?= urlencode($item['id'] ?? ''); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= ($pagination['total_pages'] ?? 1); $i++): ?>
        <a class="<?= ($i === ($pagination['page'] ?? 1)) ? 'active' : ''; ?>" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=work_orders&amp;p=<?= $i; ?>&amp;q=<?= urlencode($search); ?>">Page <?= $i; ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
