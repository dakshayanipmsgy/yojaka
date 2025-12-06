<section class="page-intro">
    <div class="panel-header">
        <h1>Letters &amp; Notices</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=letters/create'); ?>">Create New Letter</a>
    </div>
    <p>Letters you can access in this department.</p>
</section>

<section class="panel">
    <form class="filter-form" method="get" action="<?php echo yojaka_url('index.php'); ?>">
        <input type="hidden" name="r" value="letters/list">
        <div class="form-grid">
            <div class="form-group">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="<?php echo yojaka_escape($filters['q'] ?? ''); ?>" placeholder="Subject, recipient or body">
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
                <label for="template_id">Template</label>
                <select id="template_id" name="template_id">
                    <option value="">-- Any --</option>
                    <?php foreach (($templates ?? []) as $tpl): ?>
                        <option value="<?php echo yojaka_escape($tpl['id'] ?? ''); ?>" <?php echo (($filters['template_id'] ?? '') === ($tpl['id'] ?? '')) ? 'selected' : ''; ?>><?php echo yojaka_escape($tpl['name'] ?? ($tpl['id'] ?? '')); ?></option>
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
            <a class="button secondary" href="<?php echo yojaka_url('index.php?r=letters/list'); ?>">Clear</a>
        </div>
    </form>

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
