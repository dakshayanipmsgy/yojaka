<?php
// Maintenance script to reset authentication data to a clean state.
// Backs up existing user/permission data, then recreates a single superadmin
// user and base role templates. Use with caution.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../users.php';
require_once __DIR__ . '/../departments.php';
require_once __DIR__ . '/../roles.php';

function yojaka_backup_file(string $sourcePath, string $backupDir, string $prefix, string $timestamp): ?string
{
    if (!file_exists($sourcePath)) {
        return null;
    }

    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0770, true);
    }

    $target = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . '_backup_' . $timestamp . '.json';
    if (@copy($sourcePath, $target)) {
        return $target;
    }

    return null;
}

function yojaka_detect_users_shape($raw): string
{
    if (!is_array($raw)) {
        return 'list';
    }
    $isAssoc = array_keys($raw) !== range(0, count($raw) - 1);
    return $isAssoc ? 'map' : 'list';
}

$usersPath = users_data_path();
$permissionsPath = permissions_config_path();
$departmentsPath = departments_path();
$backupDir = YOJAKA_DATA_PATH . '/backups';
$timestamp = gmdate('Ymd_His');

$usersBackup = yojaka_backup_file($usersPath, $backupDir, 'users', $timestamp);
$permissionsBackup = yojaka_backup_file($permissionsPath, $backupDir, 'permissions', $timestamp);
$departmentsBackup = yojaka_backup_file($departmentsPath, $backupDir, 'departments', $timestamp);

$rawUsers = [];
if (file_exists($usersPath)) {
    $rawUsers = json_decode((string) file_get_contents($usersPath), true);
}
$userShape = yojaka_detect_users_shape($rawUsers);

$now = gmdate('c');
$superadminPassword = 'ChangeMe!123';
$superadminUser = [
    'id' => 1,
    'username' => 'superadmin',
    'full_name' => 'System Superadmin',
    'password_hash' => password_hash($superadminPassword, PASSWORD_DEFAULT),
    'role' => 'superadmin',
    'active' => true,
    'force_password_change' => true,
    'created_at' => $now,
    'updated_at' => $now,
    'office_id' => 'office_001',
    'department_id' => 'dept_default',
];

$usersPayload = $userShape === 'map' ? ['superadmin' => $superadminUser] : [$superadminUser];

if ($userShape === 'list') {
    $saved = save_users($usersPayload);
} else {
    $json = json_encode($usersPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $saved = (bool) file_put_contents($usersPath, $json, LOCK_EX);
}

$permissionsPayload = [
    'roles' => [
        'superadmin' => [
            'label' => 'Superadmin',
            'permissions' => [
                '*',
                'system.manage_departments',
                'system.manage_templates',
                'system.view_requests',
                'system.approve_requests',
                'system.view_stats',
                'auth.manage_superadmin',
                'auth.manage_global_roles',
            ],
        ],
        'dept_admin' => [
            'label' => 'Department Admin (Base)',
            'permissions' => [
                'dept.manage_roles',
                'dept.manage_users',
                'dept.manage_templates',
                'dept.view_stats',
                'dept.raise_requests',
            ],
        ],
    ],
    'custom_roles' => [],
];

save_permissions_config($permissionsPayload);

echo "Auth reset complete.\n";
if ($usersBackup) {
    echo "Users backup: {$usersBackup}\n";
}
if ($permissionsBackup) {
    echo "Permissions backup: {$permissionsBackup}\n";
}
if ($departmentsBackup) {
    echo "Departments backup: {$departmentsBackup}\n";
}
echo "New superadmin credentials: superadmin / {$superadminPassword} (change immediately).\n";
