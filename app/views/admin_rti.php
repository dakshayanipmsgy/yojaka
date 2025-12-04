<?php
require_permission('manage_rti');

$statuses = ['Pending', 'Replied', 'Closed'];
$cases = load_rti_cases();
$selectedStatus = $_GET['status'] ?? '';
$filterStatus = in_array($selectedStatus, $statuses, true) ? $selectedStatus : '';
$errors = [];
$success = '';
$pagination = null;

$searchTerm = trim($_GET['q'] ?? '');

$csrfToken = $_SESSION['admin_rti_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['admin_rti_csrf'] = $csrfToken;

function sanitize_admin_field($value): string
{
    return trim((string) $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['admin_rti_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $rtiId = sanitize_admin_field($_POST['id'] ?? '');
    $newStatus = sanitize_admin_field($_POST['status'] ?? '');
    $replyDate = sanitize_admin_field($_POST['reply_date'] ?? '');
    $replySummary = sanitize_admin_field($_POST['reply_summary'] ?? '');

    if ($rtiId === '') {
        $errors[] = 'RTI ID is required.';
    }
    if (!in_array($newStatus, $statuses, true)) {
        $errors[] = 'Invalid status.';
    }

    $case = $rtiId ? find_rti_by_id($cases, $rtiId) : null;
    if (!$case) {
        $errors[] = 'RTI case not found.';
    }

    if (empty($errors)) {
        $case['status'] = $newStatus;
        $case['reply_date'] = $replyDate !== '' ? $replyDate : null;
        $case['reply_summary'] = $replySummary !== '' ? $replySummary : null;
        $case['updated_at'] = gmdate('c');
        update_rti_case($cases, $case);
        save_rti_cases($cases);
        log_event('rti_status_updated', $_SESSION['username'] ?? null, [
            'rti_id' => $case['id'],
            'status' => $case['status'],
            'reply_date' => $case['reply_date'],
        ]);
        $success = 'RTI status updated successfully.';
    }
}

if ($filterStatus !== '') {
    $filteredCases = array_filter($cases, function ($case) use ($filterStatus) {
        return ($case['status'] ?? '') === $filterStatus;
    });
} else {
    $filteredCases = $cases;
}

$filteredCases = filter_items_search($filteredCases, $searchTerm, ['reference_number', 'applicant_name', 'subject']);

$perPage = $config['pagination_per_page'] ?? 10;
$pageParam = 'p';
$pagination = paginate_array(array_values($filteredCases), get_page_param($pageParam), $perPage);
$filteredCases = $pagination['items'];

$viewId = $_GET['id'] ?? '';
$viewCase = $viewId ? find_rti_by_id($cases, $viewId) : null;
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

<?php if ($success): ?>
    <div class="alert info"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="filter-bar">
    <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php" class="form-inline">
        <input type="hidden" name="page" value="admin_rti">
        <div class="form-field">
            <input type="text" name="q" placeholder="Search reference, applicant, subject" value="<?= htmlspecialchars($searchTerm); ?>">
        </div>
        <label for="status">Filter by Status:</label>
        <select name="status" id="status" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= htmlspecialchars($status); ?>" <?= $filterStatus === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Apply</button>
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
                <th>Assigned To</th>
                <th>Created By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filteredCases)): ?>
                <tr><td colspan="10">No RTI cases match this filter.</td></tr>
            <?php else: ?>
                <?php foreach ($filteredCases as $case): ?>
                    <?php $overdue = is_rti_overdue($case); ?>
                    <tr>
                        <td><?= htmlspecialchars($case['id']); ?></td>
                        <td><?= htmlspecialchars($case['reference_number'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($case['applicant_name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($case['subject'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($case['date_of_receipt'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($case['reply_deadline'] ?? ''); ?><?= $overdue ? ' <span class="badge badge-danger">Overdue</span>' : ''; ?></td>
                        <td><span class="badge <?= $overdue ? 'badge-danger' : 'badge-soft'; ?>"><?= htmlspecialchars($case['status'] ?? ''); ?></span></td>
                        <td><?= htmlspecialchars($case['assigned_to'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($case['created_by'] ?? ''); ?></td>
                        <td><a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_rti&id=<?= urlencode($case['id']); ?>">View / Update</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pagination): ?>
    <?php
    $queryBase = [
        'page' => 'admin_rti',
        'q' => $searchTerm,
        'status' => $filterStatus,
    ];
    ?>
    <div class="pagination">
        <span>Page <?= (int) $pagination['page']; ?> of <?= (int) $pagination['total_pages']; ?></span>
        <div class="pager-links">
            <?php if ($pagination['page'] > 1): ?>
                <?php $prevQuery = http_build_query(array_merge($queryBase, ['p' => $pagination['page'] - 1])); ?>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= $prevQuery; ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <?php $nextQuery = http_build_query(array_merge($queryBase, ['p' => $pagination['page'] + 1])); ?>
                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= $nextQuery; ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($viewCase): ?>
    <div class="card" style="margin-top:1.5rem;">
        <h3>RTI Details</h3>
        <div class="detail-grid">
            <div>
                <div class="muted">RTI ID</div>
                <div class="strong"><?= htmlspecialchars($viewCase['id']); ?></div>
            </div>
            <div>
                <div class="muted">Reference Number</div>
                <div><?= htmlspecialchars($viewCase['reference_number'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Applicant</div>
                <div><?= htmlspecialchars($viewCase['applicant_name'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Subject</div>
                <div><?= htmlspecialchars($viewCase['subject'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Date of Receipt</div>
                <div><?= htmlspecialchars($viewCase['date_of_receipt'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Reply Deadline</div>
                <div><?= htmlspecialchars($viewCase['reply_deadline'] ?? ''); ?>
                    <?php if (is_rti_overdue($viewCase)): ?>
                        <span class="badge badge-danger">Overdue</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="muted">Status</div>
                <div><span class="badge badge-soft"><?= htmlspecialchars($viewCase['status'] ?? ''); ?></span></div>
            </div>
            <div>
                <div class="muted">Assigned To</div>
                <div><?= htmlspecialchars($viewCase['assigned_to'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Created By</div>
                <div><?= htmlspecialchars($viewCase['created_by'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Created At</div>
                <div><?= htmlspecialchars($viewCase['created_at'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Updated At</div>
                <div><?= htmlspecialchars($viewCase['updated_at'] ?? ''); ?></div>
            </div>
        </div>
        <div class="form-stacked" style="margin-top:1rem;">
            <h4>Update Status</h4>
            <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_rti&id=<?= urlencode($viewCase['id']); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?= htmlspecialchars($viewCase['id']); ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <div class="form-field">
                    <label for="status_update">Status</label>
                    <select name="status" id="status_update" required>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status); ?>" <?= ($viewCase['status'] ?? '') === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="reply_date">Reply Date</label>
                    <input type="date" id="reply_date" name="reply_date" value="<?= htmlspecialchars($viewCase['reply_date'] ?? ''); ?>">
                </div>
                <div class="form-field">
                    <label for="reply_summary">Reply Summary</label>
                    <textarea id="reply_summary" name="reply_summary" rows="3"><?= htmlspecialchars($viewCase['reply_summary'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button class="btn-primary" type="submit">Save Changes</button>
                    <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_rti">Back to list</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
