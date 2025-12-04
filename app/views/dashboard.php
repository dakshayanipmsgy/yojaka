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

$yourPendingRtis = 0;
$yourOverdueRtis = 0;
$totalRtis = count($rtiCases);
$pendingRtis = 0;
$overdueRtis = 0;

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
    <?php endif; ?>
</div>
