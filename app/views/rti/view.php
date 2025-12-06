<section class="page-intro">
    <div class="panel-header">
        <h1>RTI Details</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=rti/list'); ?>">Back to List</a>
            <?php if (!empty($canEdit)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=rti/edit&id=' . urlencode($record['id'] ?? '')); ?>">Edit</a>
            <?php endif; ?>
            <?php if (!empty($canForward)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=rti/forward&id=' . urlencode($record['id'] ?? '')); ?>">Forward</a>
            <?php endif; ?>
            <?php if (!empty($canReturn)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=rti/return&id=' . urlencode($record['id'] ?? '')); ?>">Return</a>
            <?php endif; ?>
            <?php if (!empty($canClose)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=rti/close&id=' . urlencode($record['id'] ?? '')); ?>">Close</a>
            <?php endif; ?>
            <?php if (!empty($canReply)): ?>
                <?php if (!empty($record['reply_letter_id'])): ?>
                    <a class="button" href="<?php echo yojaka_url('index.php?r=letters/view&id=' . urlencode($record['reply_letter_id'])); ?>">Open Reply Letter</a>
                <?php else: ?>
                    <a class="button" href="<?php echo yojaka_url('index.php?r=rti/reply&id=' . urlencode($record['id'] ?? '')); ?>">Create Reply Letter</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($message)): ?>
        <div class="alert alert-success">Action completed: <?php echo yojaka_escape($message); ?></div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Application Details</h2>
    <dl class="meta-grid">
        <dt>ID</dt>
        <dd><?php echo yojaka_escape($record['id'] ?? ''); ?></dd>

        <dt>RTI Number</dt>
        <dd><?php echo yojaka_escape($record['basic']['rti_number'] ?? ''); ?></dd>

        <dt>Applicant</dt>
        <dd><?php echo yojaka_escape($record['basic']['applicant_name'] ?? ''); ?><br><?php echo nl2br(yojaka_escape($record['basic']['applicant_address'] ?? '')); ?></dd>

        <dt>Contact</dt>
        <dd><?php echo yojaka_escape($record['basic']['contact_details'] ?? ''); ?></dd>

        <dt>Subject</dt>
        <dd><?php echo yojaka_escape($record['basic']['subject'] ?? ''); ?></dd>

        <dt>Information Sought</dt>
        <dd><?php echo nl2br(yojaka_escape($record['basic']['information_sought'] ?? '')); ?></dd>

        <dt>Mode Received</dt>
        <dd><?php echo yojaka_escape($record['basic']['mode_received'] ?? ''); ?></dd>

        <dt>Fee Details</dt>
        <dd><?php echo yojaka_escape($record['basic']['fee_details'] ?? ''); ?></dd>

        <dt>Remarks</dt>
        <dd><?php echo nl2br(yojaka_escape($record['basic']['remarks'] ?? '')); ?></dd>
    </dl>
</section>

<section class="panel">
    <h2>Dates &amp; Status</h2>
    <?php
        $dueDate = $record['dates']['due_date'] ?? '';
        $dueLabel = $dueDate;
        if ($dueDate !== '') {
            $today = date('Y-m-d');
            if ($today > $dueDate && ($record['status'] ?? '') !== 'closed') {
                $dueLabel .= ' (Overdue)';
            } elseif ($today >= date('Y-m-d', strtotime($dueDate . ' -5 days'))) {
                $dueLabel .= ' (Due soon)';
            }
        }
    ?>
    <dl class="meta-grid">
        <dt>Received Date</dt>
        <dd><?php echo yojaka_escape($record['basic']['received_date'] ?? ''); ?></dd>

        <dt>Due Date</dt>
        <dd><?php echo yojaka_escape($dueLabel); ?></dd>

        <dt>Reply Sent</dt>
        <dd><?php echo yojaka_escape($record['dates']['reply_sent_date'] ?? ''); ?></dd>

        <dt>Closed On</dt>
        <dd><?php echo yojaka_escape($record['dates']['closing_date'] ?? ''); ?></dd>

        <dt>Status</dt>
        <dd><?php echo yojaka_escape($record['status'] ?? ''); ?></dd>
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

        <dt>Workflow</dt>
        <dd><?php echo yojaka_escape($workflowTemplate['name'] ?? ($workflowTemplate['id'] ?? '')); ?></dd>

        <dt>Current Step</dt>
        <dd><?php echo yojaka_escape($currentStep['label'] ?? ($record['workflow']['current_step'] ?? '')); ?></dd>

        <dt>Forward Options</dt>
        <dd><?php echo !empty($allowedNextSteps) ? yojaka_escape(implode(', ', array_map(function ($s) { return $s['label'] ?? $s['id'] ?? ''; }, $allowedNextSteps))) : '—'; ?></dd>

        <dt>Return Options</dt>
        <dd><?php echo !empty($allowedPrevSteps) ? yojaka_escape(implode(', ', array_map(function ($s) { return $s['label'] ?? $s['id'] ?? ''; }, $allowedPrevSteps))) : '—'; ?></dd>

        <dt>Created At</dt>
        <dd><?php echo yojaka_escape($record['created_at'] ?? ''); ?></dd>

        <dt>Updated At</dt>
        <dd><?php echo yojaka_escape($record['updated_at'] ?? ''); ?></dd>
    </dl>
</section>

<section class="panel">
    <h2>Attachments</h2>
    <?php if (!empty($canEdit)): ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo yojaka_url('index.php?r=attachments/upload'); ?>">
            <input type="hidden" name="module" value="rti">
            <input type="hidden" name="id" value="<?php echo yojaka_escape($record['id'] ?? ''); ?>">
            <input type="file" name="attachment" required>
            <button type="submit">Upload File</button>
        </form>
    <?php endif; ?>

    <?php if (empty($record['attachments'])): ?>
        <p>No attachments uploaded.</p>
    <?php else: ?>
        <?php foreach ($record['attachments'] as $file): ?>
            <div>
                <a href="<?php echo yojaka_url('index.php?r=attachments/download&module=rti&id=' . urlencode($record['id']) . '&file=' . urlencode($file)); ?>">
                    <?php echo htmlspecialchars($file); ?>
                </a>

                <?php if (!empty($canEdit)): ?>
                    <form method="post" action="<?php echo yojaka_url('index.php?r=attachments/delete'); ?>" style="display:inline">
                        <input type="hidden" name="module" value="rti">
                        <input type="hidden" name="id" value="<?php echo yojaka_escape($record['id'] ?? ''); ?>">
                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
                        <button onclick="return confirm('Delete this file?')">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
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
                            <td><?php echo yojaka_escape($event['source'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($event['label'] ?? ($event['type'] ?? '')); ?></td>
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
