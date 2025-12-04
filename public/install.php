<?php
// Yojaka installer / setup wizard
session_start();

$configFile = __DIR__ . '/../config/config.php';
$baseConfig = file_exists($configFile) ? require $configFile : [
    'installed' => false,
    'base_url' => '/yojaka/public',
    'root_path' => realpath(__DIR__ . '/..'),
    'data_path' => realpath(__DIR__ . '/../data'),
    'logs_path' => realpath(__DIR__ . '/../logs'),
    'usage_log_file' => 'usage.log',
    'templates_path' => __DIR__ . '/../data/templates',
    'letters_templates_file' => 'letters.json',
    'document_templates_file' => 'documents.json',
    'generated_letters_log' => 'generated_letters.log',
    'documents_data_path' => __DIR__ . '/../data/documents',
    'documents_meeting_minutes_file' => 'meeting_minutes.json',
    'documents_work_orders_file' => 'work_orders.json',
    'documents_guc_file' => 'guc.json',
    'office_data_path' => __DIR__ . '/../data/office',
    'office_config_file' => 'office.json',
    'bills_data_path' => __DIR__ . '/../data/bills',
    'bills_file' => 'bills.json',
    'departments_data_path' => __DIR__ . '/../data/departments',
    'departments_file' => 'departments.json',
    'dak_data_path' => __DIR__ . '/../data/dak',
    'dak_entries_file' => 'dak_entries.json',
    'dak_overdue_days' => 7,
    'rti_data_path' => __DIR__ . '/../data/rti',
    'rti_cases_file' => 'rti_cases.json',
    'rti_reply_days' => 30,
    'inspection_data_path' => __DIR__ . '/../data/inspection',
    'inspection_templates_file' => 'templates.json',
    'inspection_reports_file' => 'reports.json',
    'pagination_per_page' => 10,
    'logs_pagination_per_page' => 50,
    'backup_path' => realpath(__DIR__ . '/..') . '/backup',
    'backup_include_data' => true,
    'backup_include_config' => true,
    'roles_permissions' => [
        'admin' => [
            'manage_users', 'manage_departments', 'manage_templates', 'view_logs', 'manage_rti', 'manage_dak', 'manage_inspection', 'create_documents', 'view_all_records', 'view_reports_basic', 'admin_backup', 'manage_office_config', 'manage_bills'
        ],
        'officer' => ['create_documents', 'manage_rti', 'manage_dak', 'manage_inspection', 'view_reports_basic', 'manage_bills'],
        'clerk' => ['create_documents', 'manage_dak', 'view_reports_basic', 'manage_bills'],
        'viewer' => ['view_reports_basic'],
    ],
    'default_admin' => [
        'username' => 'admin',
        'password' => 'admin123',
        'full_name' => 'System Administrator'
    ],
    'display_errors' => false,
];

if (!empty($baseConfig['installed'])) {
    header('Location: ' . rtrim($baseConfig['base_url'], '/') . '/index.php');
    exit;
}

define('YOJAKA_ROOT', $baseConfig['root_path']);
define('YOJAKA_BASE_URL', rtrim($baseConfig['base_url'], '/'));
define('YOJAKA_DATA_PATH', $baseConfig['data_path']);
define('YOJAKA_LOGS_PATH', $baseConfig['logs_path']);

$step = isset($_GET['step']) ? max(1, (int) $_GET['step']) : 1;
$wizard = $_SESSION['install_wizard'] ?? [];
$csrf = $_SESSION['install_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['install_csrf'] = $csrf;

function check_requirements(array $paths): array
{
    $results = [];
    foreach ($paths as $label => $path) {
        $results[] = [
            'label' => $label,
            'path' => $path,
            'exists' => file_exists($path),
            'writable' => is_writable($path) || (!file_exists($path) && is_writable(dirname($path))),
        ];
    }
    return $results;
}

function write_config_file(string $path, array $config): bool
{
    $export = "<?php\nreturn " . var_export($config, true) . ";\n";
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $export);
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

function write_json_if_missing(string $path, $data): void
{
    if (!file_exists($path)) {
        $handle = @fopen($path, 'c+');
        if ($handle && flock($handle, LOCK_EX)) {
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        if ($handle) {
            fclose($handle);
        }
    }
}

function write_htaccess(string $dir): void
{
    $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($file)) {
        @file_put_contents($file, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }
}

function seed_basic_files(array $config, array $wizard): void
{
    $dataPath = $config['data_path'];
    ensure_dir($dataPath);
    write_htaccess($dataPath);

    $logsPath = $config['logs_path'];
    ensure_dir($logsPath);
    write_htaccess($logsPath);

    ensure_dir($config['backup_path']);
    write_htaccess($config['backup_path']);

    $officeDir = $config['office_data_path'];
    ensure_dir($officeDir);
    write_htaccess($officeDir);

    $billsDir = $config['bills_data_path'];
    ensure_dir($billsDir);
    write_htaccess($billsDir);

    $deptDir = $config['departments_data_path'];
    ensure_dir($deptDir);
    write_htaccess($deptDir);

    $templatesDir = $config['templates_path'];
    ensure_dir($templatesDir);
    write_htaccess($templatesDir);

    $dakDir = $config['dak_data_path'];
    ensure_dir($dakDir);
    write_htaccess($dakDir);

    $rtiDir = $config['rti_data_path'];
    ensure_dir($rtiDir);
    write_htaccess($rtiDir);

    $inspectionDir = $config['inspection_data_path'];
    ensure_dir($inspectionDir);
    write_htaccess($inspectionDir);

    $documentsDir = $config['documents_data_path'];
    ensure_dir($documentsDir);
    write_htaccess($documentsDir);

    $officeConfig = [
        'office_name' => $wizard['office_name'],
        'office_short_name' => $wizard['office_short_name'],
        'base_url' => $wizard['base_url'],
        'date_format_php' => $wizard['date_format'],
        'timezone' => $wizard['timezone'],
        'id_prefixes' => [
            'rti' => 'RTI',
            'dak' => 'DAK',
            'inspection' => 'INSP',
            'bill' => 'BILL',
            'meeting_minutes' => 'MM',
            'work_order' => 'WO',
            'guc' => 'GUC',
        ],
        'theme' => [
            'primary_color' => '#0f5aa5',
            'secondary_color' => '#f5f7fb',
            'logo_path' => $wizard['dept_logo'] ?? '',
        ],
        'modules' => [
            'enable_rti' => true,
            'enable_dak' => true,
            'enable_inspection' => true,
            'enable_bills' => true,
            'enable_meeting_minutes' => true,
            'enable_work_orders' => true,
            'enable_guc' => true,
        ],
    ];
    write_json_if_missing($officeDir . '/office.json', $officeConfig);

    $department = [
        'id' => $wizard['dept_id'],
        'name' => $wizard['dept_name'],
        'address' => $wizard['dept_address'],
        'contact' => $wizard['dept_contact'],
        'logo_path' => $wizard['dept_logo'] ?? '',
        'letterhead_header_html' => $wizard['dept_header'],
        'letterhead_footer_html' => $wizard['dept_footer'],
        'default_signatory_block' => $wizard['dept_signatory'],
        'is_default' => true,
    ];
    write_json_if_missing($deptDir . '/' . $config['departments_file'], [$department]);

    $adminUser = [
        'id' => 1,
        'username' => $wizard['admin_username'],
        'password_hash' => password_hash($wizard['admin_password'], PASSWORD_DEFAULT),
        'role' => 'admin',
        'department_id' => $wizard['dept_id'],
        'full_name' => $wizard['admin_full_name'],
        'email' => $wizard['admin_email'],
        'created_at' => gmdate('c'),
        'active' => true,
    ];
    write_json_if_missing($dataPath . '/users.json', [$adminUser]);

    write_json_if_missing($config['bills_data_path'] . '/' . $config['bills_file'], []);
    write_json_if_missing($config['dak_data_path'] . '/' . $config['dak_entries_file'], []);
    write_json_if_missing($config['rti_data_path'] . '/' . $config['rti_cases_file'], []);
    write_json_if_missing($config['inspection_data_path'] . '/' . $config['inspection_templates_file'], []);
    write_json_if_missing($config['inspection_data_path'] . '/' . $config['inspection_reports_file'], []);
    write_json_if_missing($config['documents_data_path'] . '/' . $config['documents_meeting_minutes_file'], []);
    write_json_if_missing($config['documents_data_path'] . '/' . $config['documents_work_orders_file'], []);
    write_json_if_missing($config['documents_data_path'] . '/' . $config['documents_guc_file'], []);
    write_json_if_missing($config['templates_path'] . '/' . $config['letters_templates_file'], []);
    write_json_if_missing($config['templates_path'] . '/' . $config['document_templates_file'], []);
}

function log_install_event(array $config): void
{
    $logFile = rtrim($config['logs_path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ($config['usage_log_file'] ?? 'usage.log');
    $entry = [
        'timestamp' => gmdate('c'),
        'event' => 'system_install_completed',
        'username' => 'installer',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => new stdClass(),
    ];
    $handle = @fopen($logFile, 'a');
    if ($handle && flock($handle, LOCK_EX)) {
        fwrite($handle, json_encode($entry) . "\n");
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    if ($handle) {
        fclose($handle);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        die('Security token mismatch');
    }

    if ($step === 2) {
        $wizard['office_name'] = trim($_POST['office_name'] ?? '');
        $wizard['office_short_name'] = trim($_POST['office_short_name'] ?? '');
        $wizard['office_address'] = trim($_POST['office_address'] ?? '');
        $wizard['office_contact'] = trim($_POST['office_contact'] ?? '');
        $wizard['base_url'] = trim($_POST['base_url'] ?? $baseConfig['base_url']);
        $wizard['date_format'] = trim($_POST['date_format'] ?? 'd-m-Y');
        $wizard['timezone'] = trim($_POST['timezone'] ?? 'Asia/Kolkata');
        $_SESSION['install_wizard'] = $wizard;
        header('Location: install.php?step=3');
        exit;
    }
    if ($step === 3) {
        $wizard['dept_id'] = trim($_POST['dept_id'] ?? 'main_office');
        $wizard['dept_name'] = trim($_POST['dept_name'] ?? $wizard['office_name']);
        $wizard['dept_address'] = trim($_POST['dept_address'] ?? $wizard['office_address']);
        $wizard['dept_contact'] = trim($_POST['dept_contact'] ?? $wizard['office_contact']);
        $wizard['dept_logo'] = trim($_POST['dept_logo'] ?? '');
        $wizard['dept_header'] = trim($_POST['dept_header'] ?? '');
        $wizard['dept_footer'] = trim($_POST['dept_footer'] ?? '');
        $wizard['dept_signatory'] = trim($_POST['dept_signatory'] ?? '<p><em>Authorized Signatory</em></p>');
        $_SESSION['install_wizard'] = $wizard;
        header('Location: install.php?step=4');
        exit;
    }
    if ($step === 4) {
        $adminPass = $_POST['admin_password'] ?? '';
        $confirm = $_POST['admin_confirm'] ?? '';
        if ($adminPass === '' || $adminPass !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $wizard['admin_username'] = trim($_POST['admin_username'] ?? 'admin');
            $wizard['admin_full_name'] = trim($_POST['admin_full_name'] ?? 'Administrator');
            $wizard['admin_email'] = trim($_POST['admin_email'] ?? '');
            $wizard['admin_password'] = $adminPass;
            $_SESSION['install_wizard'] = $wizard;
            header('Location: install.php?step=5');
            exit;
        }
    }
    if ($step === 5) {
        $finalConfig = $baseConfig;
        $finalConfig['installed'] = true;
        $finalConfig['base_url'] = $wizard['base_url'];
        $finalConfig['root_path'] = realpath(__DIR__ . '/..');
        $finalConfig['data_path'] = realpath(__DIR__ . '/../data');
        $finalConfig['logs_path'] = realpath(__DIR__ . '/../logs');
        $finalConfig['backup_path'] = realpath(__DIR__ . '/..') . '/backup';

        seed_basic_files($finalConfig, $wizard);
        write_config_file($configFile, $finalConfig);
        log_install_event($finalConfig);

        unset($_SESSION['install_wizard'], $_SESSION['install_csrf']);
        header('Location: ' . rtrim($wizard['base_url'], '/') . '/login.php');
        exit;
    }
}

function installer_header(string $title): void
{
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Yojaka Installer</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="auth-page">';
    echo '<div class="auth-card" style="max-width:720px;width:100%">';
    echo '<div class="auth-brand">Yojaka v1.0 Setup</div>';
    echo '<div class="auth-subtitle">' . htmlspecialchars($title) . '</div>';
}

function installer_footer(): void
{
    echo '</div></body></html>';
}

switch ($step) {
    case 1:
        installer_header('Welcome & Requirements Check');
        $checks = check_requirements([
            'Config directory' => __DIR__ . '/../config',
            'Data directory' => __DIR__ . '/../data',
            'Logs directory' => __DIR__ . '/../logs',
            'Backup directory' => __DIR__ . '/../backup',
        ]);
        echo '<p>Yojaka stores its configuration and records on disk. Please ensure the following paths are writable.</p>';
        echo '<table class="table"><tr><th>Path</th><th>Exists</th><th>Writable</th></tr>';
        foreach ($checks as $check) {
            echo '<tr><td>' . htmlspecialchars($check['label'] . ' (' . $check['path'] . ')') . '</td><td>' . ($check['exists'] ? 'Yes' : 'No') . '</td><td>' . ($check['writable'] ? 'Yes' : 'No') . '</td></tr>';
        }
        echo '</table>';
        $allGood = array_reduce($checks, function ($carry, $item) { return $carry && $item['writable']; }, true);
        echo '<div class="form-actions">';
        if ($allGood) {
            echo '<a class="btn-primary" href="install.php?step=2">Continue</a>';
        } else {
            echo '<div class="alert alert-danger">Please fix permissions before continuing.</div>';
        }
        echo '</div>';
        installer_footer();
        break;

    case 2:
        installer_header('Office / Instance Settings');
        $baseUrl = $wizard['base_url'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        ?>
        <form method="post" class="form-stacked">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <div class="form-field">
                <label>Office Name</label>
                <input type="text" name="office_name" required value="<?= htmlspecialchars($wizard['office_name'] ?? ''); ?>">
            </div>
            <div class="form-field">
                <label>Office Short Name (for IDs)</label>
                <input type="text" name="office_short_name" required value="<?= htmlspecialchars($wizard['office_short_name'] ?? 'MAIN'); ?>">
            </div>
            <div class="form-field">
                <label>Default Address</label>
                <textarea name="office_address" required><?= htmlspecialchars($wizard['office_address'] ?? ''); ?></textarea>
            </div>
            <div class="form-field">
                <label>Default Contact</label>
                <input type="text" name="office_contact" value="<?= htmlspecialchars($wizard['office_contact'] ?? ''); ?>">
            </div>
            <div class="form-field">
                <label>Base URL</label>
                <input type="text" name="base_url" required value="<?= htmlspecialchars($baseUrl ?: $baseConfig['base_url']); ?>">
            </div>
            <div class="form-field">
                <label>Date format (PHP)</label>
                <input type="text" name="date_format" value="<?= htmlspecialchars($wizard['date_format'] ?? 'd-m-Y'); ?>">
            </div>
            <div class="form-field">
                <label>Timezone</label>
                <input type="text" name="timezone" value="<?= htmlspecialchars($wizard['timezone'] ?? 'Asia/Kolkata'); ?>">
            </div>
            <div class="form-actions">
                <a class="button" href="install.php?step=1">Back</a>
                <button class="btn-primary" type="submit">Save &amp; Continue</button>
            </div>
        </form>
        <?php
        installer_footer();
        break;

    case 3:
        installer_header('Initial Department Profile');
        ?>
        <form method="post" class="form-stacked">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <div class="form-field">
                <label>Department ID</label>
                <input type="text" name="dept_id" required value="<?= htmlspecialchars($wizard['dept_id'] ?? 'main_office'); ?>">
            </div>
            <div class="form-field">
                <label>Department Name</label>
                <input type="text" name="dept_name" required value="<?= htmlspecialchars($wizard['dept_name'] ?? ($wizard['office_name'] ?? '')); ?>">
            </div>
            <div class="form-field">
                <label>Address</label>
                <textarea name="dept_address" required><?= htmlspecialchars($wizard['dept_address'] ?? ($wizard['office_address'] ?? '')); ?></textarea>
            </div>
            <div class="form-field">
                <label>Contact</label>
                <input type="text" name="dept_contact" value="<?= htmlspecialchars($wizard['dept_contact'] ?? ($wizard['office_contact'] ?? '')); ?>">
            </div>
            <div class="form-field">
                <label>Logo Path (optional)</label>
                <input type="text" name="dept_logo" value="<?= htmlspecialchars($wizard['dept_logo'] ?? ''); ?>">
            </div>
            <div class="form-field">
                <label>Header HTML</label>
                <textarea name="dept_header"><?= htmlspecialchars($wizard['dept_header'] ?? '<div class="letterhead-block"><strong>' . ($wizard['office_name'] ?? 'Office') . '</strong><div>' . ($wizard['office_address'] ?? '') . '</div></div>'); ?></textarea>
            </div>
            <div class="form-field">
                <label>Footer HTML</label>
                <textarea name="dept_footer"><?= htmlspecialchars($wizard['dept_footer'] ?? '<div class="letterhead-block">System generated by Yojaka</div>'); ?></textarea>
            </div>
            <div class="form-field">
                <label>Default Signatory Block</label>
                <textarea name="dept_signatory"><?= htmlspecialchars($wizard['dept_signatory'] ?? '<p><em>Authorized Signatory</em></p>'); ?></textarea>
            </div>
            <div class="form-actions">
                <a class="button" href="install.php?step=2">Back</a>
                <button class="btn-primary" type="submit">Save &amp; Continue</button>
            </div>
        </form>
        <?php
        installer_footer();
        break;

    case 4:
        installer_header('Admin User Setup');
        ?>
        <form method="post" class="form-stacked">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <div class="form-field">
                <label>Admin Username</label>
                <input type="text" name="admin_username" required value="<?= htmlspecialchars($wizard['admin_username'] ?? 'admin'); ?>">
            </div>
            <div class="form-field">
                <label>Full Name</label>
                <input type="text" name="admin_full_name" required value="<?= htmlspecialchars($wizard['admin_full_name'] ?? 'System Administrator'); ?>">
            </div>
            <div class="form-field">
                <label>Email (optional)</label>
                <input type="email" name="admin_email" value="<?= htmlspecialchars($wizard['admin_email'] ?? ''); ?>">
            </div>
            <div class="form-field">
                <label>Password</label>
                <input type="password" name="admin_password" required>
            </div>
            <div class="form-field">
                <label>Confirm Password</label>
                <input type="password" name="admin_confirm" required>
            </div>
            <div class="form-actions">
                <a class="button" href="install.php?step=3">Back</a>
                <button class="btn-primary" type="submit">Save &amp; Continue</button>
            </div>
        </form>
        <?php
        if (!empty($error)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
        }
        installer_footer();
        break;

    default:
        installer_header('Summary & Finalization');
        ?>
        <div class="info">Review the information below and click Install to finalize the setup.</div>
        <div class="detail-grid">
            <div>
                <div class="muted">Office Name</div>
                <div class="strong"><?= htmlspecialchars($wizard['office_name'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Base URL</div>
                <div class="strong"><?= htmlspecialchars($wizard['base_url'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Department</div>
                <div class="strong"><?= htmlspecialchars($wizard['dept_name'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Admin User</div>
                <div class="strong"><?= htmlspecialchars($wizard['admin_username'] ?? ''); ?></div>
            </div>
        </div>
        <form method="post" class="form-actions" style="margin-top:1rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <a class="button" href="install.php?step=4">Back</a>
            <button class="btn-primary" type="submit">Install</button>
        </form>
        <?php
        installer_footer();
        break;
}
?>
