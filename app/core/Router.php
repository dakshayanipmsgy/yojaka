<?php
namespace App\Core;

class Router
{
    protected $defaultController = 'HomeController';
    protected $defaultAction = 'index';

    public function route($route)
    {
        $route = trim($route, '/');
        $route = $route === '' ? $this->defaultController . '/' . $this->defaultAction : $route;

        [$controllerName, $action] = array_pad(explode('/', $route), 2, null);

        $controllerName = $controllerName ? ucfirst($controllerName) . 'Controller' : $this->defaultController;
        $action = $action ?: $this->defaultAction;

        $controllerClass = 'App\\Controllers\\' . $controllerName;
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) {
            $this->renderNotFound('Controller not found');
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerClass)) {
            $this->renderNotFound('Controller class missing');
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            $this->renderNotFound('Action not found');
            return;
        }

        call_user_func([$controller, $action]);
    }

    protected function renderNotFound($message)
    {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 Not Found</title>';
        echo '<style>body{font-family:Arial, sans-serif;background:#f5f5f5;color:#333;text-align:center;padding:80px;}';
        echo 'h1{font-size:32px;margin-bottom:10px;}p{color:#666;}</style></head><body>';
        echo '<h1>Page Not Found</h1>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<p>The page you are looking for could not be found.</p>';
        echo '</body></html>';
    }
}
