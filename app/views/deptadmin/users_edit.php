<section class="page-intro">
    <h1>Edit Department User</h1>
    <p>Update the display name and assigned roles for this user.</p>
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

<div class="panel">
    <dl class="meta-list">
        <dt>Username Base</dt>
        <dd><?php echo yojaka_escape($user['username_base'] ?? ''); ?></dd>
        <dt>Current Login Identities</dt>
        <dd>
            <?php if (!empty($user['login_identities'])): ?>
                <ul class="meta-list">
                    <?php foreach ($user['login_identities'] as $identity): ?>
                        <li><?php echo yojaka_escape($identity); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span class="muted">None</span>
            <?php endif; ?>
        </dd>
    </dl>
</div>

<form method="post" action="<?php echo yojaka_url('index.php?r=deptadmin/users/edit&id=' . urlencode($user['id'] ?? '')); ?>" class="form">
    <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" name="display_name" id="display_name" value="<?php echo yojaka_escape($form['display_name'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label>Assign Roles</label>
        <?php if (empty($roles)): ?>
            <p class="muted">No roles exist yet.</p>
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
        <button type="submit">Save Changes</button>
        <a class="button button-secondary" href="<?php echo yojaka_url('index.php?r=deptadmin/users'); ?>">Cancel</a>
    </div>
</form>
