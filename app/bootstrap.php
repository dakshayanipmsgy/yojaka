<?php
// Bootstrap file to initialize configuration, sessions, and dependencies.

$config = require __DIR__ . '/../config/config.php';

$debug = !empty($config['debug']);

// Define base constants for reuse
$rootPath = $config['root_path'] ?? (__DIR__ . '/..');
$rootPath = realpath($rootPath) ?: $rootPath;
if (!defined('YOJAKA_ROOT')) {
    define('YOJAKA_ROOT', rtrim($rootPath, DIRECTORY_SEPARATOR));
}

$dataPath = $config['data_path'] ?? (YOJAKA_ROOT . '/data');
$dataPath = realpath($dataPath) ?: $dataPath;
if (!defined('YOJAKA_DATA_PATH')) {
    define('YOJAKA_DATA_PATH', rtrim($dataPath, DIRECTORY_SEPARATOR));
}

$logsPath = $config['logs_path'] ?? (YOJAKA_ROOT . '/logs');
$logsPath = realpath($logsPath) ?: $logsPath;
if (!defined('YOJAKA_LOGS_PATH')) {
    define('YOJAKA_LOGS_PATH', rtrim($logsPath, DIRECTORY_SEPARATOR));
}

if (!defined('YOJAKA_BASE_URL')) {
    define('YOJAKA_BASE_URL', rtrim($config['base_url'], '/'));
}

function bootstrap_ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

function bootstrap_seed_json(string $path, $data): void
{
    if (file_exists($path)) {
        return;
    }
    $dir = dirname($path);
    bootstrap_ensure_directory($dir);
    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function bootstrap_ensure_base_directories(): void
{
    $directories = [
        YOJAKA_DATA_PATH,
        YOJAKA_LOGS_PATH,
        YOJAKA_DATA_PATH . '/logs',
        YOJAKA_DATA_PATH . '/attachments',
        YOJAKA_DATA_PATH . '/attachments/rti',
        YOJAKA_DATA_PATH . '/attachments/dak',
        YOJAKA_DATA_PATH . '/attachments/inspection',
        YOJAKA_DATA_PATH . '/attachments/documents',
        YOJAKA_DATA_PATH . '/attachments/bills',
        YOJAKA_DATA_PATH . '/offices',
        YOJAKA_DATA_PATH . '/audit',
        YOJAKA_DATA_PATH . '/index',
        YOJAKA_DATA_PATH . '/index_v2',
        YOJAKA_DATA_PATH . '/i18n',
        YOJAKA_DATA_PATH . '/master',
        YOJAKA_DATA_PATH . '/portal',
        YOJAKA_DATA_PATH . '/qr',
        YOJAKA_DATA_PATH . '/org',
        YOJAKA_DATA_PATH . '/ai',
        YOJAKA_DATA_PATH . '/analytics',
        YOJAKA_DATA_PATH . '/replies',
    ];

    foreach ($directories as $dir) {
        bootstrap_ensure_directory($dir);
    }
}

function bootstrap_seed_permissions(): void
{
    $permissionsFile = YOJAKA_DATA_PATH . '/org/permissions.json';
    $defaultPermissions = [
        'roles' => [
            'superadmin' => ['*'],
            'admin' => [
                'manage_users',
                'manage_people',
                'manage_hierarchy',
                'manage_routes',
                'manage_master_data',
                'manage_templates',
                'manage_ai_settings',
                'manage_reply_templates',
                'view_all_files',
                'manage_departments',
                'view_logs',
                'manage_rti',
                'manage_dak',
                'manage_inspection',
                'view_reports_basic',
                'view_mis_reports',
                'admin_backup',
                'manage_office_config',
                'manage_bills',
                'manage_documents_repository',
                'manage_housekeeping',
            ],
            'officer' => [
                'view_assigned_files',
                'move_files',
                'edit_files',
                'manage_rti',
                'manage_dak',
                'manage_inspection',
                'view_reports_basic',
                'create_documents',
                'manage_bills',
                'manage_documents_repository',
            ],
            'clerk' => [
                'create_dak',
                'move_files',
                'view_assigned_files',
                'create_documents',
                'manage_dak',
                'view_reports_basic',
                'manage_bills',
            ],
        ],
        'custom_roles' => new stdClass(),
    ];

    bootstrap_seed_json($permissionsFile, $defaultPermissions);
}

function bootstrap_seed_default_users(array $config): void
{
    $usersFile = YOJAKA_DATA_PATH . '/users.json';
    $existing = [];
    if (file_exists($usersFile)) {
        $existing = json_decode((string) file_get_contents($usersFile), true) ?: [];
    }

    $admin = $config['default_admin'] ?? ['username' => 'admin', 'password' => 'admin123', 'full_name' => 'System Administrator'];
    if (empty($existing)) {
        $existing[] = [
            'id' => 1,
            'username' => $admin['username'],
            'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
            'role' => 'admin',
            'office_id' => 'office_001',
            'active' => true,
            'preferred_language' => 'en',
            'full_name' => $admin['full_name'] ?? 'System Administrator',
            'department_id' => 'dept_default',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'force_password_change' => true,
        ];
    }

    $hasSuperAdmin = false;
    foreach ($existing as $u) {
        if (($u['role'] ?? '') === 'superadmin') {
            $hasSuperAdmin = true;
            break;
        }
    }

    if (!$hasSuperAdmin) {
        $existing[] = [
            'id' => count($existing) + 1,
            'username' => 'superadmin',
            'password_hash' => password_hash('superadmin', PASSWORD_DEFAULT),
            'role' => 'superadmin',
            'office_id' => 'office_001',
            'active' => true,
            'preferred_language' => 'en',
            'full_name' => 'Super Administrator',
            'department_id' => 'dept_default',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];
    }

    bootstrap_seed_json($usersFile, $existing);
}

function bootstrap_seed_default_i18n(array $config): void
{
    $lang = $config['i18n_default_lang'] ?? 'en';
    $langFile = YOJAKA_DATA_PATH . '/i18n/' . $lang . '.json';
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
        'nav.admin_ai' => 'AI & Assistance Settings',
        'nav.admin_replies' => 'Reply Templates',
        'btn.save' => 'Save',
        'btn.cancel' => 'Cancel',
        'btn.search' => 'Search',
        'search.advanced' => 'Advanced Search',
        'search.keyword' => 'Keyword',
        'search.status' => 'Status',
        'search.date_from' => 'Date From',
        'search.date_to' => 'Date To',
        'btn.create_new' => 'Create New',
        'export.pdf' => 'Export PDF',
        'print.html' => 'Print HTML',
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
        'ai.draft_with_assistant' => 'Draft with Assistant',
        'ai.generate_summary' => 'Generate Summary',
        'ai.suggested_reply' => 'Suggested Reply',
        'ai.settings' => 'AI & Assistance Settings',
    ];

    bootstrap_seed_json($langFile, $seed);
}

require_once __DIR__ . '/initialize.php';

yojaka_initialize($config);

bootstrap_ensure_base_directories();
bootstrap_seed_default_i18n($config);
bootstrap_seed_permissions();
bootstrap_seed_default_users($config);
bootstrap_seed_json(YOJAKA_DATA_PATH . '/ai/config.json', [
    'enabled' => false,
    'provider' => 'stub',
    'endpoint_url' => '',
    'api_key' => '',
    'max_tokens' => 800,
    'temperature' => 0.3,
    'mask_personal_data' => true,
]);
bootstrap_seed_json(YOJAKA_DATA_PATH . '/analytics/route_stats.json', new stdClass());
bootstrap_seed_json(YOJAKA_DATA_PATH . '/replies/templates.json', []);

// Control error display for production safety
$shouldDisplayErrors = $debug || !empty($config['display_errors']);
ini_set('display_errors', $shouldDisplayErrors ? '1' : '0');
ini_set('display_startup_errors', $shouldDisplayErrors ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', YOJAKA_LOGS_PATH . '/error.log');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/logging.php';
require_once __DIR__ . '/list_helpers.php';
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/rendering.php';
require_once __DIR__ . '/office.php';
require_once __DIR__ . '/license.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/archive.php';
require_once __DIR__ . '/bills.php';
require_once __DIR__ . '/master_data.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/document_templates.php';
require_once __DIR__ . '/rti.php';
require_once __DIR__ . '/dak.php';
require_once __DIR__ . '/inspection.php';
require_once __DIR__ . '/mis_helpers.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/attachments.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/sla.php';
require_once __DIR__ . '/indexes.php';
require_once __DIR__ . '/index_v2.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/custom_fields.php';
require_once __DIR__ . '/ui_config.php';
require_once __DIR__ . '/portal_rate_limit.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/pdf_export.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/dashboard_config.php';
require_once __DIR__ . '/org_hierarchy.php';
bootstrap_seed_json(org_position_history_file(), []);
require_once __DIR__ . '/file_flow.php';
require_once __DIR__ . '/ai_assistant.php';
require_once __DIR__ . '/ai_prompts.php';
require_once __DIR__ . '/file_flow_ai.php';
require_once __DIR__ . '/reply_templates.php';

// Ensure logs directory exists early
ensure_logs_directory();

// Ensure backup directory exists
ensure_backup_directory_exists();

// Ensure office configuration exists
ensure_office_storage();
load_offices_registry();

// Ensure audit directory exists
ensure_audit_storage();
ensure_index_directory();

// Update timezone from office config if available
$currentOfficeId = get_current_office_id();
$officeConfig = load_office_config_by_id($currentOfficeId);
if (!empty($officeConfig['timezone'])) {
    @date_default_timezone_set($officeConfig['timezone']);
}
$GLOBALS['current_office_config'] = $officeConfig;
$GLOBALS['current_office_id'] = $currentOfficeId;
$GLOBALS['current_office_license'] = load_office_license($currentOfficeId);
$langToUse = i18n_determine_language();
i18n_set_current_language($langToUse);

// Ensure module storages exist
ensure_departments_storage();
ensure_rti_storage();
ensure_dak_storage();
ensure_inspection_storage();
ensure_documents_storage();
ensure_bills_storage();
ensure_attachments_storage();
ensure_notifications_storage();
ensure_qr_storage();

// Seed default admin user if necessary
create_default_admin_if_needed($config);

// Normalize user departments if missing
ensure_users_have_departments();

// Seed default letter templates if necessary
ensure_default_letter_templates();

// Seed default inspection templates if necessary
ensure_default_inspection_templates();

// Seed default document templates if necessary
ensure_default_document_templates();
