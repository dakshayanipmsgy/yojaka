<?php
require_login();
require_once __DIR__ . '/../acl.php';
$currentUser = get_current_user();
$user = $currentUser;
$canManageDak = user_has_permission('manage_dak');
$canViewAll = user_has_permission('view_all_records');
$currentOfficeId = get_current_office_id();
$currentLicense = get_current_office_license();
$officeReadOnly = office_is_read_only($currentLicense);
if (!$canManageDak && !user_has_permission('view_reports_basic')) {
    require_permission('manage_dak');
}
$mode = $_GET['mode'] ?? 'list';
$entries = load_dak_entries();
$visibleEntries = [];
foreach ($entries as $entryRecord) {
    $entryRecord = acl_normalize($entryRecord);
    if (acl_can_view($currentUser, $entryRecord)) {
        $visibleEntries[] = $entryRecord;
    }
}
$errors = [];
$attachmentErrors = [];
$attachmentNotice = '';
$attachmentToken = '';
$csrfToken = $_SESSION['dak_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['dak_csrf_token'] = $csrfToken;
$usersList = load_users();
$aiDraftText = '';

if ($mode === 'create') {
    if (!$canManageDak) {
        require_permission('manage_dak');
    }
    if ($officeReadOnly) {
        $errors[] = 'Office is read-only due to license status.';
    }
}

if ($mode === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ai_action = $_POST['ai_action'] ?? null;
    $reference_no = trim($_POST['reference_no'] ?? '');
    $received_from = trim($_POST['received_from'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $date_received = trim($_POST['date_received'] ?? '');

    if ($ai_action === 'draft') {
        $context = [
            'module' => 'dak',
            'office_name' => $currentOfficeId,
            'subject' => $subject,
            'recipient' => $received_from,
            'purpose' => $details,
        ];
        $prompt = build_ai_prompt_for_drafting($context);
        $aiDraftText = ai_generate_text('draft_document', $prompt, $context) ?? '';
        $_POST['details'] = $aiDraftText;
    } else {
        if ($reference_no === '' || $received_from === '' || $subject === '' || $date_received === '') {
            $errors[] = 'All fields except details are required.';
        }

        if (empty($errors) && !$officeReadOnly) {
            $now = gmdate('c');
            $dakId = generate_next_dak_id($entries);
            $newEntry = [
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
                'office_id' => $currentOfficeId,
                'movements' => [],
                'department_id' => $user['department_id'] ?? 'dept_default',
            ];
            $newEntry = acl_initialize_new($newEntry, $currentUser, $newEntry['assigned_to'] ?? null);
            $newEntry['assigned_to'] = $newEntry['assignee'];

            file_flow_initialize($newEntry, 'dak', $user['department_id'] ?? 'dept_default', $currentOfficeId);

            append_dak_movement($newEntry, 'created', null, $user['username'] ?? null, 'Dak entry created');
            $entries[] = $newEntry;
            save_dak_entries($entries);
            log_event('dak_created', $user['username'] ?? null, ['dak_id' => $dakId]);
            log_dak_movement($dakId, 'created', null, $user['username'] ?? null, 'Dak entry created');
            write_audit_log('dak', $dakId, 'create');

            header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($dakId));
            exit;
        }
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
                <textarea name="details" rows="4" placeholder="Optional additional details"><?= htmlspecialchars($_POST['details'] ?? $aiDraftText ?? ''); ?></textarea>
            </label>
            <label>Date Received
                <input type="date" name="date_received" value="<?= htmlspecialchars($_POST['date_received'] ?? ''); ?>" required>
            </label>
            <div class="actions">
                <button type="submit" class="btn primary">Save Dak</button>
                <button type="submit" class="btn" name="ai_action" value="draft"><?= htmlspecialchars(i18n_get('ai.draft_with_assistant')); ?></button>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak">Cancel</a>
            </div>
        </form>
    </div>
<?php elseif ($mode === 'view' && $targetId): ?>
    <?php
    $entry = null;
    foreach ($entries as $e) {
        if (($e['id'] ?? '') === $targetId) {
            $entry = acl_normalize($e);
            break;
        }
    }
    $routeSuggestions = $entry ? predict_next_positions($entry) : [];
    ?>
    <?php if ($entry && !acl_can_view($currentUser, $entry)): ?>
        <?php http_response_code(403); ?>
        <p class="alert error">You do not have access to this file.</p>
        <?php $entry = null; ?>
    <?php endif; ?>
    <?php if (!$entry): ?>
        <p class="alert error">Dak entry not found.</p>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak">Back to list</a>
    <?php else: ?>
        <?php
        write_audit_log('dak', $entry['id'], 'view');
        $entryChanged = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['attachment_upload'])) {
            if (!acl_can_edit($currentUser, $entry)) {
                http_response_code(403);
                echo "You cannot edit this file.";
                exit;
            }
            $submittedToken = $_POST['csrf_token'] ?? '';
            if (!$submittedToken || !hash_equals($csrfToken, $submittedToken)) {
                $errors[] = 'Security token mismatch. Please try again.';
            }
            if ($officeReadOnly) {
                $errors[] = 'Office is read-only; updates are blocked.';
            }
            if (empty($errors) && isset($_POST['file_flow_action'])) {
                $action = $_POST['file_flow_action'];
                if ($action === 'init_route') {
                    file_flow_initialize($entry, 'dak', $entry['department_id'] ?? ($user['department_id'] ?? 'dept_default'), $entry['office_id'] ?? $currentOfficeId, $_POST['route_template_id'] ?? null);
                    $entry['updated_at'] = gmdate('c');
                    $entry['last_action'] = 'route_initialized';
                    $entryChanged = true;
                } elseif ($action === 'forward') {
                    $nextIndex = ($entry['route']['current_node_index'] ?? -1) + 1;
                    $nextNode = $entry['route']['nodes'][$nextIndex] ?? null;
                    $targetUser = $nextNode['user_username'] ?? ($entry['assigned_to'] ?? ($entry['current_holder'] ?? null));
                    $targetUser = $targetUser ?: ($user['username'] ?? '');
                    if (!file_flow_forward_with_handover($entry, $user['username'] ?? '', $targetUser, $_POST['remarks'] ?? '')) {
                        $errors[] = 'Unable to move forward.';
                    } else {
                        $entry['assignee'] = $targetUser;
                        $entry['assigned_to'] = $targetUser;
                        $entry = acl_share_with_user($entry, $targetUser);
                        append_dak_movement($entry, 'forwarded', $user['username'] ?? null, $targetUser, $_POST['remarks'] ?? '', [
                            'status' => 'pending',
                            'accepted_by' => null,
                            'accepted_at' => null,
                            'rejected_reason' => null,
                        ]);
                        $entryChanged = true;
                    }
                } elseif ($action === 'return') {
                    $target = isset($_POST['target_index']) ? (int)$_POST['target_index'] : -1;
                    if (!file_flow_return($entry, $user['username'] ?? '', $_POST['remarks'] ?? '', $target)) {
                        $errors[] = 'Unable to return to the selected step.';
                    } else {
                        $entryChanged = true;
                    }
                } elseif ($action === 'reroute') {
                    $newPosition = trim($_POST['new_position_id'] ?? '');
                    if ($newPosition === '') {
                        $errors[] = 'Select a position to reroute.';
                    } else {
                        file_flow_reroute($entry, $user['username'] ?? '', $newPosition, $_POST['remarks'] ?? '');
                        $entryChanged = true;
                    }
                }
                file_flow_sync_assignment($entry);
                if (!empty($entry['assigned_to'])) {
                    $entry['assignee'] = $entry['assigned_to'];
                    $entry = acl_share_with_user($entry, $entry['assigned_to']);
                }
                $entry['updated_at'] = gmdate('c');
            }
            if (empty($errors) && isset($_POST['assign_to']) && $canManageDak) {
                $assignTo = trim($_POST['assign_to']);
                if ($assignTo !== '') {
                    $entry['assigned_to'] = $assignTo;
                    $entry['workflow_state'] = $entry['workflow_state'] === 'received' ? 'assigned' : $entry['workflow_state'];
                    $entry['last_action'] = 'assigned';
                    $entry['last_action_at'] = gmdate('c');
                    $entry['updated_at'] = gmdate('c');
                    $entry['assignee'] = $assignTo;
                    $entry = acl_share_with_user($entry, $assignTo);
                    append_dak_movement($entry, 'assigned', $entry['created_by'] ?? null, $assignTo, 'Assigned via admin');
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
                    write_audit_log('dak', $entry['id'], 'update', ['assigned_to' => $assignTo]);
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
                    append_dak_movement($entry, 'workflow_changed', $entry['assigned_to'] ?? null, $entry['assigned_to'] ?? null, 'State changed to ' . $newState);
                    foreach ($entries as &$item) {
                        if (($item['id'] ?? '') === $entry['id']) {
                            $item = $entry;
                            break;
                        }
                    }
                    unset($item);
                    save_dak_entries($entries);
                    log_event('dak_workflow_changed', $user['username'] ?? null, ['dak_id' => $entry['id'], 'new_state' => $newState]);
                    write_audit_log('dak', $entry['id'], 'workflow_change', ['new_state' => $newState]);
                    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entry['id']));
                    exit;
                } else {
                    $errors[] = 'You are not allowed to move this Dak entry to the selected state.';
                }
            }
            if ($entryChanged && empty($errors)) {
                foreach ($entries as &$item) {
                    if (($item['id'] ?? '') === $entry['id']) {
                        $item = $entry;
                        break;
                    }
                }
                unset($item);
                save_dak_entries($entries);
                log_event('dak_route_updated', $user['username'] ?? null, ['dak_id' => $entry['id']]);
                write_audit_log('dak', $entry['id'], 'update', ['route' => true]);
                header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entry['id']));
                exit;
            }
        }
        $canUploadAttachments = ($canManageDak || user_has_permission('create_documents')) && !$officeReadOnly;
        [$attachmentErrors, $attachmentNotice, $attachmentToken] = handle_attachment_upload('dak', $entry['id'], 'dak_attachment_csrf', $canUploadAttachments);
        if (!empty($attachmentNotice) && empty($attachmentErrors)) {
            header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dak&mode=view&id=' . urlencode($entry['id']));
            exit;
        }
        $entryAttachments = find_attachments_for_entity('dak', $entry['id']);
        $positionsList = load_positions($entry['office_id'] ?? $currentOfficeId);
        $availableRouteActions = file_flow_get_available_actions($entry, $user['username'] ?? '');
        ?>
        <div class="actions" style="margin-bottom:0.75rem; display:flex; gap:10px;">
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=print_document&amp;type=dak&amp;id=<?= urlencode($entry['id']); ?>" target="_blank">Print</a>
        </div>
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
                <p><strong>Current Holder:</strong> <?= htmlspecialchars($entry['current_holder'] ?? ($entry['assigned_to'] ?? '')); ?></p>
                <p><strong>Details:</strong><br><?= nl2br(htmlspecialchars($entry['details'] ?? '')); ?></p>
                <p><strong>Created By:</strong> <?= htmlspecialchars($entry['created_by'] ?? ''); ?></p>
            </div>
            <div class="card">
                <h3>Movement History</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Date/Time</th><th>From</th><th>To</th><th>Action</th><th>Remark</th></tr></thead>
                        <tbody>
                            <?php if (empty($entry['movements'])): ?>
                                <tr><td colspan="5">No movements logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($entry['movements'] as $move): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(format_date_for_display($move['timestamp'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars($move['from_user'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($move['to_user'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($move['action'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($move['remark'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <h3>Route Overview</h3>
                <?php if (empty($entry['route']['nodes'])): ?>
                    <div class="alert info">Route not initialized. Initialize to start guided movement.</div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="file_flow_action" value="init_route">
                        <label>Route Template ID (optional)
                            <input type="text" name="route_template_id" placeholder="ROUTE-001">
                        </label>
                        <button class="btn primary" type="submit">Initialize Route</button>
                    </form>
                <?php else: ?>
                    <ol class="route-list">
                        <?php foreach ($entry['route']['nodes'] as $idx => $node): ?>
                            <?php
                            $pos = null;
                            foreach ($positionsList as $p) { if (($p['id'] ?? '') === ($node['position_id'] ?? '')) { $pos = $p; break; } }
                            $isCurrent = ($entry['route']['current_node_index'] === $idx);
                            ?>
                            <li class="<?= $isCurrent ? 'active' : (($node['status'] ?? '') === 'completed' ? 'muted' : ''); ?>">
                                <strong><?= htmlspecialchars($pos['title'] ?? ($node['position_id'] ?? '')); ?></strong>
                                <?php if (!empty($node['user_username'])): ?>
                                    <div class="muted">User: <?= htmlspecialchars($node['user_username']); ?></div>
                                <?php endif; ?>
                                <div>Status: <?= htmlspecialchars($node['status'] ?? 'pending'); ?></div>
                                <?php if (!empty($node['completed_at'])): ?>
                                    <div class="muted">Completed at <?= htmlspecialchars(format_date_for_display($node['completed_at'])); ?></div>
                                <?php endif; ?>
                            <?php if ($isCurrent): ?>
                                <div class="badge">Current</div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if (!empty($routeSuggestions)): ?>
                        <div class="alert info">
                            <strong>Suggested next positions</strong>
                            <ul>
                                <?php foreach ($routeSuggestions as $s): ?>
                                    <li>
                                        <?= htmlspecialchars($s['position_id'] ?? ''); ?>
                                        <?php if (!empty($s['label'])): ?>
                                            <span class="badge"><?= htmlspecialchars($s['label']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($s['score'])): ?>
                                            <span class="muted">Score: <?= htmlspecialchars((string) $s['score']); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($availableRouteActions)): ?>
                        <form method="post" class="form-grid" style="margin-top:1rem;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <div class="form-field">
                                <label>Remarks
                                    <input type="text" name="remarks" placeholder="Optional">
                                </label>
                            </div>
                            <div class="form-actions" style="gap:0.5rem;">
                                <?php if (in_array('forward', $availableRouteActions, true)): ?>
                                    <button class="btn primary" type="submit" name="file_flow_action" value="forward">Forward to Next Step</button>
                                <?php endif; ?>
                                <?php if (in_array('return', $availableRouteActions, true)): ?>
                                    <select name="target_index">
                                        <?php foreach ($entry['route']['nodes'] as $idx => $node): if ($idx >= ($entry['route']['current_node_index'] ?? 0)) { continue; } ?>
                                            <option value="<?= (int)$idx; ?>">Return to <?= htmlspecialchars($node['position_id'] ?? ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn" type="submit" name="file_flow_action" value="return">Send Back</button>
                                <?php endif; ?>
                            </div>
                            <?php if (in_array('reroute', $availableRouteActions, true)): ?>
                                <label>Reroute to Position
                                    <select name="new_position_id">
                                        <option value="">Select position</option>
                                        <?php foreach ($positionsList as $pos): ?>
                                            <option value="<?= htmlspecialchars($pos['id'] ?? ''); ?>"><?= htmlspecialchars($pos['title'] ?? $pos['id']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="btn" type="submit" name="file_flow_action" value="reroute">Reroute</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($entry['route']['history'])): ?>
                        <h4>Route History</h4>
                        <table class="table">
                            <thead><tr><th>Timestamp</th><th>Action</th><th>From</th><th>To</th><th>User</th><th>Acceptance</th><th>Remarks</th></tr></thead>
                            <tbody>
                                <?php foreach ($entry['route']['history'] as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(format_date_for_display($row['timestamp'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars($row['action'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($row['from_position_id'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($row['to_position_id'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($row['user'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($row['acceptance']['status'] ?? 'accepted'); ?></td>
                                        <td><?= htmlspecialchars($row['remarks'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
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
        <?php if (!empty($entry['pending_acceptance'])): ?>
            <div class="alert info">Awaiting acceptance by <?= htmlspecialchars($entry['current_holder'] ?? ''); ?>.</div>
        <?php else: ?>
            <?php $lastHistory = !empty($entry['route']['history']) ? end($entry['route']['history']) : null; ?>
            <?php if ($lastHistory && !empty($lastHistory['acceptance']['accepted_by'])): ?>
                <div class="alert success">Accepted by <?= htmlspecialchars($lastHistory['acceptance']['accepted_by']); ?> on <?= htmlspecialchars(format_date_for_display($lastHistory['acceptance']['accepted_at'] ?? '')); ?></div>
            <?php endif; ?>
        <?php endif; ?>
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
    $userDak = $visibleEntries;
    ?>
    <div class="actions">
        <?php if ($canManageDak && !$officeReadOnly): ?>
            <a class="btn primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=create">Add New Dak</a>
        <?php elseif ($officeReadOnly): ?>
            <span class="muted">Creation disabled - license expired.</span>
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
