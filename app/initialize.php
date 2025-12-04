<?php
// Initialization helpers to make Yojaka self-booting without an installer.

function yojaka_normalize_path(string $path): string
{
    return rtrim($path, DIRECTORY_SEPARATOR);
}

function yojaka_ensure_directory(string $path): string
{
    $normalized = yojaka_normalize_path($path);
    if ($normalized === '') {
        return $normalized;
    }
    if (!is_dir($normalized)) {
        @mkdir($normalized, 0755, true);
    }
    return $normalized;
}

function yojaka_seed_file(string $path, $data): void
{
    if (file_exists($path)) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function yojaka_seed_users(array $config): void
{
    $usersFile = YOJAKA_DATA_PATH . '/users.json';
    if (file_exists($usersFile)) {
        return;
    }
    $admin = $config['default_admin'] ?? ['username' => 'admin', 'password' => 'admin123', 'full_name' => 'System Administrator'];
    $now = gmdate('c');
    $user = [
        'id' => 1,
        'username' => $admin['username'],
        'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
        'role' => 'admin',
        'office_id' => 'office_001',
        'preferred_language' => 'en',
        'full_name' => $admin['full_name'] ?? 'System Administrator',
        'department_id' => 'dept_default',
        'created_at' => $now,
        'active' => true,
    ];
    yojaka_seed_file($usersFile, [$user]);
}

function yojaka_seed_office_files(): void
{
    $officesDir = YOJAKA_DATA_PATH . '/offices';
    $officeId = 'office_001';
    $registryFile = $officesDir . '/offices.json';
    $officeConfigFile = $officesDir . '/' . $officeId . '.json';
    $licenseFile = $officesDir . '/license_' . $officeId . '.json';

    if (!file_exists($registryFile)) {
        $registry = [[
            'id' => $officeId,
            'name' => 'Default Office',
            'short_name' => 'DEFAULT',
            'active' => true,
            'config_file' => basename($officeConfigFile),
            'license_file' => basename($licenseFile),
            'created_at' => gmdate('c'),
        ]];
        yojaka_seed_file($registryFile, $registry);
    }

    if (!file_exists($officeConfigFile)) {
        $officeConfig = [
            'default_language' => 'en',
            'custom_fields' => new stdClass(),
            'ui' => ['menus' => new stdClass(), 'dashboard_widgets' => []],
        ];
        yojaka_seed_file($officeConfigFile, $officeConfig);
    }

    if (!file_exists($licenseFile)) {
        $license = [
            'license_key' => 'YOJAKA-TRIAL-DEFAULT',
            'type' => 'trial',
            'issue_date' => gmdate('c'),
            'expiry_date' => gmdate('c', strtotime('+30 days')),
        ];
        yojaka_seed_file($licenseFile, $license);
    }
}

function yojaka_seed_i18n(array $config): void
{
    $i18nDir = $config['i18n_data_path'] ?? (YOJAKA_DATA_PATH . '/i18n');
    $lang = $config['i18n_default_lang'] ?? 'en';
    $langFile = yojaka_normalize_path($i18nDir) . DIRECTORY_SEPARATOR . $lang . '.json';
    $seed = [
        'app.title' => 'Yojaka',
        'nav.dashboard' => 'Dashboard',
        'nav.letters_notices' => 'Letters & Notices',
        'nav.global_search' => 'Global Search',
        'nav.rti_cases' => 'RTI Cases',
        'nav.dak_file_movement' => 'Dak & File Movement',
        'nav.inspection_reports' => 'Inspection Reports',
        'nav.rti' => 'RTI Cases',
        'nav.dak' => 'Dak & File Movement',
        'nav.inspection' => 'Inspection Reports',
        'nav.documents' => 'Documents',
        'nav.contractor_bills' => 'Contractor Bills',
        'nav.bills' => 'Contractor Bills',
        'nav.my_tasks' => 'My Tasks',
        'nav.notifications' => 'Notifications',
        'nav.mis' => 'Reports & Analytics (MIS)',
        'nav.repository' => 'Documents Repository',
        'nav.housekeeping' => 'Housekeeping & Retention',
        'nav.license' => 'License & Trial Status',
        'nav.admin_users' => 'User Management',
        'nav.admin_departments' => 'Department Profiles',
        'nav.admin_office' => 'Office Settings',
        'nav.templates_letters' => 'Letter Templates',
        'nav.documents_templates' => 'Document Templates',
        'nav.admin_rti' => 'RTI Management',
        'nav.admin_dak' => 'Dak Management',
        'nav.admin_inspection' => 'Inspection Management',
        'btn.save' => 'Save',
        'btn.cancel' => 'Cancel',
        'btn.search' => 'Search',
        'btn.create_new' => 'Create New',
        'label.language' => 'Language',
        'label.office' => 'Office',
        'label.custom_fields' => 'Custom Fields',
        'banner.trial' => 'Trial version – not for production use.',
        'banner.expired' => 'Trial expired on {date}. System is read-only.',
        'validation.required' => '{field} is required.',
        'users.title' => 'User Management',
        'users.add_new' => 'Add New User',
        'users.username' => 'Username',
        'users.full_name' => 'Full Name',
        'users.role' => 'Role',
        'users.office' => 'Office',
        'users.department' => 'Department',
        'users.active' => 'Active',
        'users.reset_password' => 'Reset Password',
        'users.change_password' => 'Change Password',
        'users.current_password' => 'Current Password',
        'users.new_password' => 'New Password',
        'users.confirm_password' => 'Confirm Password',
        'users.password_changed' => 'Password changed successfully.',
        'users.password_reset_success' => 'Password has been reset. Please note the new password.',
        'users.force_password_change_message' => 'You must change your password before continuing.',
    ];
    yojaka_seed_file($langFile, $seed);
}

function yojaka_initialize(array &$config): void
{
    $basePaths = [
        'root_path', 'data_path', 'logs_path', 'i18n_data_path', 'offices_data_path', 'audit_data_path', 'index_data_path'
    ];
    foreach ($basePaths as $key) {
        if (!empty($config[$key])) {
            $config[$key] = yojaka_normalize_path($config[$key]);
        }
    }

    yojaka_ensure_directory(YOJAKA_DATA_PATH);
    yojaka_ensure_directory(YOJAKA_LOGS_PATH);

    $directories = [
        YOJAKA_DATA_PATH . '/logs',
        YOJAKA_DATA_PATH . '/attachments',
        YOJAKA_DATA_PATH . '/attachments/rti',
        YOJAKA_DATA_PATH . '/attachments/dak',
        YOJAKA_DATA_PATH . '/attachments/inspection',
        YOJAKA_DATA_PATH . '/attachments/documents',
        YOJAKA_DATA_PATH . '/attachments/bills',
        YOJAKA_DATA_PATH . '/attachments/misc',
        YOJAKA_DATA_PATH . '/notifications',
        YOJAKA_DATA_PATH . '/offices',
        YOJAKA_DATA_PATH . '/audit',
        YOJAKA_DATA_PATH . '/audit/rti',
        YOJAKA_DATA_PATH . '/audit/dak',
        YOJAKA_DATA_PATH . '/audit/bills',
        YOJAKA_DATA_PATH . '/audit/inspection',
        YOJAKA_DATA_PATH . '/audit/documents',
        YOJAKA_DATA_PATH . '/index',
        YOJAKA_DATA_PATH . '/i18n',
    ];

    foreach ($directories as $dir) {
        yojaka_ensure_directory($dir);
    }

    // Ensure PHP errors can be logged
    $errorLog = YOJAKA_LOGS_PATH . '/error.log';
    if (!file_exists($errorLog)) {
        @touch($errorLog);
    }

    yojaka_seed_users($config);
    yojaka_seed_office_files();
    yojaka_seed_i18n($config);
}
