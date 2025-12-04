<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!YOJAKA_INSTALLED) {
    header('Location: ' . YOJAKA_BASE_URL . '/install.php');
    exit;
}

if (is_logged_in()) {
    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dashboard');
    exit;
}

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

include __DIR__ . '/../app/views/login_form.php';
