<section class="auth-box">
    <h1>Login</h1>
    <p>Please sign in with your administrator credentials.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo yojaka_escape($error); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo yojaka_url('index.php?r=auth/login'); ?>" class="form">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-actions">
            <button type="submit">Login</button>
        </div>
    </form>
</section>
