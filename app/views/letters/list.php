<section class="page-intro">
    <div class="panel-header">
        <h1>Letters &amp; Notices</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=letters/create'); ?>">Create New Letter</a>
    </div>
    <p>Letters you can access in this department.</p>
</section>

<section class="panel">
    <?php if (empty($records)): ?>
        <p>No letters found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Template</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo yojaka_escape($record['id'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($templateMap[$record['template_id'] ?? ''] ?? ($record['template_id'] ?? '')); ?></td>
                            <td><?php echo yojaka_escape($record['fields']['subject'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['status'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['created_at'] ?? ''); ?></td>
                            <td><a href="<?php echo yojaka_url('index.php?r=letters/view&id=' . urlencode($record['id'] ?? '')); ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
