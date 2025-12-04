<?php
$error = $error ?? '';
$csrf_token = $csrf_token ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Yojaka</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">Yojaka</div>
        <p class="auth-subtitle">Secure access for government workflow</p>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required autofocus>
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
            <button type="submit" class="btn-primary">Login</button>
        </form>
        <div class="auth-footer">Powered by Dakshayani</div>
    </div>
</body>
</html>
