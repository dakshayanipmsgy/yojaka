<?php
require_login();

$user = current_user();
$canManage = user_has_permission('manage_rti');
$canViewAll = user_has_permission('view_all_records');
$currentOfficeId = get_current_office_id();
$currentLicense = get_current_office_license();
$officeReadOnly = office_is_read_only($currentLicense);
if (!$canManage && !user_has_permission('view_reports_basic')) {
    require_permission('manage_rti');
}
$cases = load_rti_cases();
$mode = $_GET['mode'] ?? 'list';
$mode = in_array($mode, ['list', 'create', 'view'], true) ? $mode : 'list';
$errors = [];
$notice = '';
$pagination = null;
$attachmentErrors = [];
$attachmentNotice = '';
$attachmentToken = '';
$usersList = load_users();

$csrfToken = $_SESSION['rti_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['rti_csrf_token'] = $csrfToken;

function sanitize_field($value): string
{
    return trim((string) $value);
}

if ($mode === 'create') {
    if (!$canManage) {
        require_permission('manage_rti');
    }
    if ($officeReadOnly) {
        $errors[] = 'This office is in read-only mode because the license is expired or invalid.';
    }
}

if ($mode === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['rti_csrf_token'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please try again.';
    }

    $referenceNumber = sanitize_field($_POST['reference_number'] ?? '');
    $applicantName = sanitize_field($_POST['applicant_name'] ?? '');
    $subject = sanitize_field($_POST['subject'] ?? '');
    $details = sanitize_field($_POST['details'] ?? '');
    $dateOfReceipt = sanitize_field($_POST['date_of_receipt'] ?? '');

    if ($referenceNumber === '') { $errors[] = 'Reference number is required.'; }
    if ($applicantName === '') { $errors[] = 'Applicant name is required.'; }
    if ($subject === '') { $errors[] = 'Subject is required.'; }
    if ($details === '') { $errors[] = 'Details are required.'; }
    if ($dateOfReceipt === '') { $errors[] = 'Date of receipt is required.'; }

    $deadline = '';
    if ($dateOfReceipt !== '') {
        try {
            $dateTest = new DateTime($dateOfReceipt);
            $dateOfReceipt = $dateTest->format('Y-m-d');
            $deadline = compute_rti_reply_deadline($dateOfReceipt);
        } catch (Exception $e) {
            $errors[] = 'Invalid date of receipt.';
        }
    }

    if (empty($errors) && !$officeReadOnly) {
        $now = gmdate('c');
        $newCase = [
            'id' => generate_next_rti_id($cases),
            'reference_number' => $referenceNumber,
            'applicant_name' => $applicantName,
            'subject' => $subject,
            'details' => $details,
            'date_of_receipt' => $dateOfReceipt,
            'reply_deadline' => $deadline,
            'status' => 'Pending',
            'reply_date' => null,
            'reply_summary' => null,
            'assigned_to' => $user['username'] ?? null,
            'created_by' => $user['username'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'workflow_state' => get_default_workflow_state('rti'),
            'current_approver' => null,
            'approver_chain' => [],
            'last_action' => 'created',
            'last_action_at' => $now,
            'office_id' => $currentOfficeId,
        ];
        $cases[] = $newCase;
        save_rti_cases($cases);
        log_event('rti_created', $user['username'] ?? null, [
            'rti_id' => $newCase['id'],
            'reference_number' => $referenceNumber,
        ]);
        write_audit_log('rti', $newCase['id'], 'create');
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($newCase['id']));
        exit;
    }
}

if ($mode === 'view') {
    $id = $_GET['id'] ?? '';
    $case = $id ? find_rti_by_id($cases, $id) : null;
    if ($case && !$canViewAll && ($case['office_id'] ?? $currentOfficeId) !== $currentOfficeId) {
        $errors[] = 'You cannot access RTI cases from other offices.';
        $case = null;
    }
    if (!$case) {
        $errors[] = 'RTI case not found.';
    } elseif (!$canViewAll && ($case['created_by'] ?? '') !== ($user['username'] ?? '')) {
        $errors[] = 'You are not allowed to view this RTI.';
        $case = null;
    }

    if ($case) {
        write_audit_log('rti', $case['id'], 'view');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['attachment_upload'])) {
            $submittedToken = $_POST['csrf_token'] ?? '';
            if (!$submittedToken || !hash_equals($_SESSION['rti_csrf_token'], $submittedToken)) {
                $errors[] = 'Security token mismatch. Please try again.';
            }
            if ($officeReadOnly) {
                $errors[] = 'This office is in read-only mode; changes are blocked.';
            }
            if (empty($errors) && isset($_POST['archive_action']) && $canManage && empty($case['archived'])) {
                archive_entity($case, trim($_POST['archive_reason'] ?? 'Manual archive'));
                update_rti_case($cases, $case);
                save_rti_cases($cases);
                log_event('rti_archived', $user['username'] ?? null, ['rti_id' => $case['id']]);
                write_audit_log('rti', $case['id'], 'archive');
                header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($case['id']));
                exit;
            }
            if (empty($errors) && isset($_POST['assign_to']) && $canManage) {
                $assignTo = trim($_POST['assign_to']);
                if ($assignTo !== '') {
                    $case['assigned_to'] = $assignTo;
                    $case['workflow_state'] = $case['workflow_state'] === 'new' ? 'assigned' : $case['workflow_state'];
                    $case['last_action'] = 'assigned';
                    $case['last_action_at'] = gmdate('c');
                    $case['updated_at'] = gmdate('c');
                    update_rti_case($cases, $case);
                    save_rti_cases($cases);
                    create_notification($assignTo, 'rti', $case['id'], 'rti_assigned', 'RTI case assigned to you', ($case['id'] ?? '') . ' has been assigned.');
                    log_event('rti_assigned', $user['username'] ?? null, ['rti_id' => $case['id'], 'assigned_to' => $assignTo]);
                    write_audit_log('rti', $case['id'], 'update', ['assigned_to' => $assignTo]);
                    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($case['id']));
                    exit;
                }
            }
            if (empty($errors) && isset($_POST['new_state'])) {
                $newState = $_POST['new_state'];
                $currentState = $case['workflow_state'] ?? get_default_workflow_state('rti');
                if (can_transition_workflow('rti', $currentState, $newState, $user)) {
                    $case['workflow_state'] = $newState;
                    if ($newState === 'closed') {
                        $case['status'] = 'Closed';
                    }
                    $case['last_action'] = 'workflow_changed';
                    $case['last_action_at'] = gmdate('c');
                    $case['updated_at'] = gmdate('c');
                    update_rti_case($cases, $case);
                    save_rti_cases($cases);
                    log_event('rti_workflow_changed', $user['username'] ?? null, ['rti_id' => $case['id'], 'new_state' => $newState]);
                    if (!empty($case['created_by'])) {
                        create_notification($case['created_by'], 'rti', $case['id'], 'rti_workflow_changed', 'RTI updated', 'RTI ' . ($case['id'] ?? '') . ' moved to ' . $newState);
                    }
                    write_audit_log('rti', $case['id'], 'workflow_change', ['new_state' => $newState]);
                    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($case['id']));
                    exit;
                } else {
                    $errors[] = 'You are not allowed to move this RTI to the selected state.';
                }
            }
        }
        $canUploadAttachments = ($canManage || user_has_permission('create_documents')) && !$officeReadOnly;
        [$attachmentErrors, $attachmentNotice, $attachmentToken] = handle_attachment_upload('rti', $case['id'], 'rti_attachment_csrf', $canUploadAttachments);
        if (!empty($attachmentNotice) && empty($attachmentErrors)) {
            header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($case['id']));
            exit;
        }
        $caseAttachments = find_attachments_for_entity('rti', $case['id']);
    }
}

if ($mode === 'list') {
    if ($canViewAll || $canManage) {
        $visibleCases = $cases;
    } else {
        $visibleCases = array_filter($cases, function ($case) use ($user) {
            return ($case['created_by'] ?? null) === ($user['username'] ?? null);
        });
    }

    if (!$canViewAll) {
        $visibleCases = filter_records_by_office($visibleCases, $currentOfficeId);
    }

    $searchTerm = trim($_GET['q'] ?? '');
    $statusOptions = ['Pending', 'Replied', 'Closed'];
    $statusFilter = trim($_GET['status'] ?? '');
    if (!in_array($statusFilter, $statusOptions, true)) {
        $statusFilter = '';
    }
    $archivedFilter = $_GET['archived'] ?? 'active';
    $archivedAllowed = ['active', 'archived', 'all'];
    if (!in_array($archivedFilter, $archivedAllowed, true)) {
        $archivedFilter = 'active';
    }

    $visibleCases = filter_items_search($visibleCases, $searchTerm, ['reference_number', 'applicant_name', 'subject', 'details']);
    if ($statusFilter !== '') {
        $visibleCases = array_filter($visibleCases, function ($case) use ($statusFilter) {
            return ($case['status'] ?? '') === $statusFilter;
        });
    }
    if ($archivedFilter === 'active') {
        $visibleCases = array_filter($visibleCases, function ($case) {
            return empty($case['archived']);
        });
    } elseif ($archivedFilter === 'archived') {
        $visibleCases = array_filter($visibleCases, function ($case) {
            return !empty($case['archived']);
        });
    }

    $perPage = $config['pagination_per_page'] ?? 10;
    $pageParam = 'p';
    $pagination = paginate_array(array_values($visibleCases), get_page_param($pageParam), $perPage);
    $visibleCases = $pagination['items'];
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

<?php if ($mode === 'list'): ?>
    <div class="actions" style="margin-bottom: 1rem; display:flex; justify-content: space-between; align-items: center; gap: 1rem;">
        <div>
            <strong>Your RTI cases</strong> <?= ($canViewAll || $canManage) ? '(all cases shown per your permissions)' : ''; ?>
        </div>
        <?php if ($canManage && !$officeReadOnly): ?>
            <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti&mode=create">Create New RTI</a>
        <?php elseif ($officeReadOnly): ?>
            <span class="muted">Creation disabled - office is in read-only mode.</span>
        <?php endif; ?>
    </div>
    <div class="filter-bar">
        <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php" class="form-inline">
            <input type="hidden" name="page" value="rti">
            <div class="form-field">
                <input type="text" name="q" placeholder="Search RTIs (reference, applicant, subject)" value="<?= htmlspecialchars($_GET['q'] ?? ''); ?>">
            </div>
            <div class="form-field">
                <label for="status">Status</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($statusOptions as $statusOpt): ?>
                        <option value="<?= htmlspecialchars($statusOpt); ?>" <?= ($statusFilter ?? '') === $statusOpt ? 'selected' : ''; ?>><?= htmlspecialchars($statusOpt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="archived">Archived</label>
                <select id="archived" name="archived" onchange="this.form.submit()">
                    <option value="active" <?= $archivedFilter === 'active' ? 'selected' : ''; ?>>Active only</option>
                    <option value="archived" <?= $archivedFilter === 'archived' ? 'selected' : ''; ?>>Archived only</option>
                    <option value="all" <?= $archivedFilter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <button type="submit" class="btn">Search</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Reference #</th>
                    <th>Applicant</th>
                    <th>Subject</th>
                    <th>Date of Receipt</th>
                    <th>Reply Deadline</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visibleCases)): ?>
                    <tr><td colspan="8">No RTI cases found.</td></tr>
                <?php else: ?>
                    <?php foreach ($visibleCases as $case): ?>
                        <?php $overdue = is_rti_overdue($case); ?>
                        <tr>
                            <td><?= htmlspecialchars($case['id']); ?><?= !empty($case['archived']) ? ' <span class="badge">Archived</span>' : ''; ?></td>
                            <td><?= htmlspecialchars($case['reference_number'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['applicant_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['subject'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['date_of_receipt'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['reply_deadline'] ?? ''); ?><?= $overdue ? ' <span class="badge badge-danger">Overdue</span>' : ''; ?></td>
                            <td><span class="badge <?= $overdue ? 'badge-danger' : 'badge-soft'; ?>"><?= htmlspecialchars($case['status'] ?? ''); ?></span></td>
                            <td><a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti&mode=view&id=<?= urlencode($case['id']); ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination): ?>
        <div class="pagination">
            <span>Page <?= (int) $pagination['page']; ?> of <?= (int) $pagination['total_pages']; ?></span>
            <?php
            $queryBase = [
                'page' => 'rti',
                'q' => $_GET['q'] ?? '',
                'status' => $statusFilter ?? '',
            ];
            $pageParam = 'p';
            ?>
            <div class="pager-links">
                <?php if ($pagination['page'] > 1): ?>
                    <?php $prevQuery = http_build_query(array_merge($queryBase, [$pageParam => $pagination['page'] - 1])); ?>
                    <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= $prevQuery; ?>">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                    <?php $nextQuery = http_build_query(array_merge($queryBase, [$pageParam => $pagination['page'] + 1])); ?>
                    <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= $nextQuery; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php elseif ($mode === 'create'): ?>
    <h3>Create New RTI Case</h3>
    <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti&mode=create" class="form-stacked">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <div class="form-field">
            <label for="reference_number">Reference Number *</label>
            <input type="text" id="reference_number" name="reference_number" value="<?= htmlspecialchars($_POST['reference_number'] ?? ''); ?>" required>
        </div>
        <div class="form-field">
            <label for="applicant_name">Applicant Name *</label>
            <input type="text" id="applicant_name" name="applicant_name" value="<?= htmlspecialchars($_POST['applicant_name'] ?? ''); ?>" required>
        </div>
        <div class="form-field">
            <label for="subject">Subject *</label>
            <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
        </div>
        <div class="form-field">
            <label for="details">Details *</label>
            <textarea id="details" name="details" required><?= htmlspecialchars($_POST['details'] ?? ''); ?></textarea>
        </div>
        <div class="form-field">
            <label for="date_of_receipt">Date of Receipt *</label>
            <input type="date" id="date_of_receipt" name="date_of_receipt" value="<?= htmlspecialchars($_POST['date_of_receipt'] ?? date('Y-m-d')); ?>" required>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit">Save RTI</button>
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti">Cancel</a>
        </div>
    </form>
<?php elseif ($mode === 'view' && !empty($case)): ?>
    <div class="rti-detail">
        <div class="detail-grid">
            <div>
                <div class="muted">RTI ID</div>
                <div class="strong"><?= htmlspecialchars($case['id']); ?></div>
            </div>
            <div>
                <div class="muted">Reference Number</div>
                <div><?= htmlspecialchars($case['reference_number'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Applicant</div>
                <div><?= htmlspecialchars($case['applicant_name'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Subject</div>
                <div><?= htmlspecialchars($case['subject'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Date of Receipt</div>
                <div><?= htmlspecialchars($case['date_of_receipt'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Reply Deadline</div>
                <div><?= htmlspecialchars($case['reply_deadline'] ?? ''); ?>
                    <?php if (is_rti_overdue($case)): ?>
                        <span class="badge badge-danger">Overdue</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="muted">Status</div>
                <div><span class="badge badge-soft"><?= htmlspecialchars($case['status'] ?? ''); ?></span></div>
            </div>
            <div>
                <div class="muted">Archived</div>
                <div><?= !empty($case['archived']) ? 'Yes (since ' . htmlspecialchars($case['archived_at'] ?? '') . ')' : 'No'; ?></div>
            </div>
            <div>
                <div class="muted">Assigned To</div>
                <div><?= htmlspecialchars($case['assigned_to'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Reply Date</div>
                <div><?= htmlspecialchars($case['reply_date'] ?? '—'); ?></div>
            </div>
            <div>
                <div class="muted">Reply Summary</div>
                <div><?= nl2br(htmlspecialchars($case['reply_summary'] ?? '—')); ?></div>
            </div>
            <div>
                <div class="muted">Created By</div>
                <div><?= htmlspecialchars($case['created_by'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Created At</div>
                <div><?= htmlspecialchars($case['created_at'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Updated At</div>
                <div><?= htmlspecialchars($case['updated_at'] ?? ''); ?></div>
            </div>
        </div>
        <div class="card" style="margin-top:1rem;">
            <h3>Details</h3>
            <p><?= nl2br(htmlspecialchars($case['details'] ?? '')); ?></p>
        </div>
        <div class="card" style="margin-top:1rem;">
            <h3>Workflow &amp; Assignment</h3>
            <p><strong>Workflow State:</strong> <?= htmlspecialchars($case['workflow_state'] ?? get_default_workflow_state('rti')); ?></p>
            <p><strong>Assigned To:</strong> <?= htmlspecialchars($case['assigned_to'] ?? 'Unassigned'); ?></p>
            <?php if ($canManage): ?>
                <form method="post" class="form-inline" style="gap:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <label>Assign to
                        <select name="assign_to">
                            <option value="">Select user</option>
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?= htmlspecialchars($u['username'] ?? ''); ?>" <?= ($case['assigned_to'] ?? '') === ($u['username'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($u['full_name'] ?? ($u['username'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="btn" type="submit">Assign</button>
                </form>
            <?php endif; ?>
            <?php
            $availableTransitions = workflow_definitions()['rti']['transitions'][$case['workflow_state'] ?? get_default_workflow_state('rti')] ?? [];
            ?>
            <?php if (!empty($availableTransitions)): ?>
                <div class="form-actions" style="margin-top:0.5rem;">
                    <?php foreach ($availableTransitions as $state): ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="new_state" value="<?= htmlspecialchars($state); ?>">
                            <button class="btn" type="submit">Mark <?= htmlspecialchars($state); ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($canManage && empty($case['archived']) && !$officeReadOnly): ?>
            <div class="card" style="margin-top:1rem;">
                <h3>Archive Case</h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="archive_action" value="1">
                    <div class="form-field">
                        <label>Reason (optional)</label>
                        <input type="text" name="archive_reason" placeholder="Retention policy">
                    </div>
                    <button class="btn danger" type="submit">Archive this RTI</button>
                </form>
            </div>
        <?php endif; ?>
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
                        <?php if (empty($caseAttachments)): ?>
                            <tr><td colspan="6">No attachments yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($caseAttachments as $att): ?>
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
            <?php if ($canManage || user_has_permission('create_documents')): ?>
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
        <div style="margin-top:1rem;">
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti">Back to list</a>
            <?php if (user_has_permission('admin_backup')): ?>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=case_export&module=rti&id=<?= urlencode($case['id'] ?? ''); ?>">Export Case Bundle</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
