<?php
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/bootstrap.php';

use App\Core\Router;

$environment = $GLOBALS['config']['environment'] ?? 'development';

try {
    $route = $_GET['route'] ?? '';

    if ($route === '') {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = $path === null ? '/' : $path;
        $path = trim($path, '/');

        if (strpos($path, 'public/') === 0) {
            $path = substr($path, strlen('public/'));
        } elseif ($path === 'public') {
            $path = '';
        }

        $route = $path;
    }

    $router = new Router();
    $router->route($route);
} catch (\Throwable $e) {
    if ($environment === 'development') {
        echo '<h1>Application Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    } else {
        echo 'An unexpected error occurred. Please try again later.';
    }
}
