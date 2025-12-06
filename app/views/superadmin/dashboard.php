<section class="page-intro">
    <h1>Welcome, Super Administrator</h1>
    <p>Manage departments and review the multi-tenant setup for Yojaka.</p>
</section>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo yojaka_escape($message); ?></div>
<?php endif; ?>

<?php if (!empty($adminNotice)): ?>
    <div class="alert alert-info"><?php echo yojaka_escape($adminNotice); ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo yojaka_escape($error); ?></div>
<?php endif; ?>

<section class="panel">
    <h2>Create Department</h2>
    <form method="post" action="<?php echo yojaka_url('index.php?r=superadmin/dashboard'); ?>" class="form">
        <div class="form-group">
            <label for="dept-name">Name</label>
            <input type="text" id="dept-name" name="name" required>
        </div>
        <div class="form-group">
            <label for="dept-slug">Slug <span class="muted">(lowercase, letters/numbers/dashes)</span></label>
            <input type="text" id="dept-slug" name="slug" placeholder="optional; auto-generated from name">
        </div>
        <div class="form-actions">
            <button type="submit">Create Department</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Departments</h2>
    <?php if (empty($departments)): ?>
        <p>No departments created yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td><?php echo yojaka_escape($department['id'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($department['slug'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($department['name'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($department['status'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($department['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
