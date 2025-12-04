<?php
// Main layout template
$user = current_user();
$pageTitle = $pageTitle ?? 'Yojaka';
$department = $user ? get_user_department($user) : null;
$officeConfig = load_office_config();
$primaryColor = $officeConfig['theme']['primary_color'] ?? '#0f5aa5';
$secondaryColor = $officeConfig['theme']['secondary_color'] ?? '#f5f7fb';
$hasAdminMenu = user_has_permission('manage_users') || user_has_permission('manage_templates') || user_has_permission('manage_departments') || user_has_permission('view_logs') || user_has_permission('manage_rti') || user_has_permission('manage_dak') || user_has_permission('manage_inspection') || user_has_permission('admin_backup') || user_has_permission('manage_office_config') || user_has_permission('view_mis_reports') || user_has_permission('view_all_records');
$unreadNotifications = $user ? count(get_unread_notifications_for_user($user['username'])) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> - Yojaka</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/assets/css/style.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($primaryColor); ?>;
            --bg: <?= htmlspecialchars($secondaryColor); ?>;
        }
    </style>
</head>
<body class="app-shell">
    <header class="topbar">
        <div class="brand">
            <?php if (!empty($officeConfig['theme']['logo_path'])): ?>
                <img src="<?= YOJAKA_BASE_URL . '/' . ltrim($officeConfig['theme']['logo_path'], '/'); ?>" alt="Logo" class="brand-logo">
            <?php endif; ?>
            <div>
                <div class="logo"><?= htmlspecialchars($officeConfig['office_name'] ?? 'Yojaka'); ?></div>
                <div class="subtitle">Govt Workflow &amp; Document Automation</div>
            </div>
        </div>
        <div class="user-meta">
            <div class="name"><?= htmlspecialchars($user['full_name'] ?? ''); ?></div>
            <div class="role badge"><?= htmlspecialchars($user['role'] ?? ''); ?></div>
            <?php if ($department): ?>
                <div class="dept muted">Dept: <?= htmlspecialchars($department['name'] ?? ''); ?></div>
            <?php endif; ?>
            <a class="logout" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=notifications">Notifications <?= $unreadNotifications ? '(' . (int) $unreadNotifications . ')' : ''; ?></a>
            <a class="logout" href="<?= YOJAKA_BASE_URL; ?>/logout.php">Logout</a>
        </div>
    </header>
    <div class="body">
        <nav class="sidebar">
            <div class="nav-section">Main</div>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dashboard" class="nav-item<?= ($activePage ?? '') === 'dashboard' ? ' active' : ''; ?>">Dashboard</a>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=my_tasks" class="nav-item<?= ($activePage ?? '') === 'my_tasks' ? ' active' : ''; ?>">My Tasks</a>
            <?php if (user_has_permission('create_documents')): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=letters" class="nav-item<?= ($activePage ?? '') === 'letters' ? ' active' : ''; ?>">Letters &amp; Notices</a>
            <?php endif; ?>
            <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=global_search" class="nav-item<?= ($activePage ?? '') === 'global_search' ? ' active' : ''; ?>">Global Search</a>
            <?php if (is_module_enabled('rti') && (user_has_permission('manage_rti') || user_has_permission('view_reports_basic'))): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=rti" class="nav-item<?= ($activePage ?? '') === 'rti' ? ' active' : ''; ?>">RTI Cases</a>
            <?php endif; ?>
            <?php if (is_module_enabled('dak') && (user_has_permission('manage_dak') || user_has_permission('view_reports_basic'))): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dak" class="nav-item<?= ($activePage ?? '') === 'dak' ? ' active' : ''; ?>">Dak &amp; File Movement</a>
            <?php endif; ?>
            <?php if (is_module_enabled('inspection') && (user_has_permission('manage_inspection') || user_has_permission('view_reports_basic') || user_has_permission('create_documents'))): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=inspection" class="nav-item<?= ($activePage ?? '') === 'inspection' ? ' active' : ''; ?>">Inspection Reports</a>
            <?php endif; ?>
            <?php if (is_module_enabled('meeting_minutes') && user_has_permission('create_documents')): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=meeting_minutes" class="nav-item<?= ($activePage ?? '') === 'meeting_minutes' ? ' active' : ''; ?>">Meeting Minutes</a>
            <?php endif; ?>
            <?php if (is_module_enabled('work_orders') && user_has_permission('create_documents')): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=work_orders" class="nav-item<?= ($activePage ?? '') === 'work_orders' ? ' active' : ''; ?>">Work Orders</a>
            <?php endif; ?>
            <?php if (is_module_enabled('guc') && user_has_permission('create_documents')): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=guc" class="nav-item<?= ($activePage ?? '') === 'guc' ? ' active' : ''; ?>">Grant Utilization Certificates</a>
            <?php endif; ?>
            <?php if (is_module_enabled('bills') && user_has_permission('create_documents')): ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=bills" class="nav-item<?= ($activePage ?? '') === 'bills' ? ' active' : ''; ?>">Contractor Bills</a>
            <?php endif; ?>
            <?php if ($hasAdminMenu): ?>
                <div class="nav-section">Admin</div>
                <?php if (user_has_permission('manage_users')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_users" class="nav-item<?= ($activePage ?? '') === 'admin_users' ? ' active' : ''; ?>">User List</a>
                <?php endif; ?>
                <?php if (user_has_permission('manage_departments')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments" class="nav-item<?= ($activePage ?? '') === 'admin_departments' ? ' active' : ''; ?>">Department Profiles</a>
                <?php endif; ?>
                <?php if (user_has_permission('manage_office_config')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_office" class="nav-item<?= ($activePage ?? '') === 'admin_office' ? ' active' : ''; ?>">Office Settings</a>
                <?php endif; ?>
                <?php if (user_has_permission('manage_templates')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_letter_templates" class="nav-item<?= ($activePage ?? '') === 'admin_letter_templates' ? ' active' : ''; ?>">Letter Templates</a>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_documents" class="nav-item<?= ($activePage ?? '') === 'admin_documents' ? ' active' : ''; ?>">Document Templates</a>
                <?php endif; ?>
                <?php if (user_has_permission('manage_rti')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_rti" class="nav-item<?= ($activePage ?? '') === 'admin_rti' ? ' active' : ''; ?>">RTI Management</a>
                <?php endif; ?>
                <?php if (user_has_permission('manage_dak')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_dak" class="nav-item<?= ($activePage ?? '') === 'admin_dak' ? ' active' : ''; ?>">Dak Management</a>
                <?php endif; ?>
                <?php if (user_has_permission('manage_inspection')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_inspection" class="nav-item<?= ($activePage ?? '') === 'admin_inspection' ? ' active' : ''; ?>">Inspection Management</a>
                <?php endif; ?>
                <?php if (user_has_permission('view_all_records') || user_has_permission('manage_documents_repository')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_repository" class="nav-item<?= ($activePage ?? '') === 'admin_repository' ? ' active' : ''; ?>">Documents Repository</a>
                <?php endif; ?>
                <?php if (user_has_permission('view_logs')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_logs" class="nav-item<?= ($activePage ?? '') === 'admin_logs' ? ' active' : ''; ?>">Usage Logs</a>
                <?php endif; ?>
                <?php if (user_has_permission('view_all_records') || user_has_permission('view_mis_reports')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_mis" class="nav-item<?= ($activePage ?? '') === 'admin_mis' ? ' active' : ''; ?>">Reports &amp; Analytics (MIS)</a>
                <?php endif; ?>
                <?php if (user_has_permission('admin_backup')): ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_backup" class="nav-item<?= ($activePage ?? '') === 'admin_backup' ? ' active' : ''; ?>">Backup &amp; Export</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <main class="content">
            <div class="breadcrumb">Home &gt; <?= htmlspecialchars($pageTitle); ?></div>
            <h1><?= htmlspecialchars($pageTitle); ?></h1>
            <section class="panel">
                <?php if (!empty($viewFile) && file_exists($viewFile)) { include $viewFile; } else { ?>
                    <p>Page not found.</p>
                <?php } ?>
            </section>
        </main>
    </div>
    <footer class="footer">
        Powered by Dakshayani &bull; Yojaka v1.3
    </footer>
    <script>
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('ai-suggest-btn')) {
                e.preventDefault();
                alert('AI suggestions will be available in a future version.');
            }
        });
    </script>
</body>
</html>
