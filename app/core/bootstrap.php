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
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/department_users.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/auth.php';

// Ensure the default superadmin user exists for first-run setup.
yojaka_seed_superadmin();
