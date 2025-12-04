<?php
require_login();
$available = i18n_available_languages();
$requested = $_POST['lang'] ?? ($_GET['lang'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';
$officeReadOnly = office_is_read_only(get_current_office_license());
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
if (!$requested || !in_array($requested, $available, true)) {
    $requested = i18n_determine_language();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        i18n_set_current_language($requested);
        if (!$officeReadOnly) {
            i18n_update_user_preference($requested);
        }
    }
}
$target = $_SERVER['HTTP_REFERER'] ?? (YOJAKA_BASE_URL . '/app.php?page=dashboard');
header('Location: ' . $target);
exit;
