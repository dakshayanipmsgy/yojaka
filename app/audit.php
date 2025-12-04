<?php
// Audit trail helpers

function audit_base_path(): string
{
    global $config;
    $base = $config['audit_data_path'] ?? (YOJAKA_DATA_PATH . '/audit');
    if (!$base) {
        $base = YOJAKA_DATA_PATH . '/audit';
    }
    return rtrim($base, DIRECTORY_SEPARATOR);
}

function audit_log_path(string $module, string $entityId): string
{
    $dir = audit_base_path() . DIRECTORY_SEPARATOR . $module;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . DIRECTORY_SEPARATOR . $entityId . '.log';
}

function ensure_audit_storage(): void
{
    $base = audit_base_path();
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }
    $modules = ['rti', 'dak', 'bills', 'documents', 'inspection'];
    foreach ($modules as $module) {
        $dir = $base . DIRECTORY_SEPARATOR . $module;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    $htaccess = $base . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\nDeny from all\n<FilesMatch \\\"\\.(log|json)$\\\">\n    Require all denied\n</FilesMatch>\n");
    }
}

function write_audit_log(string $module, string $entityId, string $action, array $details = []): void
{
    ensure_audit_storage();
    $path = audit_log_path($module, $entityId);
    $currentUser = current_user();
    $entry = [
        'timestamp' => gmdate('c'),
        'user' => $currentUser['username'] ?? 'system',
        'office_id' => get_current_office_id(),
        'action' => $action,
        'details' => array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ], $details),
    ];

    $handle = fopen($path, 'a');
    if (!$handle) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n");
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

