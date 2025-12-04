<?php
require_login();
if (!user_has_permission('view_mis_reports')) {
    require_permission('view_all_records');
}

$officeConfig = load_office_config();
$dateFormat = $officeConfig['date_format_php'] ?? 'Y-m-d';
$departments = load_departments();
$users = load_users();

$defaultFrom = date('Y-m-d', strtotime('-30 days'));
$defaultTo = date('Y-m-d');
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : $defaultFrom;
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : $defaultTo;
$fromDate = $fromDate === '' ? null : $fromDate;
$toDate = $toDate === '' ? null : $toDate;
$departmentFilter = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$departmentFilter = $departmentFilter === '' ? null : $departmentFilter;
$userFilter = isset($_GET['user']) ? trim($_GET['user']) : '';
$userFilter = $userFilter === '' ? null : $userFilter;
$report = $_GET['report'] ?? 'overview';
$allowedReports = ['overview', 'rti', 'dak', 'inspection', 'documents', 'bills'];
if (!in_array($report, $allowedReports, true)) {
    $report = 'overview';
}
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

function mis_apply_filters(array $items, array $dateFields, ?string $fromDate, ?string $toDate, ?string $departmentId, ?string $username, array $userFields): array
{
    $items = mis_filter_by_date($items, $dateFields, $fromDate, $toDate);
    $items = mis_filter_by_department($items, $departmentId);
    $items = mis_filter_by_user($items, $username, $userFields);
    return $items;
}

$rtiCases = load_rti_cases();
$dakEntries = load_dak_entries();
$inspectionReports = load_inspection_reports();
$meetingMinutes = load_document_records('meeting_minutes');
$workOrders = load_document_records('work_order');
$gucRecords = load_document_records('guc');
$allBills = load_bills();

$documentRecords = [];
foreach ($meetingMinutes as $rec) {
    $rec['category_label'] = 'Meeting Minutes';
    $documentRecords[] = $rec;
}
foreach ($workOrders as $rec) {
    $rec['category_label'] = 'Work Order';
    $documentRecords[] = $rec;
}
foreach ($gucRecords as $rec) {
    $rec['category_label'] = 'GUC';
    $documentRecords[] = $rec;
}

$rtiFiltered = mis_apply_filters($rtiCases, ['created_at', 'date_of_receipt'], $fromDate, $toDate, $departmentFilter, $userFilter, ['created_by', 'assigned_to']);
$dakFiltered = mis_apply_filters($dakEntries, ['date_received', 'created_at'], $fromDate, $toDate, $departmentFilter, $userFilter, ['assigned_to', 'created_by']);
$inspectionFiltered = mis_apply_filters($inspectionReports, ['fields.date_of_inspection', 'created_at'], $fromDate, $toDate, $departmentFilter, $userFilter, ['created_by']);
$documentFiltered = mis_apply_filters($documentRecords, ['created_at'], $fromDate, $toDate, $departmentFilter, $userFilter, ['created_by']);
$billsFiltered = mis_apply_filters($allBills, ['bill_date', 'created_at'], $fromDate, $toDate, $departmentFilter, $userFilter, ['created_by']);
$documentCategoryCounts = mis_group_count($documentFiltered, 'category_label');

$rtiOverdue = array_filter($rtiFiltered, 'is_rti_overdue');
$dakOverdue = array_filter($dakFiltered, 'is_dak_overdue');
$pendingRti = array_filter($rtiFiltered, function ($case) { return ($case['status'] ?? '') === 'Pending'; });
$pendingDak = array_filter($dakFiltered, function ($dak) { return ($dak['status'] ?? '') !== 'Closed'; });
$openInspections = array_filter($inspectionFiltered, function ($rep) { return strtolower($rep['status'] ?? '') !== 'closed'; });
$closedInspections = array_filter($inspectionFiltered, function ($rep) { return strtolower($rep['status'] ?? '') === 'closed'; });
$billSubTotal = mis_sum_field($billsFiltered, 'sub_total');
$billNet = mis_sum_field($billsFiltered, 'net_payable');

if ($exportCsv) {
    $filename = 'yojaka_report_' . $report . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    switch ($report) {
        case 'rti':
            fputcsv($output, ['ID', 'Reference #', 'Applicant', 'Subject', 'Date of Receipt', 'Reply Deadline', 'Status', 'Assigned To', 'Created By']);
            foreach ($rtiFiltered as $case) {
                fputcsv($output, [
                    $case['id'] ?? '',
                    $case['reference_number'] ?? '',
                    $case['applicant_name'] ?? '',
                    $case['subject'] ?? '',
                    $case['date_of_receipt'] ?? '',
                    $case['reply_deadline'] ?? '',
                    $case['status'] ?? '',
                    $case['assigned_to'] ?? '',
                    $case['created_by'] ?? '',
                ]);
            }
            break;
        case 'dak':
            fputcsv($output, ['ID', 'Reference #', 'Received From', 'Subject', 'Date Received', 'Status', 'Assigned To', 'Overdue']);
            foreach ($dakFiltered as $entry) {
                fputcsv($output, [
                    $entry['id'] ?? '',
                    $entry['reference_number'] ?? '',
                    $entry['received_from'] ?? '',
                    $entry['subject'] ?? '',
                    $entry['date_received'] ?? '',
                    $entry['status'] ?? '',
                    $entry['assigned_to'] ?? '',
                    is_dak_overdue($entry) ? 'Yes' : 'No',
                ]);
            }
            break;
        case 'inspection':
            fputcsv($output, ['Report ID', 'Template', 'Date of Inspection', 'Status', 'Created By']);
            foreach ($inspectionFiltered as $rep) {
                fputcsv($output, [
                    $rep['id'] ?? '',
                    $rep['template_name'] ?? '',
                    mis_get_value($rep, 'fields.date_of_inspection') ?? '',
                    $rep['status'] ?? '',
                    $rep['created_by'] ?? '',
                ]);
            }
            break;
        case 'documents':
            fputcsv($output, ['Record ID', 'Category', 'Template', 'Created By', 'Created At']);
            foreach ($documentFiltered as $doc) {
                fputcsv($output, [
                    $doc['id'] ?? '',
                    $doc['category_label'] ?? '',
                    $doc['template_name'] ?? '',
                    $doc['created_by'] ?? '',
                    $doc['created_at'] ?? '',
                ]);
            }
            break;
        case 'bills':
            fputcsv($output, ['Bill ID', 'Bill No', 'Bill Date', 'Contractor', 'Work Description', 'Sub Total', 'Net Payable', 'Created By']);
            foreach ($billsFiltered as $bill) {
                fputcsv($output, [
                    $bill['id'] ?? '',
                    $bill['bill_no'] ?? '',
                    $bill['bill_date'] ?? '',
                    $bill['contractor_name'] ?? '',
                    $bill['work_description'] ?? '',
                    $bill['sub_total'] ?? '',
                    $bill['net_payable'] ?? '',
                    $bill['created_by'] ?? '',
                ]);
            }
            break;
        default:
            fputcsv($output, ['Module', 'Metric', 'Value']);
            fputcsv($output, ['RTI', 'Total', count($rtiFiltered)]);
            fputcsv($output, ['Dak', 'Total', count($dakFiltered)]);
            fputcsv($output, ['Inspection', 'Total', count($inspectionFiltered)]);
            fputcsv($output, ['Documents', 'Total', count($documentFiltered)]);
            fputcsv($output, ['Bills', 'Total', count($billsFiltered)]);
            break;
    }
    fclose($output);
    exit;
}

function mis_query(array $params): string
{
    $query = array_merge($_GET, $params);
    foreach ($query as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        }
    }
    return http_build_query($query);
}

function mis_render_status_counts(array $counts): string
{
    if (empty($counts)) {
        return '<em>No data</em>';
    }
    $html = '<ul class="pill-list">';
    foreach ($counts as $status => $count) {
        $html .= '<li><span class="badge">' . htmlspecialchars($status) . '</span> ' . (int) $count . '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>

<div class="filter-bar">
    <form method="get" action="<?= YOJAKA_BASE_URL; ?>/app.php">
        <input type="hidden" name="page" value="admin_mis">
        <input type="hidden" name="report" value="<?= htmlspecialchars($report); ?>">
        <div class="form-field">
            <label>From Date</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate ?? ''); ?>">
        </div>
        <div class="form-field">
            <label>To Date</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($toDate ?? ''); ?>">
        </div>
        <div class="form-field">
            <label>Department</label>
            <select name="department_id">
                <option value="">All</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['id'] ?? ''); ?>" <?= ($departmentFilter === ($dept['id'] ?? '')) ? 'selected' : ''; ?>><?= htmlspecialchars($dept['name'] ?? ''); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>User</label>
            <select name="user">
                <option value="">All</option>
                <?php foreach ($users as $usr): ?>
                    <option value="<?= htmlspecialchars($usr['username'] ?? ''); ?>" <?= ($userFilter === ($usr['username'] ?? '')) ? 'selected' : ''; ?>><?= htmlspecialchars(($usr['full_name'] ?? '') . ' (' . ($usr['username'] ?? '') . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary">Apply Filters</button>
    </form>
</div>

<div class="tab-bar">
    <?php foreach ($allowedReports as $slug): ?>
        <?php $isActive = $report === $slug; ?>
        <a class="tab<?= $isActive ? ' active' : ''; ?>" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= mis_query(['report' => $slug, 'export' => null]); ?>"><?= ucfirst($slug); ?></a>
    <?php endforeach; ?>
</div>

<?php if ($report === 'overview'): ?>
    <div class="grid stats">
        <div class="card stat">
            <div class="stat-label">RTI Cases</div>
            <div class="stat-value"><?= count($rtiFiltered); ?></div>
            <div class="stat-sub">Pending: <?= count($pendingRti); ?> | Overdue: <?= count($rtiOverdue); ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">Dak Entries</div>
            <div class="stat-value"><?= count($dakFiltered); ?></div>
            <div class="stat-sub">Pending: <?= count($pendingDak); ?> | Overdue: <?= count($dakOverdue); ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">Inspection Reports</div>
            <div class="stat-value"><?= count($inspectionFiltered); ?></div>
            <div class="stat-sub">Open: <?= count($openInspections); ?> | Closed: <?= count($closedInspections); ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">Documents</div>
            <div class="stat-value"><?= count($documentFiltered); ?></div>
            <div class="stat-sub">Minutes: <?= (int) ($documentCategoryCounts['Meeting Minutes'] ?? 0); ?> | WOs: <?= (int) ($documentCategoryCounts['Work Order'] ?? 0); ?> | GUC: <?= (int) ($documentCategoryCounts['GUC'] ?? 0); ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">Bills</div>
            <div class="stat-value"><?= count($billsFiltered); ?></div>
            <div class="stat-sub">Sub Total: ₹<?= number_format($billSubTotal, 2); ?> | Net: ₹<?= number_format($billNet, 2); ?></div>
        </div>
    </div>
    <div class="info">Download CSV for module-specific views to get detailed records.</div>
<?php elseif ($report === 'rti'): ?>
    <div class="actions" style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 10px;">
        <h3>RTI Report</h3>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= mis_query(['export' => 'csv']); ?>">Download CSV</a>
    </div>
    <div class="grid stats">
        <div class="card stat">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?= count($rtiFiltered); ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">Overdue</div>
            <div class="stat-value warn"><?= count($rtiOverdue); ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">Status Mix</div>
            <div class="stat-value small"><?= mis_render_status_counts(mis_count_by_status($rtiFiltered, 'status')); ?></div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th><th>Reference #</th><th>Applicant</th><th>Subject</th><th>Receipt</th><th>Deadline</th><th>Status</th><th>Assigned To</th><th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rtiFiltered)): ?>
                    <tr><td colspan="9">No RTI cases match the filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($rtiFiltered as $case): ?>
                        <?php $overdue = is_rti_overdue($case); ?>
                        <tr>
                            <td><?= htmlspecialchars($case['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['reference_number'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['applicant_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['subject'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(mis_format_date_for_display($case['date_of_receipt'] ?? '', $dateFormat)); ?></td>
                            <td><?= htmlspecialchars(mis_format_date_for_display($case['reply_deadline'] ?? '', $dateFormat)); ?><?= $overdue ? ' <span class="badge badge-danger">Overdue</span>' : ''; ?></td>
                            <td><span class="badge"><?= htmlspecialchars($case['status'] ?? ''); ?></span></td>
                            <td><?= htmlspecialchars($case['assigned_to'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($case['created_by'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($report === 'dak'): ?>
    <div class="actions" style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 10px;">
        <h3>Dak Report</h3>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= mis_query(['export' => 'csv']); ?>">Download CSV</a>
    </div>
    <div class="grid stats">
        <div class="card stat"><div class="stat-label">Total</div><div class="stat-value"><?= count($dakFiltered); ?></div></div>
        <div class="card stat"><div class="stat-label">Overdue</div><div class="stat-value warn"><?= count($dakOverdue); ?></div></div>
        <div class="card stat"><div class="stat-label">Status Mix</div><div class="stat-value small"><?= mis_render_status_counts(mis_count_by_status($dakFiltered, 'status')); ?></div></div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Reference</th><th>From</th><th>Subject</th><th>Date Received</th><th>Status</th><th>Assigned To</th><th>Overdue?</th></tr>
            </thead>
            <tbody>
                <?php if (empty($dakFiltered)): ?>
                    <tr><td colspan="8">No Dak entries found.</td></tr>
                <?php else: ?>
                    <?php foreach ($dakFiltered as $entry): ?>
                        <?php $overdue = is_dak_overdue($entry); ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($entry['reference_number'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($entry['received_from'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($entry['subject'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(mis_format_date_for_display($entry['date_received'] ?? '', $dateFormat)); ?></td>
                            <td><span class="badge"><?= htmlspecialchars($entry['status'] ?? ''); ?></span></td>
                            <td><?= htmlspecialchars($entry['assigned_to'] ?? ''); ?></td>
                            <td><?= $overdue ? '<span class="badge badge-danger">Yes</span>' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($report === 'inspection'): ?>
    <div class="actions" style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 10px;">
        <h3>Inspection Report</h3>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= mis_query(['export' => 'csv']); ?>">Download CSV</a>
    </div>
    <div class="grid stats">
        <div class="card stat"><div class="stat-label">Total</div><div class="stat-value"><?= count($inspectionFiltered); ?></div></div>
        <div class="card stat"><div class="stat-label">By Template</div><div class="stat-value small"><?= mis_render_status_counts(mis_group_count($inspectionFiltered, 'template_name')); ?></div></div>
        <div class="card stat"><div class="stat-label">Status Mix</div><div class="stat-value small"><?= mis_render_status_counts(mis_count_by_status($inspectionFiltered, 'status')); ?></div></div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Template</th><th>Date</th><th>Status</th><th>Created By</th></tr></thead>
            <tbody>
                <?php if (empty($inspectionFiltered)): ?>
                    <tr><td colspan="5">No inspection reports found.</td></tr>
                <?php else: ?>
                    <?php foreach ($inspectionFiltered as $rep): ?>
                        <tr>
                            <td><?= htmlspecialchars($rep['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($rep['template_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(mis_format_date_for_display(mis_get_value($rep, 'fields.date_of_inspection'), $dateFormat)); ?></td>
                            <td><span class="badge"><?= htmlspecialchars($rep['status'] ?? ''); ?></span></td>
                            <td><?= htmlspecialchars($rep['created_by'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($report === 'documents'): ?>
    <div class="actions" style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 10px;">
        <h3>Documents Report (Minutes, WOs, GUC)</h3>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= mis_query(['export' => 'csv']); ?>">Download CSV</a>
    </div>
    <div class="grid stats">
        <div class="card stat"><div class="stat-label">Total</div><div class="stat-value"><?= count($documentFiltered); ?></div></div>
        <div class="card stat"><div class="stat-label">By Category</div><div class="stat-value small"><?= mis_render_status_counts(mis_group_count($documentFiltered, 'category_label')); ?></div></div>
        <div class="card stat"><div class="stat-label">By Template</div><div class="stat-value small"><?= mis_render_status_counts(mis_group_count($documentFiltered, 'template_name')); ?></div></div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Category</th><th>Template</th><th>Created By</th><th>Created At</th></tr></thead>
            <tbody>
                <?php if (empty($documentFiltered)): ?>
                    <tr><td colspan="5">No document records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($documentFiltered as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($doc['category_label'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($doc['template_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($doc['created_by'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(mis_format_date_for_display($doc['created_at'] ?? '', $dateFormat)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($report === 'bills'): ?>
    <div class="actions" style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 10px;">
        <h3>Bills Report</h3>
        <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?<?= mis_query(['export' => 'csv']); ?>">Download CSV</a>
    </div>
    <div class="grid stats">
        <div class="card stat"><div class="stat-label">Total Bills</div><div class="stat-value"><?= count($billsFiltered); ?></div></div>
        <div class="card stat"><div class="stat-label">Total Sub Total</div><div class="stat-value">₹<?= number_format($billSubTotal, 2); ?></div></div>
        <div class="card stat"><div class="stat-label">Total Net Payable</div><div class="stat-value">₹<?= number_format($billNet, 2); ?></div></div>
        <div class="card stat"><div class="stat-label">Top Contractors</div><div class="stat-value small"><?= mis_render_status_counts(mis_group_count($billsFiltered, 'contractor_name')); ?></div></div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>ID</th><th>Bill No</th><th>Bill Date</th><th>Contractor</th><th>Description</th><th>Sub Total</th><th>Net Payable</th><th>Created By</th></tr></thead>
            <tbody>
                <?php if (empty($billsFiltered)): ?>
                    <tr><td colspan="8">No bills found.</td></tr>
                <?php else: ?>
                    <?php foreach ($billsFiltered as $bill): ?>
                        <tr>
                            <td><?= htmlspecialchars($bill['id'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($bill['bill_no'] ?? ''); ?></td>
                            <td><?= htmlspecialchars(mis_format_date_for_display($bill['bill_date'] ?? '', $dateFormat)); ?></td>
                            <td><?= htmlspecialchars($bill['contractor_name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($bill['work_description'] ?? ''); ?></td>
                            <td>₹<?= number_format((float) ($bill['sub_total'] ?? 0), 2); ?></td>
                            <td>₹<?= number_format((float) ($bill['net_payable'] ?? 0), 2); ?></td>
                            <td><?= htmlspecialchars($bill['created_by'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
