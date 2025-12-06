<section class="page-intro">
    <h1>Change Password</h1>
    <p>Update your department admin password. You must change the default password before proceeding.</p>
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

<form method="post" action="<?php echo yojaka_url('index.php?r=deptadmin/change_password'); ?>" class="form">
    <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" name="current_password" id="current_password" required>
    </div>

    <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" name="new_password" id="new_password" required minlength="8">
    </div>

    <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
    </div>

    <div class="form-actions">
        <button type="submit">Update Password</button>
        <a class="button button-secondary" href="<?php echo yojaka_url('index.php?r=deptadmin/dashboard'); ?>">Cancel</a>
    </div>
</form>
