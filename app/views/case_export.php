<?php
require_login();
require_permission('admin_backup');

$module = $_GET['module'] ?? '';
$id = $_GET['id'] ?? '';

if ($module === '' || $id === '') {
    echo '<p>Missing module or ID.</p>';
    return;
}

try {
    export_case_bundle($module, $id);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
}
