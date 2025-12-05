<?php
// Department profile management helpers with tenant slugs encoded in usernames/roles.

function departments_directory(): string
{
    return YOJAKA_DATA_PATH . '/org';
}

function departments_path(): string
{
    return departments_directory() . DIRECTORY_SEPARATOR . 'departments.json';
}

function ensure_departments_storage(): void
{
    $dir = departments_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $path = departments_path();
    if (!file_exists($path) || filesize($path) === 0) {
        $default = [];
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

function index_departments_by_id(array $departments): array
{
    return $departments;
}

function get_department_label(?string $departmentId, array $departments): string
{
    if ($departmentId === null || $departmentId === '') {
        return '(Not set)';
    }

    $dept = find_department_by_id($departments, $departmentId);
    if ($dept) {
        return $dept['name'] ?? $departmentId;
    }

    return $departmentId;
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
        fwrite($handle, json_encode($departments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function get_default_department(array $departments): ?array
{
    foreach ($departments as $dept) {
        if (!empty($dept['is_default']) || !empty($dept['active'])) {
            return $dept;
        }
    }
    $values = array_values($departments);
    return $values[0] ?? null;
}

function find_department_by_id(array $departments, string $id): ?array
{
    if (isset($departments[$id])) {
        return $departments[$id];
    }
    foreach ($departments as $dept) {
        if (($dept['id'] ?? '') === $id || ($dept['slug'] ?? '') === $id) {
            return $dept;
        }
    }
    return null;
}

function get_user_department(?array $user, ?array $departments = null): ?array
{
    require_once __DIR__ . '/roles.php';
    $departments = $departments ?? load_departments();
    $deptSlug = $user ? get_current_department_slug_for_user($user) : null;
    if ($deptSlug && isset($departments[$deptSlug])) {
        return $departments[$deptSlug];
    }
    return get_default_department($departments);
}

function slugify_department_name(string $name, array $departments): string
{
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'dept';
    }
    $base = $slug;
    $counter = 1;
    while (isset($departments[$slug])) {
        $slug = $base . '_' . $counter;
        $counter++;
    }
    return $slug;
}

?>
