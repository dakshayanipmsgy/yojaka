<?php
$officeId = get_current_office_id();
$routes = load_routes($officeId);
?>
<div class="card">
    <h2>File Routes &amp; Workflows</h2>
    <p>Routes now revolve around positions. Legacy user-based routes are still shown for reference.</p>
    <table class="table">
        <thead><tr><th>Route</th><th>Department</th><th>File Type</th><th>Nodes</th></tr></thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <tr>
                <td><?= htmlspecialchars($route['id'] ?? ''); ?> - <?= htmlspecialchars($route['name'] ?? ''); ?></td>
                <td><?= htmlspecialchars($route['department_id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($route['file_type'] ?? ''); ?></td>
                <td><?= count($route['nodes'] ?? []); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
