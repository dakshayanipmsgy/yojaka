<?php
require_login();
$user = current_user();
$canManageDak = user_has_permission('manage_dak');
$canViewAll = user_has_permission('view_all_records');
if (!$canManageDak && !user_has_permission('view_reports_basic')) {
    require_permission('manage_dak');
}
$mode = $_GET['mode'] ?? 'list';
$entries = load_dak_entries();
$errors = [];
$attachmentErrors = [];
$attachmentNotice = '';
$attachmentToken = '';
$csrfToken = $_SESSION['dak_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['dak_csrf_token'] = $csrfToken;
$usersList = load_users();

if ($mode === 'create') {
    if (!$canManageDak) {
        require_permission('manage_dak');
    }
}

if ($mode === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_no = trim($_POST['reference_no'] ?? '');
    $received_from = trim($_POST['received_from'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $date_received = trim($_POST['date_received'] ?? '');

    if ($reference_no === '' || $received_from === '' || $subject === '' || $date_received === '') {
        $errors[] = 'All fields except details are required.';
    }

    if (empty($errors)) {
        $now = gmdate('c');
        $dakId = generate_next_dak_id($entries);
        $entries[] = [
            'id' => $dakId,
            'reference_no' => $reference_no,
            'received_from' => $received_from,
            'subject' => $subject,
            'details' => $details,
            'date_received' => $date_received,
            'status' => 'Received',
            'assigned_to' => null,
            'created_by' => $user['username'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'workflow_state' => get_default_workflow_state('dak'),
            'current_approver' => null,
            'approver_chain' => [],
            'last_action' => 'created',
            'last_action_at' => $now,
        ];

        save_dak_entries($entries);
        log_event('dak_created', $user['username'] ?? null, ['dak_id' => $dakId]);
        log_dak_movement($dakId, 'created', null, $user['username'] ?? null, 'Dak entry created');

        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($dakId));
        exit;
    }
}

$targetId = $_GET['id'] ?? null;
?>

<?php if ($mode === 'create'): ?>
    <div class="form-grid">
        <form method="post" class="form-card">
            <h3>New Dak Entry</h3>
            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?= htmlspecialchars(implode(' ', $errors)); ?>
                </div>
            <?php endif; ?>
            <label>Reference No
                <input type="text" name="reference_no" value="<?= htmlspecialchars($_POST['reference_no'] ?? ''); ?>" required>
            </label>
            <label>Received From
                <input type="text" name="received_from" value="<?= htmlspecialchars($_POST['received_from'] ?? ''); ?>" required>
            </label>
            <label>Subject
                <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
            </label>
            <label>Details / Summary
                <textarea name="details" rows="4" placeholder="Optional additional details"><?= htmlspecialchars($_POST['details'] ?? ''); ?></textarea>
            </label>
            <label>Date Received
                <input type="date" name="date_received" value="<?= htmlspecialchars($_POST['date_received'] ?? ''); ?>" required>
            </label>
            <div class="actions">
                <button type="submit" class="btn primary">Save Dak</button>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak">Cancel</a>
            </div>
        </form>
    </div>
<?php elseif ($mode === 'view' && $targetId): ?>
    <?php
    $entry = null;
    foreach ($entries as $e) {
        if (($e['id'] ?? '') === $targetId) {
            $entry = $e;
            break;
        }
    }
    ?>
    <?php if (!$entry): ?>
        <p class="alert error">Dak entry not found.</p>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak">Back to list</a>
    <?php else: ?>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['attachment_upload'])) {
            $submittedToken = $_POST['csrf_token'] ?? '';
            if (!$submittedToken || !hash_equals($csrfToken, $submittedToken)) {
                $errors[] = 'Security token mismatch. Please try again.';
            }
            if (empty($errors) && isset($_POST['assign_to']) && $canManageDak) {
                $assignTo = trim($_POST['assign_to']);
                if ($assignTo !== '') {
                    $entry['assigned_to'] = $assignTo;
                    $entry['workflow_state'] = $entry['workflow_state'] === 'received' ? 'assigned' : $entry['workflow_state'];
                    $entry['last_action'] = 'assigned';
                    $entry['last_action_at'] = gmdate('c');
                    $entry['updated_at'] = gmdate('c');
                    foreach ($entries as &$item) {
                        if (($item['id'] ?? '') === $entry['id']) {
                            $item = $entry;
                            break;
                        }
                    }
                    unset($item);
                    save_dak_entries($entries);
                    create_notification($assignTo, 'dak', $entry['id'], 'dak_assigned', 'Dak assigned to you', ($entry['id'] ?? '') . ' has been assigned.');
                    log_event('dak_assigned', $user['username'] ?? null, ['dak_id' => $entry['id'], 'assigned_to' => $assignTo]);
                    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entry['id']));
                    exit;
                }
            }
            if (empty($errors) && isset($_POST['new_state'])) {
                $newState = $_POST['new_state'];
                $currentState = $entry['workflow_state'] ?? get_default_workflow_state('dak');
                if (can_transition_workflow('dak', $currentState, $newState, $user)) {
                    $entry['workflow_state'] = $newState;
                    if ($newState === 'closed') {
                        $entry['status'] = 'Closed';
                    }
                    $entry['last_action'] = 'workflow_changed';
                    $entry['last_action_at'] = gmdate('c');
                    $entry['updated_at'] = gmdate('c');
                    foreach ($entries as &$item) {
                        if (($item['id'] ?? '') === $entry['id']) {
                            $item = $entry;
                            break;
                        }
                    }
                    unset($item);
                    save_dak_entries($entries);
                    log_event('dak_workflow_changed', $user['username'] ?? null, ['dak_id' => $entry['id'], 'new_state' => $newState]);
                    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entry['id']));
                    exit;
                } else {
                    $errors[] = 'You are not allowed to move this Dak entry to the selected state.';
                }
            }
        }
        $canUploadAttachments = $canManageDak || user_has_permission('create_documents');
        [$attachmentErrors, $attachmentNotice, $attachmentToken] = handle_attachment_upload('dak', $entry['id'], 'dak_attachment_csrf', $canUploadAttachments);
        if (!empty($attachmentNotice) && empty($attachmentErrors)) {
            header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entry['id']));
            exit;
        }
        $entryAttachments = find_attachments_for_entity('dak', $entry['id']);
        ?>
        <div class="grid">
            <div class="card">
                <h3>Dak Details</h3>
                <p><strong>ID:</strong> <?= htmlspecialchars($entry['id']); ?></p>
                <p><strong>Reference No:</strong> <?= htmlspecialchars($entry['reference_no']); ?></p>
                <p><strong>Received From:</strong> <?= htmlspecialchars($entry['received_from']); ?></p>
                <p><strong>Subject:</strong> <?= htmlspecialchars($entry['subject']); ?></p>
                <p><strong>Date Received:</strong> <?= htmlspecialchars($entry['date_received']); ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($entry['status']); ?></p>
                <p><strong>Assigned To:</strong> <?= htmlspecialchars($entry['assigned_to'] ?? 'Unassigned'); ?></p>
                <p><strong>Details:</strong><br><?= nl2br(htmlspecialchars($entry['details'] ?? '')); ?></p>
                <p><strong>Created By:</strong> <?= htmlspecialchars($entry['created_by'] ?? ''); ?></p>
            </div>
            <div class="card">
                <h3>Workflow &amp; Assignment</h3>
                <p><strong>Workflow State:</strong> <?= htmlspecialchars($entry['workflow_state'] ?? get_default_workflow_state('dak')); ?></p>
                <?php if ($canManageDak): ?>
                    <form method="post" class="form-inline" style="gap:0.5rem;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <label>Assign to
                            <select name="assign_to">
                                <option value="">Select user</option>
                                <?php foreach ($usersList as $u): ?>
                                    <option value="<?= htmlspecialchars($u['username'] ?? ''); ?>" <?= ($entry['assigned_to'] ?? '') === ($u['username'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($u['full_name'] ?? ($u['username'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="btn" type="submit">Assign</button>
                    </form>
                <?php endif; ?>
                <?php $dakTransitions = workflow_definitions()['dak']['transitions'][$entry['workflow_state'] ?? get_default_workflow_state('dak')] ?? []; ?>
                <?php if (!empty($dakTransitions)): ?>
                    <div class="form-actions" style="margin-top:0.5rem;">
                        <?php foreach ($dakTransitions as $state): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="new_state" value="<?= htmlspecialchars($state); ?>">
                                <button class="btn" type="submit">Mark <?= htmlspecialchars($state); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Movement History</h3>
                <?php
                $history = [];
                $logPath = movement_logs_path($entry['id']);
                if (file_exists($logPath)) {
                    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $decoded = json_decode($line, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $history[] = $decoded;
                        }
                    }
                }
                ?>
                <?php if (empty($history)): ?>
                    <p>No movement recorded.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Action</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['timestamp'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($item['action'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($item['from_user'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($item['to_user'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($item['remarks'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div class="actions">
                    <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak">Back to list</a>
                </div>
            </div>
            <div class="card">
                <h3>Attachments</h3>
                <?php if (!empty($attachmentErrors)): ?>
                    <div class="alert error">
                        <ul>
                            <?php foreach ($attachmentErrors as $err): ?>
                                <li><?= htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php elseif ($attachmentNotice !== ''): ?>
                    <div class="alert success"><?= htmlspecialchars($attachmentNotice); ?></div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Description</th><th>File</th><th>Size</th><th>Uploaded By</th><th>Uploaded At</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($entryAttachments)): ?>
                                <tr><td colspan="6">No attachments yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($entryAttachments as $att): ?>
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
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php
    $userDak = [];
    foreach ($entries as $entry) {
        if ($canViewAll || $canManageDak || ($entry['assigned_to'] ?? null) === ($user['username'] ?? null) || ($entry['created_by'] ?? null) === ($user['username'] ?? null)) {
            $userDak[] = $entry;
        }
    }
    ?>
    <div class="actions">
        <?php if ($canManageDak): ?>
            <a class="btn primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=create">Add New Dak</a>
        <?php endif; ?>
    </div>
    <?php if (empty($userDak)): ?>
        <p>No dak entries assigned to you yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Reference</th>
                    <th>Subject</th>
                    <th>Date Received</th>
                    <th>Status</th>
                    <th>Overdue</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userDak as $entry): ?>
                    <?php $overdue = is_dak_overdue($entry); ?>
                    <tr class="<?= $overdue ? 'warn' : ''; ?>">
                        <td><?= htmlspecialchars($entry['id']); ?></td>
                        <td><?= htmlspecialchars($entry['reference_no']); ?></td>
                        <td><?= htmlspecialchars($entry['subject']); ?></td>
                        <td><?= htmlspecialchars($entry['date_received']); ?></td>
                        <td><?= htmlspecialchars($entry['status']); ?></td>
                        <td><?= $overdue ? '<span class="badge warn">Overdue</span>' : '-'; ?></td>
                        <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=view&id=<?= urlencode($entry['id']); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>
