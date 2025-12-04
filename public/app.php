<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

require_login();

$page = $_GET['page'] ?? 'dashboard';
$route = resolve_route($page);
$pageTitle = $route['title'] ?? 'Yojaka';
$viewFile = $route['view'] ?? null;
$activePage = $page;

include __DIR__ . '/../app/views/layout.php';
