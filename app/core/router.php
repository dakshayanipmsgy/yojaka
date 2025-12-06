<?php
// Simple router for Yojaka using query parameter based routing.

/**
 * Determine the current route string from the request.
 */
function yojaka_route(): string
{
    $route = isset($_GET['r']) ? trim($_GET['r']) : '';
    return $route === '' ? 'home' : $route;
}

/**
 * Dispatch the route to the appropriate controller.
 */
function yojaka_dispatch(string $route)
{
    $controllerMap = [
        'home' => ['HomeController', 'index'],
        'about' => ['AboutController', 'index'],
        'auth/login' => ['AuthController', 'login'],
        'auth/logout' => ['AuthController', 'logout'],
        'superadmin/dashboard' => ['SuperadminController', 'dashboard'],
        'deptadmin/dashboard' => ['DeptAdminController', 'dashboard'],
        'deptadmin/roles/create' => ['DeptAdminController', 'roles_create'],
        'deptadmin/users' => ['DeptUsersController', 'index'],
        'deptadmin/users/create' => ['DeptUsersController', 'create'],
        'deptadmin/users/edit' => ['DeptUsersController', 'edit'],
        'deptadmin/users/password' => ['DeptUsersController', 'password'],
        'deptuser/dashboard' => ['DeptUserController', 'dashboard'],
        'dak/list' => ['DakController', 'list'],
        'dak/create' => ['DakController', 'create'],
        'dak/view' => ['DakController', 'view'],
        'dak/edit' => ['DakController', 'edit'],
    ];

    if (!isset($controllerMap[$route])) {
        http_response_code(404);
        return yojaka_render_view('errors/404', ['route' => $route], 'main');
    }

    [$className, $method] = $controllerMap[$route];
    $controllerFile = yojaka_config('paths.app_path') . '/controllers/' . $className . '.php';

    if (!file_exists($controllerFile)) {
        http_response_code(404);
        return yojaka_render_view('errors/404', ['route' => $route], 'main');
    }

    require_once $controllerFile;

    if (!class_exists($className)) {
        http_response_code(500);
        return yojaka_render_view('errors/500', ['message' => 'Controller not found.'], 'main');
    }

    $controller = new $className();

    if (!method_exists($controller, $method)) {
        http_response_code(404);
        return yojaka_render_view('errors/404', ['route' => $route], 'main');
    }

    return call_user_func([$controller, $method]);
}
