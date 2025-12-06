<section class="page-intro">
    <h1>Create Department User</h1>
    <p>Define the base username, display name, and assign one or more roles.</p>
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

<form method="post" action="<?php echo yojaka_url('index.php?r=deptadmin/users/create'); ?>" class="form">
    <div class="form-group">
        <label for="username_base">Username Base <span class="muted">(lowercase letters/numbers)</span></label>
        <input type="text" name="username_base" id="username_base" value="<?php echo yojaka_escape($form['username_base'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" name="display_name" id="display_name" value="<?php echo yojaka_escape($form['display_name'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label>Assign Roles</label>
        <?php if (empty($roles)): ?>
            <p class="muted">No roles exist yet. Create roles first.</p>
        <?php else: ?>
            <div class="permissions-grid">
                <?php foreach ($roles as $role): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="role_ids[]" value="<?php echo yojaka_escape($role['role_id'] ?? ''); ?>" <?php echo in_array($role['role_id'] ?? '', $form['role_ids'] ?? [], true) ? 'checked' : ''; ?>>
                        <?php echo yojaka_escape($role['role_id'] ?? ''); ?> (<?php echo yojaka_escape($role['label'] ?? ''); ?>)
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit">Create User</button>
        <a class="button button-secondary" href="<?php echo yojaka_url('index.php?r=deptadmin/users'); ?>">Cancel</a>
    </div>
</form>
