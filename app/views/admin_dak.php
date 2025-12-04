<?php
require_permission('manage_dak');

$mode = $_GET['mode'] ?? 'list';
$entries = load_dak_entries();
$users = load_users();
$targetId = $_GET['id'] ?? null;
$statusOptions = dak_statuses();
$errors = [];

if ($mode === 'view' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $dakId = $_POST['dak_id'] ?? '';

    if ($dakId === '') {
        $errors[] = 'Missing Dak ID.';
    } else {
        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            if (!in_array($newStatus, $statusOptions, true)) {
                $errors[] = 'Invalid status selected.';
            } else {
                if (update_dak_status($entries, $dakId, $newStatus)) {
                    save_dak_entries($entries);
                    log_event('dak_status_updated', $_SESSION['username'] ?? null, ['dak_id' => $dakId, 'status' => $newStatus]);
                } else {
                    $errors[] = 'Unable to update status.';
                }
            }
        } elseif ($action === 'assign') {
            $assignedTo = $_POST['assigned_to'] ?? '';
            if ($assignedTo === '') {
                $errors[] = 'Please select a user to assign.';
            } else {
                if (assign_dak_to_user($entries, $dakId, $assignedTo)) {
                    save_dak_entries($entries);
                    log_event('dak_assigned', $_SESSION['username'] ?? null, ['dak_id' => $dakId, 'assigned_to' => $assignedTo]);
                } else {
                    $errors[] = 'Unable to assign Dak.';
                }
            }
        } elseif ($action === 'forward') {
            $forwardTo = $_POST['forward_to'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');
            if ($forwardTo === '') {
                $errors[] = 'Please select a user to forward to.';
            } else {
                $fromUser = $_SESSION['username'] ?? null;
                if (forward_dak($entries, $dakId, $fromUser, $forwardTo, $remarks)) {
                    save_dak_entries($entries);
                    log_event('dak_forwarded', $_SESSION['username'] ?? null, ['dak_id' => $dakId, 'forward_to' => $forwardTo]);
                } else {
                    $errors[] = 'Unable to forward Dak.';
                }
            }
        }
    }

    if (empty($errors)) {
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=admin_dak&mode=view&id=' . urlencode($dakId));
        exit;
    }
}
?>

<?php if ($mode === 'view' && $targetId): ?>
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
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_dak">Back to list</a>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?= htmlspecialchars(implode(' ', $errors)); ?>
            </div>
        <?php endif; ?>
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
                <h3>Admin Actions</h3>
                <form method="post" class="stacked">
                    <input type="hidden" name="dak_id" value="<?= htmlspecialchars($entry['id']); ?>">
                    <label>Change Status
                        <select name="status" required>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= htmlspecialchars($status); ?>" <?= ($entry['status'] ?? '') === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" name="action" value="update_status" class="btn primary">Update Status</button>
                </form>

                <form method="post" class="stacked">
                    <input type="hidden" name="dak_id" value="<?= htmlspecialchars($entry['id']); ?>">
                    <label>Assign / Reassign
                        <select name="assigned_to" required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= htmlspecialchars($u['username']); ?>" <?= ($entry['assigned_to'] ?? '') === ($u['username'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($u['full_name'] ?? $u['username']); ?> (<?= htmlspecialchars($u['username']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" name="action" value="assign" class="btn">Assign</button>
                </form>

                <form method="post" class="stacked">
                    <input type="hidden" name="dak_id" value="<?= htmlspecialchars($entry['id']); ?>">
                    <label>Forward To
                        <select name="forward_to" required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= htmlspecialchars($u['username']); ?>"><?= htmlspecialchars($u['full_name'] ?? $u['username']); ?> (<?= htmlspecialchars($u['username']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Remarks
                        <textarea name="remarks" rows="3" placeholder="Optional remarks"></textarea>
                    </label>
                    <button type="submit" name="action" value="forward" class="btn">Forward</button>
                </form>
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
                    <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_dak">Back to list</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php
    $filterStatus = $_GET['status'] ?? '';
    $filterUser = $_GET['assigned_to'] ?? '';

    $filtered = array_filter($entries, function ($entry) use ($filterStatus, $filterUser) {
        $statusMatch = $filterStatus === '' || ($entry['status'] ?? '') === $filterStatus;
        $userMatch = $filterUser === '' || ($entry['assigned_to'] ?? '') === $filterUser;
        return $statusMatch && $userMatch;
    });
    ?>
    <form method="get" class="filters">
        <input type="hidden" name="page" value="admin_dak">
        <input type="hidden" name="mode" value="list">
        <label>Status
            <select name="status">
                <option value="">All</option>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?= htmlspecialchars($status); ?>" <?= $filterStatus === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Assigned User
            <select name="assigned_to">
                <option value="">All</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= htmlspecialchars($u['username']); ?>" <?= $filterUser === ($u['username'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars($u['full_name'] ?? $u['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn">Filter</button>
    </form>

    <?php if (empty($filtered)): ?>
        <p>No dak entries found.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Date Received</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Overdue</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered as $entry): ?>
                    <?php $overdue = is_dak_overdue($entry); ?>
                    <tr class="<?= $overdue ? 'warn' : ''; ?>">
                        <td><?= htmlspecialchars($entry['id']); ?></td>
                        <td><?= htmlspecialchars($entry['received_from']); ?></td>
                        <td><?= htmlspecialchars($entry['subject']); ?></td>
                        <td><?= htmlspecialchars($entry['date_received']); ?></td>
                        <td><?= htmlspecialchars($entry['status']); ?></td>
                        <td><?= htmlspecialchars($entry['assigned_to'] ?? 'Unassigned'); ?></td>
                        <td><?= $overdue ? '<span class="badge warn">Overdue</span>' : '-'; ?></td>
                        <td><a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_dak&mode=view&id=<?= urlencode($entry['id']); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>
