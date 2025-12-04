<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

if (!YOJAKA_INSTALLED) {
    header('Location: ' . YOJAKA_BASE_URL . '/install.php');
    exit;
}

require_login();

$page = $_GET['page'] ?? 'dashboard';
$route = resolve_route($page);
$pageTitle = $route['title'] ?? 'Yojaka';
$viewFile = $route['view'] ?? null;
$activePage = $page;

if (!empty($route['permission'])) {
    require_permission($route['permission']);
} elseif (!empty($route['role'])) {
    // Backward compatibility with older role-based routes
    require_role($route['role']);
}

if ($page === 'dashboard' && isset($_SESSION['username'])) {
    log_event('dashboard_view', $_SESSION['username']);
}

include __DIR__ . '/../app/views/layout.php';
