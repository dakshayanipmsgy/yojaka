<?php
// Department profile management helpers stored as JSON files.

function departments_data_path(): string
{
    return __DIR__ . '/../data/org/departments.json';
}

function departments_path(): string
{
    return departments_data_path();
}

function departments_directory(): string
{
    return dirname(departments_data_path());
}

function ensure_departments_storage(): void
{
    $dir = departments_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    $path = departments_data_path();
    if (!file_exists($path)) {
        save_departments([]);
    }
}

function load_departments(): array
{
    ensure_departments_storage();
    $path = departments_data_path();
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }

    $normalized = [];
    foreach ($data as $key => $dept) {
        if (!is_array($dept)) {
            continue;
        }
        $slug = $dept['slug'] ?? (is_string($key) ? $key : '');
        if ($slug === '') {
            $slug = make_department_slug($dept['name'] ?? '');
        }
        $dept['slug'] = $slug;
        $dept['id'] = $dept['id'] ?? $slug;
        $dept['status'] = $dept['status'] ?? (!empty($dept['active']) ? 'active' : 'suspended');
        $dept['active'] = ($dept['status'] === 'active');
        $normalized[$slug] = $dept;
    }

    return $normalized;
}

function save_departments(array $departments): bool
{
    $path = departments_data_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    $payload = [];
    foreach ($departments as $key => $dept) {
        if (!is_array($dept)) {
            continue;
        }
        $slug = $dept['slug'] ?? (is_string($key) ? $key : '');
        if ($slug === '') {
            $slug = make_department_slug($dept['name'] ?? '');
        }
        $dept['slug'] = $slug;
        $dept['id'] = $dept['id'] ?? $slug;
        $dept['status'] = $dept['status'] ?? (!empty($dept['active']) ? 'active' : 'suspended');
        $dept['active'] = ($dept['status'] === 'active');
        $payload[$slug] = $dept;
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return (bool) file_put_contents($path, $json, LOCK_EX);
}

/**
 * Create URL-safe, lowercase slug from department name.
 * Example: "Department ABC (Main)" => "departmentabc_main"
 */
function make_department_slug(string $name): string
{
    $slug = mb_strtolower($name, 'UTF-8');
    $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug);
    $slug = trim((string) $slug, '_');
    if ($slug === '') {
        $slug = 'dept_' . time();
    }
    return $slug;
}

function make_unique_department_slug(string $name, array $existing): string
{
    $base = make_department_slug($name);
    $slug = $base;
    $i = 1;
    while (isset($existing[$slug])) {
        $slug = $base . '_' . $i;
        $i++;
    }
    return $slug;
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

function get_default_department(array $departments): ?array
{
    foreach ($departments as $dept) {
        if (($dept['status'] ?? 'active') === 'active') {
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
    return make_unique_department_slug($name, $departments);
}

?>
