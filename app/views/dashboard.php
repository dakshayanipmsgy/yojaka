<?php
$user = current_user();
$users = load_users();
$usageEntries = read_usage_logs(1000); // limit to recent entries for lightweight stats
$roleConfig = get_role_dashboard_config($_SESSION['role'] ?? 'user');
$widgetList = $roleConfig['widgets'] ?? ['my_pending_files'];

$totalUsers = count($users);
$totalLoginSuccess = count_events($usageEntries, 'login_success');
$totalLoginFailure = count_events($usageEntries, 'login_failure');
$userDashboardViews = count_events($usageEntries, 'dashboard_view', $user['username'] ?? null);
$totalLettersGenerated = count_events($usageEntries, 'letter_generated');
$userLettersGenerated = count_events($usageEntries, 'letter_generated', $user['username'] ?? null);

$rtiCases = load_rti_cases();
$dakEntries = load_dak_entries();
$inspectionReports = load_inspection_reports();
$meetingMinutesDocs = load_document_records('meeting_minutes');
$workOrdersDocs = load_document_records('work_order');
$gucDocs = load_document_records('guc');
$bills = load_bills();
$officeConfig = load_office_config();
$dashboardWidgets = get_office_dashboard_widgets();
run_sla_checks();

$yourPendingRtis = 0;
$yourOverdueRtis = 0;
$yourDakAssigned = 0;
$yourDakPending = 0;
$yourDakOverdue = 0;
$totalRtis = count($rtiCases);
$pendingRtis = 0;
$overdueRtis = 0;
$totalDak = count($dakEntries);
$pendingDak = 0;
$overdueDak = 0;
$unassignedDak = 0;
$yourInspections = 0;
$yourOpenInspections = 0;
$totalInspections = count($inspectionReports);
$openInspections = 0;
$closedInspections = 0;
$yourMeetingMinutes = 0;
$yourWorkOrders = 0;
$yourGucs = 0;
$totalMeetingMinutes = count($meetingMinutesDocs);
$totalWorkOrders = count($workOrdersDocs);
$totalGucs = count($gucDocs);
$totalBills = count($bills);
$yourBills = 0;
$todayOutbox = 0;

function dashboard_widget_visible(array $widgets, string $id): bool
{
    foreach ($widgets as $widget) {
        if (($widget['id'] ?? '') === $id) {
            return !empty($widget['visible']);
        }
    }
    return true;
}

foreach ($rtiCases as $case) {
    $isPending = ($case['status'] ?? '') === 'Pending';
    $isOverdue = is_rti_overdue($case);
    if (($case['created_by'] ?? null) === ($user['username'] ?? null)) {
        if ($isPending) {
            $yourPendingRtis++;
        }
        if ($isOverdue) {
            $yourOverdueRtis++;
        }
    }
    if ($isPending) {
        $pendingRtis++;
    }
    if ($isOverdue) {
        $overdueRtis++;
    }
}

foreach ($dakEntries as $entry) {
    $isAssignedToUser = ($entry['assigned_to'] ?? null) === ($user['username'] ?? null);
    $isClosed = ($entry['status'] ?? '') === 'Closed';
    $overdue = is_dak_overdue($entry);

    if (!empty($entry['movements'])) {
        foreach ($entry['movements'] as $movement) {
            if (!empty($movement['timestamp']) && substr($movement['timestamp'], 0, 10) === gmdate('Y-m-d')) {
                $todayOutbox++;
            }
        }
    }

    if ($isAssignedToUser) {
        $yourDakAssigned++;
        if (!$isClosed) {
            $yourDakPending++;
        }
        if ($overdue) {
            $yourDakOverdue++;
        }
    }

    if (!$isClosed) {
        $pendingDak++;
    }
    if ($overdue) {
        $overdueDak++;
    }
    if (empty($entry['assigned_to'])) {
        $unassignedDak++;
    }
}

foreach ($inspectionReports as $report) {
    $isClosed = ($report['status'] ?? '') === 'Closed';
    if (($report['created_by'] ?? null) === ($user['username'] ?? null)) {
        $yourInspections++;
        if (!$isClosed) {
            $yourOpenInspections++;
        }
    }

    if ($isClosed) {
        $closedInspections++;
    } else {
        $openInspections++;
    }
}

foreach ($meetingMinutesDocs as $doc) {
    if (($doc['created_by'] ?? null) === ($user['username'] ?? null)) {
        $yourMeetingMinutes++;
    }
}

foreach ($workOrdersDocs as $doc) {
    if (($doc['created_by'] ?? null) === ($user['username'] ?? null)) {
        $yourWorkOrders++;
    }
}

foreach ($gucDocs as $doc) {
    if (($doc['created_by'] ?? null) === ($user['username'] ?? null)) {
        $yourGucs++;
    }
}

foreach ($bills as $bill) {
    if (($bill['created_by'] ?? null) === ($user['username'] ?? null)) {
        $yourBills++;
    }
}

function render_dashboard_widget(string $id, array $ctx): void
{
    switch ($id) {
        case 'office_stats':
            ?>
            <div class="card highlight">
                <h2>Welcome <?= htmlspecialchars($ctx['user']['full_name'] ?? ''); ?></h2>
                <p>Role: <?= htmlspecialchars($ctx['user']['role'] ?? ''); ?> @ <?= htmlspecialchars($ctx['officeConfig']['office_name'] ?? ''); ?></p>
                <div class="muted">Timezone: <?= htmlspecialchars($ctx['officeConfig']['timezone'] ?? ''); ?></div>
            </div>
            <div class="card stat">
                <div class="stat-label">Registered Users</div>
                <div class="stat-value"><?= (int) $ctx['totalUsers']; ?></div>
            </div>
            <div class="card stat">
                <div class="stat-label">Login Success</div>
                <div class="stat-value"><?= (int) $ctx['totalLoginSuccess']; ?></div>
            </div>
            <div class="card stat">
                <div class="stat-label">Login Failures</div>
                <div class="stat-value warn"><?= (int) $ctx['totalLoginFailure']; ?></div>
            </div>
            <?php
            break;
        case 'pending_rti':
            ?>
            <div class="card stat">
                <div class="stat-label">Pending RTIs</div>
                <div class="stat-value"><?= (int) $ctx['pendingRtis']; ?></div>
                <div class="muted">Overdue: <?= (int) $ctx['overdueRtis']; ?></div>
            </div>
            <?php
            break;
        case 'pending_dak':
            ?>
            <div class="card stat">
                <div class="stat-label">Pending Dak (Office)</div>
                <div class="stat-value"><?= (int) $ctx['pendingDak']; ?></div>
                <div class="muted">Overdue: <?= (int) $ctx['overdueDak']; ?> | Unassigned: <?= (int) $ctx['unassignedDak']; ?></div>
            </div>
            <?php
            break;
        case 'overdue_bills':
            ?>
            <div class="card stat">
                <div class="stat-label">Bills Tracked</div>
                <div class="stat-value"><?= (int) $ctx['totalBills']; ?></div>
                <div class="muted">Your bills: <?= (int) $ctx['yourBills']; ?></div>
            </div>
            <?php
            break;
        case 'my_pending_files':
            $totalMine = ($ctx['yourDakPending'] ?? 0) + ($ctx['yourPendingRtis'] ?? 0) + ($ctx['yourBills'] ?? 0) + ($ctx['yourOpenInspections'] ?? 0);
            ?>
            <div class="card stat">
                <div class="stat-label">My Pending Files</div>
                <div class="stat-value"><?= (int) $totalMine; ?></div>
                <div class="muted">Dak: <?= (int) $ctx['yourDakPending']; ?> | RTI: <?= (int) $ctx['yourPendingRtis']; ?> | Bills: <?= (int) $ctx['yourBills']; ?></div>
            </div>
            <?php
            break;
        case 'recent_movements':
            ?>
            <div class="card">
                <h3>Recent Activity</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>When</th><th>User</th><th>Event</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice(array_reverse($ctx['usageEntries']), 0, 5) as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars(format_date_for_display($entry['timestamp'] ?? '')); ?></td>
                                <td><?= htmlspecialchars($entry['username'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($entry['event'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            break;
        case 'rti_summary':
            ?>
            <div class="card stat">
                <div class="stat-label">Your RTIs</div>
                <div class="stat-value"><?= (int) $ctx['yourPendingRtis']; ?></div>
                <div class="muted">Overdue: <?= (int) $ctx['yourOverdueRtis']; ?> | Total: <?= (int) $ctx['totalRtis']; ?></div>
            </div>
            <?php
            break;
        case 'new_dak_quick':
            ?>
            <div class="card stat">
                <div class="stat-label">Create Dak</div>
                <div class="stat-value">Ready</div>
                <a class="btn primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak&mode=create">New Dak Entry</a>
            </div>
            <?php
            break;
        case 'outbox_today':
            ?>
            <div class="card stat">
                <div class="stat-label">Outbox Today</div>
                <div class="stat-value"><?= (int) $ctx['todayOutbox']; ?></div>
                <div class="muted">Movements recorded today</div>
            </div>
            <?php
            break;
        case 'bills_summary':
            ?>
            <div class="card stat">
                <div class="stat-label">Bills Overview</div>
                <div class="stat-value"><?= (int) $ctx['totalBills']; ?></div>
                <div class="muted">Your bills: <?= (int) $ctx['yourBills']; ?></div>
            </div>
            <?php
            break;
        case 'work_orders_summary':
            ?>
            <div class="card stat">
                <div class="stat-label">Work Orders</div>
                <div class="stat-value"><?= (int) $ctx['totalWorkOrders']; ?></div>
                <div class="muted">Created by you: <?= (int) $ctx['yourWorkOrders']; ?></div>
            </div>
            <?php
            break;
        default:
            ?>
            <div class="card">
                <div class="stat-label">Widget</div>
                <div class="stat-value"><?= htmlspecialchars($id); ?></div>
                <div class="muted">No renderer defined.</div>
            </div>
            <?php
    }
}

$widgetsToRender = array_values(array_unique($widgetList));
$widgetContext = [
    'user' => $user,
    'officeConfig' => $officeConfig,
    'totalUsers' => $totalUsers,
    'totalLoginSuccess' => $totalLoginSuccess,
    'totalLoginFailure' => $totalLoginFailure,
    'userDashboardViews' => $userDashboardViews,
    'totalLettersGenerated' => $totalLettersGenerated,
    'userLettersGenerated' => $userLettersGenerated,
    'pendingRtis' => $pendingRtis,
    'overdueRtis' => $overdueRtis,
    'yourPendingRtis' => $yourPendingRtis,
    'yourOverdueRtis' => $yourOverdueRtis,
    'totalRtis' => $totalRtis,
    'pendingDak' => $pendingDak,
    'overdueDak' => $overdueDak,
    'unassignedDak' => $unassignedDak,
    'yourDakPending' => $yourDakPending,
    'yourBills' => $yourBills,
    'totalBills' => $totalBills,
    'usageEntries' => $usageEntries,
    'todayOutbox' => $todayOutbox,
    'totalWorkOrders' => $totalWorkOrders,
    'yourWorkOrders' => $yourWorkOrders,
    'yourOpenInspections' => $yourOpenInspections,
];
?>
<div class="grid">
    <?php foreach ($widgetsToRender as $widgetId): ?>
        <?php if (!dashboard_widget_visible($dashboardWidgets, $widgetId)) { continue; } ?>
        <?php render_dashboard_widget($widgetId, $widgetContext); ?>
    <?php endforeach; ?>
</div>
