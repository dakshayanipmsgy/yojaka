<?php
// Bootstrap file for Yojaka

// Start secure session early
if (session_status() === PHP_SESSION_NONE) {
    session_name('YOJAKA_SESSION');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Make configuration globally accessible
$GLOBALS['config'] = $config;

// Determine environment
$environment = $config['environment'] ?? 'development';

// Configure error reporting based on environment
if ($environment === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// Simple autoloader for classes under /app
spl_autoload_register(function ($class) use ($config) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strpos($class, $prefix) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
        $file = $baseDir . $relativePath;

        if (file_exists($file)) {
            require $file;
        }
    }
});

// Ensure essential data directories exist
$dataRoot = $config['paths']['data'] ?? __DIR__ . '/../data';
$dataPaths = [
    $dataRoot,
    $dataRoot . '/departments',
    $dataRoot . '/logs',
    $dataRoot . '/system',
];

foreach ($dataPaths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Bootstrap default superadmin if user store is missing
$usersFile = $dataRoot . '/system/users.json';
if (!file_exists($usersFile)) {
    $defaultPassword = 'changeMe123!';
    $defaultUser = [
        [
            'id' => '1',
            'username' => 'superadmin',
            'password_hash' => password_hash($defaultPassword, PASSWORD_DEFAULT),
            'role' => 'superadmin',
            'created_at' => date('c'),
        ],
    ];

    file_put_contents($usersFile, json_encode($defaultUser, JSON_PRETTY_PRINT), LOCK_EX);

    $_SESSION['default_superadmin_notice'] = 'Default Superadmin created. Username: superadmin, Password: changeMe123! Please change this later.';
}

// Determine base URL if not set
if (empty($config['base_url'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('index.php', '', $scriptName), '/');
    $config['base_url'] = $scheme . '://' . $host . $dir;
    $GLOBALS['config'] = $config;
}

// Convenience function to access config values
function app_config($key, $default = null)
{
    $config = $GLOBALS['config'] ?? [];
    return $config[$key] ?? $default;
}
