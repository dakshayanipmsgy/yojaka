<?php
// Main layout template
$user = current_user();
$pageTitle = $pageTitle ?? i18n_get('app.title');
$officeConfig = get_current_office_config();
$currentLicense = get_current_office_license();
$officeReadOnly = office_is_read_only($currentLicense);
$primaryColor = $officeConfig['theme']['primary_color'] ?? '#0f5aa5';
$secondaryColor = $officeConfig['theme']['secondary_color'] ?? '#f5f7fb';
$hasAdminMenu = user_has_permission('manage_users') || user_has_permission('manage_templates') || user_has_permission('manage_departments') || user_has_permission('view_logs') || user_has_permission('manage_rti') || user_has_permission('manage_dak') || user_has_permission('manage_inspection') || user_has_permission('admin_backup') || user_has_permission('manage_office_config') || user_has_permission('view_mis_reports') || user_has_permission('view_all_records') || user_has_permission('manage_housekeeping') || user_has_permission('manage_ai_settings') || user_has_permission('manage_reply_templates');
$isSuperAdmin = yojaka_current_user_role() === 'superadmin';
$unreadNotifications = $user ? count(get_unread_notifications_for_user($user['username'])) : 0;
$menuConfig = get_office_menu_config();
$availableLanguages = i18n_available_languages();
$currentLanguage = i18n_current_language();

// Render the requested view before sending any layout markup so redirects can run safely.
$viewContent = '';
if (!empty($viewFile) && file_exists($viewFile)) {
    ob_start();
    include $viewFile;
    $viewContent = ob_get_clean();
} else {
    $viewContent = '<p>Page not found.</p>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> - <?= htmlspecialchars(i18n_get('app.title')); ?></title>
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
                <div class="logo"><?= htmlspecialchars($officeConfig['office_name'] ?? i18n_get('app.title')); ?></div>
                <div class="subtitle">Govt Workflow &amp; Document Automation</div>
            </div>
        </div>
        <div class="user-meta">
            <div class="name">Logged in as: <?= htmlspecialchars($user['full_name'] ?? ''); ?></div>
            <div class="role badge"><?= htmlspecialchars($user['role'] ?? ''); ?></div>
            <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=change_language" style="display:inline-block; margin-right:8px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ($_SESSION['csrf_token'] = bin2hex(random_bytes(16)))); ?>" />
                <select name="lang" onchange="this.form.submit()">
                    <?php foreach ($availableLanguages as $langCode): ?>
                        <option value="<?= htmlspecialchars($langCode); ?>" <?= $langCode === $currentLanguage ? 'selected' : ''; ?>><?= htmlspecialchars(strtoupper($langCode)); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a class="logout" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=change_password"><?= htmlspecialchars(i18n_get('users.change_password')); ?></a>
            <a class="logout" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=notifications"><?= i18n_get('nav.notifications'); ?> <?= $unreadNotifications ? '(' . (int) $unreadNotifications . ')' : ''; ?></a>
            <a class="logout" href="<?= YOJAKA_BASE_URL; ?>/logout.php">Logout</a>
        </div>
    </header>
    <?php if ($officeReadOnly): ?>
        <div class="alert alert-danger" style="margin:0; border-radius:0;"><?= htmlspecialchars(i18n_get('banner.expired', ['date' => format_date_for_display($currentLicense['expires_at'] ?? '')])); ?></div>
    <?php elseif (is_license_trial($currentLicense ?? [])): ?>
        <div class="alert info" style="margin:0; border-radius:0;"><?= htmlspecialchars(i18n_get('banner.trial')); ?></div>
    <?php endif; ?>
    <div class="body">
        <nav class="sidebar">
            <div class="nav-section">Main</div>
            <?php
            $mainWhitelist = ['dashboard', 'my_tasks', 'global_search'];
            foreach ($menuConfig['main'] as $item):
                if (empty($item['visible'])) { continue; }
                $pageKey = $item['page'] ?? '';
                if (!in_array($pageKey, $mainWhitelist, true)) { continue; }
                $permissionKey = $item['permission'] ?? null;
                if (!user_has_permission($permissionKey)) { continue; }
                $label = i18n_get($item['label_key'] ?? $pageKey);
                ?>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=<?= htmlspecialchars($pageKey); ?>" class="nav-item<?= ($activePage ?? '') === $pageKey ? ' active' : ''; ?>"><?= htmlspecialchars($label); ?></a>
            <?php endforeach; ?>
            <?php if ($hasAdminMenu): ?>
                <div class="nav-section">Admin</div>
                <?php foreach ($menuConfig['admin'] as $item): ?>
                    <?php
                    if (empty($item['visible'])) { continue; }
                    $pageKey = $item['page'] ?? '';
                    if ($pageKey === 'admin_departments' && !$isSuperAdmin) { continue; }
                    $adminHidden = ['admin_rti', 'admin_dak', 'admin_inspection'];
                    if (in_array($pageKey, $adminHidden, true)) { continue; }
                    $permissionMap = [
                        'admin_users' => 'manage_users',
                        'admin_roles' => 'manage_users',
                        'admin_departments' => 'manage_departments',
                        'admin_office' => 'manage_office_config',
                        'admin_license' => 'manage_office_config',
                        'admin_letter_templates' => 'manage_templates',
                        'admin_documents' => 'manage_templates',
                        'admin_rti' => 'manage_rti',
                        'admin_dak' => 'manage_dak',
                        'admin_inspection' => 'manage_inspection',
                        'admin_master_data' => 'manage_office_config',
                        'admin_routes' => 'manage_office_config',
                        'admin_ai' => 'manage_ai_settings',
                        'admin_replies' => 'manage_reply_templates',
                        'admin_repository' => 'manage_documents_repository',
                        'admin_logs' => 'view_logs',
                        'admin_housekeeping' => 'manage_housekeeping',
                        'admin_mis' => 'view_mis_reports',
                        'admin_backup' => 'admin_backup',
                    ];
                    $requiredPermission = $permissionMap[$pageKey] ?? null;
                    if ($requiredPermission && !user_has_permission($requiredPermission)) { continue; }
                    if ($pageKey === 'admin_backup' && !license_feature_enabled($currentLicense, 'enable_backup')) {
                        echo '<span class="nav-item muted">' . htmlspecialchars(i18n_get($item['label_key'] ?? $pageKey)) . '</span>';
                        continue;
                    }
                    $label = i18n_get($item['label_key'] ?? $pageKey);
                    ?>
                    <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=<?= htmlspecialchars($pageKey); ?>" class="nav-item<?= ($activePage ?? '') === $pageKey ? ' active' : ''; ?>"><?= htmlspecialchars($label); ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($isSuperAdmin): ?>
                <div class="nav-section">Super Admin</div>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=superadmin_dashboard" class="nav-item<?= ($activePage ?? '') === 'superadmin_dashboard' ? ' active' : ''; ?>">Global Dashboard</a>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_departments" class="nav-item<?= ($activePage ?? '') === 'admin_departments' ? ' active' : ''; ?>">Departments</a>
                <a href="<?= YOJAKA_BASE_URL; ?>/app.php?page=superadmin_offices" class="nav-item<?= ($activePage ?? '') === 'superadmin_offices' ? ' active' : ''; ?>">Offices</a>
            <?php endif; ?>
        </nav>
        <main class="content">
            <div class="breadcrumb">Home &gt; <?= htmlspecialchars($pageTitle); ?></div>
            <h1><?= htmlspecialchars($pageTitle); ?></h1>
            <section class="panel">
                <?= $viewContent; ?>
            </section>
        </main>
    </div>
    <footer class="footer">
        Powered by Dakshayani &bull; Yojaka v1.5
    </footer>
    <?php if (is_license_trial($currentLicense ?? []) && !empty($currentLicense['watermark_text'])): ?>
        <div style="position:fixed; top:30%; left:0; right:0; text-align:center; opacity:0.1; font-size:64px; pointer-events:none; transform:rotate(-20deg); z-index:0;">
            <?= htmlspecialchars($currentLicense['watermark_text']); ?>
        </div>
    <?php endif; ?>
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
