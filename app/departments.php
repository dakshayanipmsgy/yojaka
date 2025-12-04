<?php
// Department profile management helpers

function departments_directory(): string
{
    global $config;
    $dir = $config['departments_data_path'] ?? (YOJAKA_DATA_PATH . '/departments');
    return rtrim($dir, DIRECTORY_SEPARATOR);
}

function departments_path(): string
{
    global $config;
    $file = $config['departments_file'] ?? 'departments.json';
    return departments_directory() . DIRECTORY_SEPARATOR . $file;
}

function ensure_departments_storage(): void
{
    $dir = departments_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }

    $path = departments_path();
    if (!file_exists($path) || filesize($path) === 0) {
        $default = [
            [
                'id' => 'dept_default',
                'name' => 'Default Government Office',
                'address' => '123 Secretariat Lane, Capital City',
                'contact' => 'Phone: 000-000000 | Email: office@example.gov',
                'logo_path' => 'assets/images/default_logo.png',
                'letterhead_header_html' => '<div class="letterhead-block"><strong>Government Office</strong><div>Official Correspondence</div></div>',
                'letterhead_footer_html' => '<div class="letterhead-block">This is a system generated document.</div>',
                'default_signatory_block' => '<p><em>Authorized Signatory</em></p>',
                'is_default' => true,
            ],
        ];
        save_departments($default);
    }
}

function load_departments(): array
{
    ensure_departments_storage();
    $path = departments_path();
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

function save_departments(array $departments): bool
{
    ensure_departments_storage();
    $path = departments_path();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }

    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($departments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function get_default_department(array $departments): ?array
{
    foreach ($departments as $dept) {
        if (!empty($dept['is_default'])) {
            return $dept;
        }
    }
    return $departments[0] ?? null;
}

function find_department_by_id(array $departments, string $id): ?array
{
    foreach ($departments as $dept) {
        if (($dept['id'] ?? '') === $id) {
            return $dept;
        }
    }
    return null;
}

function get_user_department(?array $user, ?array $departments = null): ?array
{
    $departments = $departments ?? load_departments();
    $deptId = $user['department_id'] ?? null;
    if ($deptId) {
        $dept = find_department_by_id($departments, $deptId);
        if ($dept) {
            return $dept;
        }
    }
    return get_default_department($departments);
}

?>
