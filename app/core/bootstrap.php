<?php
// Core bootstrap file for Yojaka.
// Loads configuration, sets up error handling, and prepares helper functions.

$config = require dirname(__DIR__, 2) . '/config/config.php';

// Start session early for authentication handling.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple configuration accessor.
function yojaka_config(string $key, $default = null)
{
    global $config;

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
        } else {
            return $default;
        }
    }

    return $value;
}

// Define BASE_URL for constructing links throughout the app.
if (!defined('BASE_URL')) {
    $baseUrl = rtrim(yojaka_config('base_url', ''), '/');
    define('BASE_URL', $baseUrl === '' ? '' : $baseUrl);
}

// Configure error reporting based on environment.
$environment = yojaka_config('environment', 'production');
if ($environment === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// Load helper functions used across the application.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/workflows.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/department_users.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/acl.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/attachments.php';
require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/dak.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/letters.php';
require_once __DIR__ . '/pdf.php';

function yojaka_migrate_dept_users_to_per_department(): void
{
    $dataPath = yojaka_config('data_path', yojaka_config('paths.data_path'));
    $usersFile = $dataPath . '/system/users.json';

    if (!file_exists($usersFile)) {
        return;
    }

    $users = json_decode(@file_get_contents($usersFile), true);
    if (!is_array($users)) {
        return;
    }

    $remaining = [];
    foreach ($users as $user) {
        $type = $user['user_type'] ?? null;
        $deptSlug = $user['department_slug'] ?? null;

        if ($type === 'dept_user' && $deptSlug) {
            yojaka_dept_users_ensure_storage($deptSlug);
            yojaka_dept_users_upsert($deptSlug, $user);
        } else {
            $remaining[] = $user;
        }
    }

    if (count($remaining) !== count($users)) {
        file_put_contents(
            $usersFile,
            json_encode($remaining, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}

function yojaka_migrate_dept_admin_accounts(): void
{
    $departments = yojaka_load_departments();

    foreach ($departments as $department) {
        $deptSlug = $department['slug'] ?? null;
        $deptName = $department['name'] ?? '';

        if ($deptSlug === null) {
            continue;
        }

        yojaka_users_ensure_dept_admin($deptSlug, $deptName);
    }
}

yojaka_migrate_dept_users_to_per_department();

// Ensure the default superadmin user exists for first-run setup.
yojaka_seed_superadmin();

// Repair or seed department admin accounts for all departments.
yojaka_migrate_dept_admin_accounts();

// Seed system templates used across modules (idempotent).
yojaka_templates_ensure_seeded();
