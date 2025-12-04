<?php
require_login();

$user = current_user();
$cases = load_rti_cases();
$mode = $_GET['mode'] ?? 'list';
$mode = in_array($mode, ['list', 'create', 'view'], true) ? $mode : 'list';
$errors = [];
$notice = '';

$csrfToken = $_SESSION['rti_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['rti_csrf_token'] = $csrfToken;

function sanitize_field($value): string
{
    return trim((string) $value);
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

    if (empty($errors)) {
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
        ];
        $cases[] = $newCase;
        save_rti_cases($cases);
        log_event('rti_created', $user['username'] ?? null, [
            'rti_id' => $newCase['id'],
            'reference_number' => $referenceNumber,
        ]);
        header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=rti&mode=view&id=' . urlencode($newCase['id']));
        exit;
    }
}

if ($mode === 'view') {
    $id = $_GET['id'] ?? '';
    $case = $id ? find_rti_by_id($cases, $id) : null;
    if (!$case) {
        $errors[] = 'RTI case not found.';
    } elseif (($user['role'] ?? '') !== 'admin' && ($case['created_by'] ?? '') !== ($user['username'] ?? '')) {
        $errors[] = 'You are not allowed to view this RTI.';
        $case = null;
    }
}

if ($mode === 'list') {
    if (($user['role'] ?? '') === 'admin') {
        $visibleCases = $cases;
    } else {
        $visibleCases = array_filter($cases, function ($case) use ($user) {
            return ($case['created_by'] ?? null) === ($user['username'] ?? null);
        });
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

<?php if ($mode === 'list'): ?>
    <div class="actions" style="margin-bottom: 1rem; display:flex; justify-content: space-between; align-items: center; gap: 1rem;">
        <div>
            <strong>Your RTI cases</strong> <?= ($user['role'] ?? '') === 'admin' ? '(all cases shown for admin)' : ''; ?>
        </div>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti&mode=create">Create New RTI</a>
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
                            <td><?= htmlspecialchars($case['id']); ?></td>
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
        <div style="margin-top:1rem;">
            <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti">Back to list</a>
        </div>
    </div>
<?php endif; ?>
