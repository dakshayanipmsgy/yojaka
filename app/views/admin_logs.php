<?php
$maxEntries = 300;
$entries = read_usage_logs($maxEntries);
$totalLoginSuccess = count_events($entries, 'login_success');
$totalLoginFailure = count_events($entries, 'login_failure');
$totalDashboardViews = count_events($entries, 'dashboard_view');
?>
<div class="grid stats">
    <div class="card stat">
        <div class="stat-label">Login Successes</div>
        <div class="stat-value"><?= (int) $totalLoginSuccess; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Login Failures</div>
        <div class="stat-value warn"><?= (int) $totalLoginFailure; ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Dashboard Views</div>
        <div class="stat-value"><?= (int) $totalDashboardViews; ?></div>
    </div>
</div>
<div class="info">
    <p>Showing up to the most recent <?= $maxEntries; ?> entries from the usage log.</p>
</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Event</th>
                <th>Username</th>
                <th>IP</th>
                <th>User Agent</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
                <tr><td colspan="6">No log entries available.</td></tr>
            <?php else: ?>
                <?php foreach (array_reverse($entries) as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['timestamp'] ?? ''); ?></td>
                        <td><span class="badge"><?= htmlspecialchars($entry['event'] ?? ''); ?></span></td>
                        <td><?= htmlspecialchars($entry['username'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($entry['ip'] ?? ''); ?></td>
                        <td><?= htmlspecialchars(substr($entry['user_agent'] ?? '', 0, 80)); ?></td>
                        <td>
                            <?php
                            $details = $entry['details'] ?? '';
                            if (is_array($details) || is_object($details)) {
                                echo htmlspecialchars(json_encode($details));
                            } else {
                                echo htmlspecialchars((string) $details);
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
