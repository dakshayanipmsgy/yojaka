<section class="page-intro">
    <h1>Department Audit Log</h1>
    <p>Recent activity recorded for this department.</p>
    <p><a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/dashboard'); ?>">Back to Dashboard</a></p>
</section>

<section class="panel">
    <h2>Recent Entries</h2>
    <?php if (empty($entries)): ?>
        <p>No audit entries recorded yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Module</th>
                        <th>Record</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?php echo yojaka_escape($entry['timestamp'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($entry['actor_username'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($entry['module'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($entry['record_id'] ?? '—'); ?></td>
                            <td><?php echo yojaka_escape($entry['action_label'] ?? ($entry['action_type'] ?? '')); ?></td>
                            <td><code><?php echo yojaka_escape(json_encode($entry['details'] ?? [])); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
