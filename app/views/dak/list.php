<section class="page-intro">
    <div class="panel-header">
        <h1>Dak – My Files</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/create'); ?>">Create New Dak</a>
    </div>
    <p>Incoming dak entries visible to you appear below.</p>
</section>

<section class="panel">
    <?php if (empty($records)): ?>
        <p>No dak records available.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Step</th>
                        <th>Created</th>
                        <th>Assignee</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo yojaka_escape($record['id'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['title'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['reference_no'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['status'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['workflow']['current_step'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['created_at'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['assignee_username'] ?? ''); ?></td>
                            <td><a href="<?php echo yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id'] ?? '')); ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
