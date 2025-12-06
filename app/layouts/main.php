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
            <a href="<?php echo yojaka_url('index.php?r=admin'); ?>" class="muted">Admin (coming soon)</a>
            <a href="<?php echo yojaka_url('index.php?r=login'); ?>" class="muted">Login</a>
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
