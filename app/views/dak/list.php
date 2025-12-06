<section class="page-intro">
    <div class="panel-header">
        <h1>Dak – My Files</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/create'); ?>">Create New Dak</a>
    </div>
    <p>Incoming dak entries visible to you appear below.</p>
</section>

<section class="panel">
    <form class="filter-form" method="get" action="<?php echo yojaka_url('index.php'); ?>">
        <input type="hidden" name="r" value="dak/list">
        <div class="form-grid">
            <div class="form-group">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="<?php echo yojaka_escape($filters['q'] ?? ''); ?>" placeholder="Title, subject, reference">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">-- Any --</option>
                    <?php foreach (($statusOptions ?? []) as $key => $label): ?>
                        <option value="<?php echo yojaka_escape($key); ?>" <?php echo (($filters['status'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo yojaka_escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="current_step">Workflow Step</label>
                <select id="current_step" name="current_step">
                    <option value="">-- Any --</option>
                    <?php foreach (($workflowSteps ?? []) as $id => $label): ?>
                        <option value="<?php echo yojaka_escape($id); ?>" <?php echo (($filters['current_step'] ?? '') === $id) ? 'selected' : ''; ?>><?php echo yojaka_escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="assignee">Assignee</label>
                <select id="assignee" name="assignee">
                    <option value="">-- Any --</option>
                    <?php foreach (($assignees ?? []) as $identity => $label): ?>
                        <option value="<?php echo yojaka_escape($identity); ?>" <?php echo (($filters['assignee'] ?? '') === $identity) ? 'selected' : ''; ?>><?php echo yojaka_escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="created_from">Created From</label>
                <input type="date" id="created_from" name="created_from" value="<?php echo yojaka_escape($filters['created_from'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="created_to">Created To</label>
                <input type="date" id="created_to" name="created_to" value="<?php echo yojaka_escape($filters['created_to'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="button">Apply Filters</button>
            <a class="button secondary" href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Clear</a>
        </div>
    </form>

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
