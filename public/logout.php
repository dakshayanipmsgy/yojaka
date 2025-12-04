<?php
require_once __DIR__ . '/../app/bootstrap.php';

logout();

header('Location: ' . YOJAKA_BASE_URL . '/login.php');
exit;
