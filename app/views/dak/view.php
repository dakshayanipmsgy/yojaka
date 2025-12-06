<section class="page-intro">
    <div class="panel-header">
        <h1>Dak Details</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Back to List</a>
            <?php if (!empty($canEdit)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=dak/edit&id=' . urlencode($record['id'] ?? '')); ?>">Edit</a>
            <?php endif; ?>
            <?php if (!empty($canForward)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=dak/forward&id=' . urlencode($record['id'] ?? '')); ?>">Forward</a>
            <?php endif; ?>
            <?php if (!empty($canReturn)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=dak/return&id=' . urlencode($record['id'] ?? '')); ?>">Return</a>
            <?php endif; ?>
            <?php if (!empty($canClose)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=dak/close&id=' . urlencode($record['id'] ?? '')); ?>">Close</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($message) && $message === 'created'): ?>
        <div class="alert alert-success">Dak created successfully.</div>
    <?php elseif (!empty($message) && $message === 'updated'): ?>
        <div class="alert alert-success">Dak updated successfully.</div>
    <?php elseif (!empty($message) && $message === 'forwarded'): ?>
        <div class="alert alert-success">Dak forwarded.</div>
    <?php elseif (!empty($message) && $message === 'returned'): ?>
        <div class="alert alert-success">Dak returned.</div>
    <?php elseif (!empty($message) && $message === 'closed'): ?>
        <div class="alert alert-success">Dak closed.</div>
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

<section class="panel">
    <h2>Workflow Progress</h2>
    <?php if (empty($workflowTemplate)): ?>
        <p>No workflow is attached to this dak.</p>
    <?php else: ?>
        <dl class="meta-grid">
            <dt>Workflow</dt>
            <dd><?php echo yojaka_escape($workflowTemplate['name'] ?? ($workflowTemplate['id'] ?? '')); ?></dd>

            <dt>Current Step</dt>
            <dd><?php echo yojaka_escape($currentStep['label'] ?? ($currentStep['id'] ?? 'N/A')); ?></dd>

            <dt>Status</dt>
            <dd><?php echo yojaka_escape($record['workflow']['status'] ?? $record['status'] ?? ''); ?></dd>

            <dt>Forward Options</dt>
            <dd><?php echo !empty($allowedNextSteps) ? yojaka_escape(implode(', ', array_map(function ($s) { return ($s['label'] ?? $s['id'] ?? ''); }, $allowedNextSteps))) : '—'; ?></dd>

            <dt>Return Options</dt>
            <dd><?php echo !empty($allowedPrevSteps) ? yojaka_escape(implode(', ', array_map(function ($s) { return ($s['label'] ?? $s['id'] ?? ''); }, $allowedPrevSteps))) : '—'; ?></dd>
        </dl>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Workflow History</h2>
    <?php $history = $record['workflow']['history'] ?? []; ?>
    <?php if (empty($history)): ?>
        <p>No workflow activity recorded yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>From → To</th>
                        <th>Actor</th>
                        <th>Assigned To</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td><?php echo yojaka_escape($entry['timestamp'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($entry['action'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape(($entry['from_step'] ?? '—') . ' → ' . ($entry['to_step'] ?? '—')); ?></td>
                            <td><?php echo yojaka_escape($entry['actor_user'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($entry['to_user'] ?? ($entry['external_actor'] ?? '')); ?></td>
                            <td><?php echo nl2br(yojaka_escape($entry['comment'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Activity Timeline</h2>
    <?php if (empty($timeline)): ?>
        <p>No activity recorded yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Source</th>
                        <th>Action</th>
                        <th>From → To</th>
                        <th>Actor</th>
                        <th>Target</th>
                        <th>Comment / Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeline as $event): ?>
                        <tr>
                            <td><?php echo yojaka_escape($event['timestamp'] ?? ''); ?></td>
                            <td><span class="tag tag-small"><?php echo yojaka_escape($event['source'] ?? ''); ?></span></td>
                            <td><?php echo yojaka_escape($event['label'] ?? $event['type'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape(($event['from_step'] ?? '—') . ' → ' . ($event['to_step'] ?? '—')); ?></td>
                            <td><?php echo yojaka_escape($event['actor'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($event['to_user'] ?? ''); ?></td>
                            <td><?php echo nl2br(yojaka_escape($event['comment'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
