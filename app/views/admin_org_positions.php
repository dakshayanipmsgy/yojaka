<?php
$officeId = get_current_office_id();
$positions = load_positions($officeId);
?>
<div class="card">
    <h2>Positions &amp; Hierarchy</h2>
    <p>Positions are seats within the office. Assignments are stored with history for immutability.</p>
    <table class="table">
        <thead><tr><th>Position ID</th><th>Title</th><th>Department</th><th>Current Staff</th></tr></thead>
        <tbody>
        <?php foreach ($positions as $pos): ?>
            <tr>
                <td><?= htmlspecialchars($pos['position_id'] ?? ($pos['id'] ?? '')); ?></td>
                <td><?= htmlspecialchars($pos['title'] ?? ''); ?></td>
                <td><?= htmlspecialchars($pos['department_id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($pos['current_staff_id'] ?? ($pos['staff_id'] ?? '')); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
