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
            <div class="nav-section">Admin</div>
            <div class="nav-item disabled<?= ($user['role'] ?? '') !== 'admin' ? ' locked' : ''; ?>">User Management (Coming Soon)</div>
            <div class="nav-section">Modules</div>
            <div class="nav-item disabled">Letters &amp; Notices (Coming Soon)</div>
            <div class="nav-item disabled">RTI Replies (Coming Soon)</div>
            <div class="nav-item disabled">Dak &amp; File Movement (Coming Soon)</div>
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
        Powered by Dakshayani &bull; Yojaka v0.1
    </footer>
</body>
</html>
