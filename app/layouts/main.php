<?php
/**
 * Main layout wrapper for Yojaka pages.
 * $content is injected from the view renderer.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? yojaka_escape($title) : 'Yojaka'; ?></title>
    <link rel="stylesheet" href="<?php echo yojaka_url('assets/css/style.css'); ?>">
</head>
<body>
    <header class="site-header">
        <div class="branding">
            <h1><a href="<?php echo yojaka_url('index.php'); ?>">Yojaka</a></h1>
            <p class="tagline">Workflow &amp; document automation for government departments</p>
        </div>
        <nav class="nav">
            <a href="<?php echo yojaka_url('index.php'); ?>">Home</a>
            <a href="<?php echo yojaka_url('index.php?r=about'); ?>">About</a>

            <?php if (yojaka_is_superadmin()): ?>
                <a href="<?php echo yojaka_url('index.php?r=superadmin/dashboard'); ?>">Superadmin Dashboard</a>
                <a href="<?php echo yojaka_url('index.php?r=auth/logout'); ?>">Logout</a>
                <span class="muted">Logged in as superadmin</span>
            <?php elseif (yojaka_is_dept_admin()): ?>
                <a href="<?php echo yojaka_url('index.php?r=deptadmin/dashboard'); ?>">Department Admin Dashboard</a>
                <a href="<?php echo yojaka_url('index.php?r=deptadmin/workflows'); ?>">Workflows</a>
                <?php $currentUser = yojaka_current_user(); ?>
                <?php if ($currentUser && (($currentUser['user_type'] ?? '') === 'dept_admin' || yojaka_has_permission($currentUser, 'dak.create'))): ?>
                    <a href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Dak</a>
                <?php endif; ?>
                <?php if ($currentUser && (($currentUser['user_type'] ?? '') === 'dept_admin' || yojaka_has_permission($currentUser, 'letters.view'))): ?>
                    <a href="<?php echo yojaka_url('index.php?r=letters/list'); ?>">Letters</a>
                <?php endif; ?>
                <a href="<?php echo yojaka_url('index.php?r=auth/logout'); ?>">Logout</a>
                <span class="muted">Logged in as Admin (<?php echo yojaka_escape($currentUser['department_slug'] ?? ''); ?>)</span>
            <?php elseif (yojaka_is_dept_user()): ?>
                <a href="<?php echo yojaka_url('index.php?r=deptuser/dashboard'); ?>">Dept User Dashboard</a>
                <?php $currentUser = yojaka_current_user(); ?>
                <?php if ($currentUser && yojaka_has_permission($currentUser, 'dak.create')): ?>
                    <a href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Dak</a>
                <?php endif; ?>
                <?php if ($currentUser && yojaka_has_permission($currentUser, 'letters.view')): ?>
                    <a href="<?php echo yojaka_url('index.php?r=letters/list'); ?>">Letters</a>
                <?php endif; ?>
                <a href="<?php echo yojaka_url('index.php?r=auth/logout'); ?>">Logout</a>
                <span class="muted">Logged in as <?php echo yojaka_escape($currentUser['login_identity'] ?? ''); ?></span>
            <?php elseif (yojaka_is_logged_in()): ?>
                <a href="#" class="muted">Dashboard</a>
                <a href="<?php echo yojaka_url('index.php?r=auth/logout'); ?>">Logout</a>
            <?php else: ?>
                <a href="<?php echo yojaka_url('index.php?r=auth/login'); ?>">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="content">
        <?php echo $content ?? ''; ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Yojaka. All rights reserved.</p>
    </footer>

    <script src="<?php echo yojaka_url('assets/js/main.js'); ?>"></script>
</body>
</html>
