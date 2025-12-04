<?php
require_role('superadmin');
$registry = load_offices_registry();
$total = count($registry);
$active = count(array_filter($registry, fn($o) => !empty($o['active'])));
$suspended = $total - $active;

function superadmin_collect_errors(array $registry): array
{
    $logs = [];
    foreach ($registry as $office) {
        $logFile = YOJAKA_DATA_PATH . '/logs/' . ($office['id'] ?? '') . '_error.log';
        if (file_exists($logFile)) {
            $content = @file_get_contents($logFile);
            if ($content) {
                $logs[$office['id']] = explode("\n", trim($content));
            }
        }
    }
    return $logs;
}

$logs = superadmin_collect_errors($registry);
?>
<div class="grid" style="grid-template-columns: repeat(3,1fr); gap: 1rem;">
    <div class="card">
        <div class="muted">Total Offices</div>
        <div class="stat"><?= (int) $total; ?></div>
    </div>
    <div class="card">
        <div class="muted">Active</div>
        <div class="stat"><?= (int) $active; ?></div>
    </div>
    <div class="card">
        <div class="muted">Suspended</div>
        <div class="stat"><?= (int) $suspended; ?></div>
    </div>
</div>

<h3>Licenses nearing expiry</h3>
<table class="table">
    <thead><tr><th>Office</th><th>Expiry</th></tr></thead>
    <tbody>
        <?php foreach ($registry as $o): ?>
            <?php if (!empty($o['license_expiry'])): ?>
                <tr>
                    <td><?= htmlspecialchars($o['name'] ?? $o['id']); ?></td>
                    <td><?= htmlspecialchars($o['license_expiry']); ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Error logs</h3>
<?php if (empty($logs)): ?>
    <p class="muted">No error logs found.</p>
<?php else: ?>
    <?php foreach ($logs as $officeId => $lines): ?>
        <div class="panel">
            <strong><?= htmlspecialchars($officeId); ?></strong>
            <pre style="max-height:200px; overflow:auto; background:#f7f7f7; padding:8px;"><?= htmlspecialchars(implode("\n", array_slice($lines, -10))); ?></pre>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
