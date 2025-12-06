<section class="page-intro">
    <h1>Change User Password</h1>
    <p>Reset or update the password for this login identity group.</p>
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
        <dt>Login Identities</dt>
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

<form method="post" action="<?php echo yojaka_url('index.php?r=deptadmin/users/password&id=' . urlencode($user['id'] ?? '')); ?>" class="form">
    <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" name="new_password" id="new_password" required>
    </div>

    <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required>
    </div>

    <div class="form-actions">
        <button type="submit">Update Password</button>
        <a class="button button-secondary" href="<?php echo yojaka_url('index.php?r=deptadmin/users'); ?>">Cancel</a>
    </div>
</form>
