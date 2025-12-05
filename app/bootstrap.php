<?php
// Bootstrap file for Yojaka

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
$dataPaths = [
    $config['paths']['data'] ?? __DIR__ . '/../data',
    ($config['paths']['data'] ?? __DIR__ . '/../data') . '/departments',
    ($config['paths']['data'] ?? __DIR__ . '/../data') . '/logs',
];

foreach ($dataPaths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
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
