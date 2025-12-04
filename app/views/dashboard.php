<?php
$user = current_user();
$users = load_users();
$usageEntries = read_usage_logs(1000); // limit to recent entries for lightweight stats

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
?>
<div class="grid">
    <div class="card highlight">
        <h2>Welcome, <?= htmlspecialchars($user['full_name'] ?? ''); ?>!</h2>
        <p>You are logged in as <strong><?= htmlspecialchars($user['role'] ?? ''); ?></strong>.</p>
        <p>This is the starting point for Yojaka modules.</p>
    </div>
    <div class="card stat">
        <div class="stat-label">Registered Users</div>
        <div class="stat-value"><?= (int) $totalUsers; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Login Successes</div>
        <div class="stat-value"><?= (int) $totalLoginSuccess; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Login Failures</div>
        <div class="stat-value warn"><?= (int) $totalLoginFailure; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your Dashboard Views</div>
        <div class="stat-value"><?= (int) $userDashboardViews; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Total Letters Generated</div>
        <div class="stat-value"><?= (int) $totalLettersGenerated; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your Letters Generated</div>
        <div class="stat-value"><?= (int) $userLettersGenerated; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your Meeting Minutes</div>
        <div class="stat-value"><?= (int) $yourMeetingMinutes; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your Work Orders</div>
        <div class="stat-value"><?= (int) $yourWorkOrders; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your GUCs</div>
        <div class="stat-value"><?= (int) $yourGucs; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your Inspection Reports</div>
        <div class="stat-value"><?= (int) $yourInspections; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your Open Inspections</div>
        <div class="stat-value"><?= (int) $yourOpenInspections; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Dak Assigned to You</div>
        <div class="stat-value"><?= (int) $yourDakAssigned; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Dak Pending for You</div>
        <div class="stat-value"><?= (int) $yourDakPending; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Dak Overdue</div>
        <div class="stat-value warn"><?= (int) $yourDakOverdue; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your RTIs (Pending)</div>
        <div class="stat-value"><?= (int) $yourPendingRtis; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Your RTIs (Overdue)</div>
        <div class="stat-value warn"><?= (int) $yourOverdueRtis; ?></div>
    </div>
    <?php if (($user['role'] ?? '') === 'admin'): ?>
        <div class="card stat">
            <div class="stat-label">Total RTIs</div>
            <div class="stat-value"><?= (int) $totalRtis; ?></div>
        </div>
        <div class="card stat">
        <div class="stat-label">Pending RTIs</div>
        <div class="stat-value"><?= (int) $pendingRtis; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Overdue RTIs</div>
        <div class="stat-value warn"><?= (int) $overdueRtis; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Total Dak</div>
        <div class="stat-value"><?= (int) $totalDak; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Pending Dak</div>
        <div class="stat-value"><?= (int) $pendingDak; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Overdue Dak</div>
        <div class="stat-value warn"><?= (int) $overdueDak; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Unassigned Dak</div>
        <div class="stat-value"><?= (int) $unassignedDak; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Total Inspection Reports</div>
        <div class="stat-value"><?= (int) $totalInspections; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Open Inspection Reports</div>
        <div class="stat-value"><?= (int) $openInspections; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Closed Inspection Reports</div>
        <div class="stat-value"><?= (int) $closedInspections; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Total Meeting Minutes</div>
        <div class="stat-value"><?= (int) $totalMeetingMinutes; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Total Work Orders</div>
        <div class="stat-value"><?= (int) $totalWorkOrders; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Total GUCs</div>
        <div class="stat-value"><?= (int) $totalGucs; ?></div>
    </div>
    <?php endif; ?>
</div>
