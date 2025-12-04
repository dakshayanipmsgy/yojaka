<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!YOJAKA_INSTALLED) {
    header('Location: ' . YOJAKA_BASE_URL . '/install.php');
    exit;
}

if (is_logged_in()) {
    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojaka - Government Workflow Platform</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/assets/css/style.css">
</head>
<body class="landing">
    <div class="hero">
        <div class="hero-text">
            <div class="brand">Yojaka</div>
            <p class="tagline">A secure workflow and document automation platform for government offices.</p>
            <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/login.php">Login</a>
        </div>
        <div class="hero-footer">Powered by Dakshayani</div>
    </div>
</body>
</html>
