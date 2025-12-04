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
</div>
