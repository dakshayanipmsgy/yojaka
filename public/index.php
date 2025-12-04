<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Route visitors to login or dashboard based on session state.
if (is_logged_in()) {
    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=dashboard');
    exit;
}

header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=login');
exit;
