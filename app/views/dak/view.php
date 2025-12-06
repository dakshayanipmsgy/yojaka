<section class="page-intro">
    <div class="panel-header">
        <h1>Dak Details</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Back to List</a>
            <?php if (!empty($canEdit)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=dak/edit&id=' . urlencode($record['id'] ?? '')); ?>">Edit</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($message) && $message === 'created'): ?>
        <div class="alert alert-success">Dak created successfully.</div>
    <?php elseif (!empty($message) && $message === 'updated'): ?>
        <div class="alert alert-success">Dak updated successfully.</div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Summary</h2>
    <dl class="meta-grid">
        <dt>ID</dt>
        <dd><?php echo yojaka_escape($record['id'] ?? ''); ?></dd>

        <dt>Title</dt>
        <dd><?php echo yojaka_escape($record['title'] ?? ''); ?></dd>

        <dt>Reference No.</dt>
        <dd><?php echo yojaka_escape($record['reference_no'] ?? ''); ?></dd>

        <dt>Status</dt>
        <dd><?php echo yojaka_escape($record['status'] ?? ''); ?></dd>

        <dt>Received Date</dt>
        <dd><?php echo yojaka_escape($record['received_date'] ?? ''); ?></dd>

        <dt>Received Via</dt>
        <dd><?php echo yojaka_escape($record['received_via'] ?? ''); ?></dd>

        <dt>From</dt>
        <dd><?php echo yojaka_escape($record['from_name'] ?? ''); ?><br><?php echo nl2br(yojaka_escape($record['from_address'] ?? '')); ?></dd>

        <dt>Subject</dt>
        <dd><?php echo yojaka_escape($record['subject'] ?? ''); ?></dd>

        <dt>Remarks</dt>
        <dd><?php echo nl2br(yojaka_escape($record['remarks'] ?? '')); ?></dd>
    </dl>
</section>

<section class="panel">
    <h2>Ownership &amp; Workflow</h2>
    <dl class="meta-grid">
        <dt>Owner</dt>
        <dd><?php echo yojaka_escape($record['owner_username'] ?? ''); ?></dd>

        <dt>Assignee</dt>
        <dd><?php echo yojaka_escape($record['assignee_username'] ?? ''); ?></dd>

        <dt>Allowed Roles</dt>
        <dd><?php echo !empty($record['allowed_roles']) ? yojaka_escape(implode(', ', $record['allowed_roles'])) : '—'; ?></dd>

        <dt>Allowed Users</dt>
        <dd><?php echo !empty($record['allowed_users']) ? yojaka_escape(implode(', ', $record['allowed_users'])) : '—'; ?></dd>

        <dt>Created At</dt>
        <dd><?php echo yojaka_escape($record['created_at'] ?? ''); ?></dd>

        <dt>Updated At</dt>
        <dd><?php echo yojaka_escape($record['updated_at'] ?? ''); ?></dd>
    </dl>
</section>
