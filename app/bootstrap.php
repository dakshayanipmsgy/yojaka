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

require_once __DIR__ . '/initialize.php';

yojaka_initialize($config);

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
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/archive.php';
require_once __DIR__ . '/bills.php';
require_once __DIR__ . '/auth.php';
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
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/custom_fields.php';
require_once __DIR__ . '/ui_config.php';

// Ensure logs directory exists early
ensure_logs_directory();

$i18nDir = i18n_data_path();
if (!is_dir($i18nDir)) {
    @mkdir($i18nDir, 0755, true);
}
$defaultLangPath = $i18nDir . DIRECTORY_SEPARATOR . ($config['i18n_default_lang'] ?? 'en') . '.json';
if (!file_exists($defaultLangPath)) {
    $seed = [
        'app.title' => 'Yojaka',
        'nav.dashboard' => 'Dashboard',
        'nav.rti' => 'RTI Cases',
        'nav.dak' => 'Dak & File Movement',
        'nav.inspection' => 'Inspection Reports',
        'nav.documents' => 'Documents',
        'nav.bills' => 'Contractor Bills',
        'nav.my_tasks' => 'My Tasks',
        'nav.notifications' => 'Notifications',
        'nav.mis' => 'Reports & Analytics (MIS)',
        'nav.global_search' => 'Global Search',
        'nav.repository' => 'Documents Repository',
        'nav.housekeeping' => 'Housekeeping & Retention',
        'nav.license' => 'License & Trial Status',
        'nav.admin_users' => 'User List',
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
    ];
    @file_put_contents($defaultLangPath, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Ensure backup directory exists
ensure_backup_directory_exists();

// Ensure office configuration exists
ensure_office_storage();

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
