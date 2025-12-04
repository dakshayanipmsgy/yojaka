<?php
$user = current_user();
$mode = $_GET['mode'] ?? 'list';
$entries = load_dak_entries();
$errors = [];

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
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php
    $userDak = [];
    foreach ($entries as $entry) {
        if (($entry['assigned_to'] ?? null) === ($user['username'] ?? null) || ($entry['created_by'] ?? null) === ($user['username'] ?? null)) {
            $userDak[] = $entry;
        }
    }
    ?>
    <div class="actions">
        <a class="btn primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=create">Add New Dak</a>
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
