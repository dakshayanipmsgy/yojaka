<?php
/**
 * Unified people & user view (lightweight placeholder)
 */
$officeId = get_current_office_id();
$staff = load_staff($officeId);
$users = load_users();
$assignments = load_position_assignments($officeId);
?>
<div class="card">
    <h2>People &amp; Users</h2>
    <p>This lightweight dashboard surfaces staff, linked user accounts, and current position assignments using the new unified helpers.</p>
    <h3>Staff Directory (<?= count($staff); ?>)</h3>
    <table class="table">
        <thead><tr><th>Staff ID</th><th>Name</th><th>Designation</th><th>Department</th><th>User</th></tr></thead>
        <tbody>
        <?php foreach ($staff as $person):
            $user = null;
            foreach ($users as $u) {
                if (($u['staff_id'] ?? null) === ($person['staff_id'] ?? null)) {
                    $user = $u;
                    break;
                }
            }
            ?>
            <tr>
                <td><?= htmlspecialchars($person['staff_id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($person['full_name'] ?? ''); ?></td>
                <td><?= htmlspecialchars($person['designation'] ?? ''); ?></td>
                <td><?= htmlspecialchars($person['department_id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($user['username'] ?? '—'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Position Assignments (<?= count($assignments); ?>)</h3>
    <table class="table">
        <thead><tr><th>Position</th><th>Staff</th><th>From</th><th>To</th><th>Remarks</th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $assignment): ?>
            <tr>
                <td><?= htmlspecialchars($assignment['position_id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($assignment['staff_id'] ?? ''); ?></td>
                <td><?= htmlspecialchars($assignment['effective_from'] ?? ''); ?></td>
                <td><?= htmlspecialchars($assignment['effective_to'] ?? ''); ?></td>
                <td><?= htmlspecialchars($assignment['remarks'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="alert info">
        Download a sample CSV for bulk onboarding: <a href="<?= YOJAKA_BASE_URL; ?>/public/samples/users_sample.csv" target="_blank">users_sample.csv</a>
    </div>
</div>
