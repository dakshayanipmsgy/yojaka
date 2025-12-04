<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

$page = $_GET['page'] ?? 'dashboard';
$route = resolve_route($page);
$pageTitle = $route['title'] ?? 'Yojaka';
$viewFile = $route['view'] ?? null;
$activePage = $page;
$requiresAuth = $route['requires_auth'] ?? true;
$useLayout = $route['layout'] ?? true;

if (!$requiresAuth && $page === 'login' && is_logged_in()) {
    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dashboard');
    exit;
}

if ($requiresAuth) {
    require_login();
}

if (!empty($route['permission']) && $requiresAuth) {
    require_permission($route['permission']);
} elseif (!empty($route['role']) && $requiresAuth) {
    // Backward compatibility with older role-based routes
    require_role($route['role']);
}

// Handle login inside the routed app
if ($page === 'login') {
    $error = false;
    $csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
    $_SESSION['csrf_token'] = $csrf_token;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!$submittedToken || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            $error = true;
            log_event('login_failure', $_POST['username'] ?? null, ['reason' => 'csrf_mismatch']);
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($username !== '' && $password !== '' && login($username, $password)) {
                unset($_SESSION['csrf_token']);
                header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dashboard');
                exit;
            }
            $error = true;
            if ($username === '' || $password === '') {
                log_event('login_failure', $username ?: null, ['reason' => 'missing_credentials']);
            }
        }
    }

    if (!empty($viewFile) && file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo 'Login view missing.';
    }
    exit;
}

// Allow certain pages to bypass the layout (e.g., CSV exports)
if ($page === 'admin_mis' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!empty($viewFile) && file_exists($viewFile)) {
        include $viewFile;
    }
    exit;
}

if ($page === 'dashboard' && isset($_SESSION['username'])) {
    log_event('dashboard_view', $_SESSION['username']);
}

if (empty($viewFile) || !file_exists($viewFile)) {
    http_response_code(404);
    $pageTitle = 'Page Not Found';
    $viewFile = __DIR__ . '/../app/views/not_found.php';
    $useLayout = true;
}

if ($useLayout) {
    include __DIR__ . '/../app/views/layout.php';
} else {
    include $viewFile;
}
