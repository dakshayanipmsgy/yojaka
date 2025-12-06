<section class="page-intro">
    <h1>Create Role</h1>
    <p>Define a role and select permissions from the global catalog.</p>
</section>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo yojaka_escape($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo yojaka_url('index.php?r=deptadmin/roles/create'); ?>" class="form">
    <div class="form-group">
        <label for="local_key">Local Key <span class="muted">(letters/numbers/underscores)</span></label>
        <input type="text" name="local_key" id="local_key" value="<?php echo yojaka_escape($form['local_key'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="label">Label</label>
        <input type="text" name="label" id="label" value="<?php echo yojaka_escape($form['label'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label>Permissions</label>
        <div class="permissions-grid">
            <?php foreach ($catalog as $groupKey => $permissions): ?>
                <div class="permission-group">
                    <h3><?php echo yojaka_escape(ucwords(str_replace('_', ' ', $groupKey))); ?></h3>
                    <?php foreach ($permissions as $permission): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="permissions[]" value="<?php echo yojaka_escape($permission); ?>" <?php echo in_array($permission, $form['permissions'] ?? [], true) ? 'checked' : ''; ?>>
                            <?php echo yojaka_escape($permission); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit">Create Role</button>
        <a class="button button-secondary" href="<?php echo yojaka_url('index.php?r=deptadmin/dashboard'); ?>">Cancel</a>
    </div>
</form>
