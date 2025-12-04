<?php
// Bootstrap file to initialize configuration, sessions, and dependencies.

$config = require __DIR__ . '/../config/config.php';
$installedFlag = !empty($config['installed']);

// Control error display for production safety
ini_set('display_errors', $config['display_errors'] ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base constants for reuse
if (!defined('YOJAKA_ROOT')) {
    define('YOJAKA_ROOT', $config['root_path']);
}
if (!defined('YOJAKA_DATA_PATH')) {
    define('YOJAKA_DATA_PATH', $config['data_path']);
}
if (!defined('YOJAKA_LOGS_PATH')) {
    define('YOJAKA_LOGS_PATH', $config['logs_path']);
}
if (!defined('YOJAKA_BASE_URL')) {
    define('YOJAKA_BASE_URL', rtrim($config['base_url'], '/'));
}
if (!defined('YOJAKA_INSTALLED')) {
    define('YOJAKA_INSTALLED', $installedFlag);
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

// Ensure logs directory exists early
ensure_logs_directory();

if (YOJAKA_INSTALLED) {
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
}
