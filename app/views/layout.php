<?php
// Main layout template
$user = current_user();
$pageTitle = $pageTitle ?? 'Yojaka';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> - Yojaka</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/assets/css/style.css">
</head>
<body class="app-shell">
    <header class="topbar">
        <div class="brand">
            <div class="logo">Yojaka</div>
            <div class="subtitle">Govt Workflow &amp; Document Automation</div>
        </div>
        <div class="user-meta">
            <div class="name"><?= htmlspecialchars($user['full_name'] ?? ''); ?></div>
            <div class="role badge"><?= htmlspecialchars($user['role'] ?? ''); ?></div>
            <a class="logout" href="<?= YOJAKA_BASE_URL; ?>/logout.php">Logout</a>
        </div>
    </header>
    <div class="body">
        <nav class="sidebar">
            <div class="nav-section">Main</div>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dashboard" class="nav-item<?= ($activePage ?? '') === 'dashboard' ? ' active' : ''; ?>">Dashboard</a>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=letters" class="nav-item<?= ($activePage ?? '') === 'letters' ? ' active' : ''; ?>">Letters &amp; Notices</a>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti" class="nav-item<?= ($activePage ?? '') === 'rti' ? ' active' : ''; ?>">RTI Cases</a>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak" class="nav-item<?= ($activePage ?? '') === 'dak' ? ' active' : ''; ?>">Dak &amp; File Movement</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <div class="nav-section">Admin</div>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users" class="nav-item<?= ($activePage ?? '') === 'admin_users' ? ' active' : ''; ?>">User List</a>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_logs" class="nav-item<?= ($activePage ?? '') === 'admin_logs' ? ' active' : ''; ?>">Usage Logs</a>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_letter_templates" class="nav-item<?= ($activePage ?? '') === 'admin_letter_templates' ? ' active' : ''; ?>">Letter Templates</a>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_rti" class="nav-item<?= ($activePage ?? '') === 'admin_rti' ? ' active' : ''; ?>">RTI Management</a>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_dak" class="nav-item<?= ($activePage ?? '') === 'admin_dak' ? ' active' : ''; ?>">Dak Management</a>
            <?php endif; ?>
        </nav>
        <main class="content">
            <h1><?= htmlspecialchars($pageTitle); ?></h1>
            <section class="panel">
                <?php if (!empty($viewFile) && file_exists($viewFile)) { include $viewFile; } else { ?>
                    <p>Page not found.</p>
                <?php } ?>
            </section>
        </main>
    </div>
    <footer class="footer">
        Powered by Dakshayani &bull; Yojaka v0.5
    </footer>
</body>
</html>
