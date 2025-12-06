<?php
// Department repository helpers for Yojaka.

function yojaka_departments_file_path(): string
{
    $systemDir = yojaka_config('paths.data_path') . '/system';
    if (!is_dir($systemDir)) {
        mkdir($systemDir, 0777, true);
    }

    return $systemDir . '/departments.json';
}

function yojaka_load_departments(): array
{
    $filePath = yojaka_departments_file_path();

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);

    if (!is_array($data)) {
        // If decoding fails, return an empty list to keep the app running.
        return [];
    }

    return $data;
}

function yojaka_save_departments(array $departments): bool
{
    $filePath = yojaka_departments_file_path();
    $json = json_encode($departments, JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_generate_department_id(array $departments): string
{
    $maxNumber = 0;

    foreach ($departments as $department) {
        if (isset($department['id']) && preg_match('/dept_(\d+)/', $department['id'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $maxNumber) {
                $maxNumber = $num;
            }
        }
    }

    $nextNumber = $maxNumber + 1;
    return 'dept_' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function yojaka_find_department_by_slug(string $slug): ?array
{
    $departments = yojaka_load_departments();

    foreach ($departments as $department) {
        if (isset($department['slug']) && strtolower($department['slug']) === strtolower($slug)) {
            return $department;
        }
    }

    return null;
}

function yojaka_find_department_by_id(string $id): ?array
{
    $departments = yojaka_load_departments();

    foreach ($departments as $department) {
        if (isset($department['id']) && $department['id'] === $id) {
            return $department;
        }
    }

    return null;
}

function yojaka_add_department(array $data): ?array
{
    $departments = yojaka_load_departments();
    $data['id'] = $data['id'] ?? yojaka_generate_department_id($departments);
    $now = date('c');
    $data['created_at'] = $data['created_at'] ?? $now;
    $data['updated_at'] = $data['updated_at'] ?? $now;
    $departments[] = $data;

    if (yojaka_save_departments($departments)) {
        return $data;
    }

    return null;
}

function yojaka_departments_initialize_storage(string $deptSlug): void
{
    $basePath = yojaka_config('paths.data_path') . '/departments/' . $deptSlug;
    $folders = [
        $basePath,
        $basePath . '/config',
        $basePath . '/roles',
        $basePath . '/users',
        $basePath . '/workflows',
        $basePath . '/modules',
        $basePath . '/modules/dak',
        $basePath . '/modules/rti',
    ];

    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
    }

    $rolesFile = $basePath . '/roles/roles.json';
    if (!file_exists($rolesFile)) {
        file_put_contents($rolesFile, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    }

    // Seed default workflows for the department if none exist yet.
    yojaka_workflows_seed_default($deptSlug);
}

function yojaka_update_department_status(string $id, string $status): bool
{
    $departments = yojaka_load_departments();
    $updated = false;

    foreach ($departments as &$department) {
        if (isset($department['id']) && $department['id'] === $id) {
            $department['status'] = $status;
            $department['updated_at'] = date('c');
            $updated = true;
            break;
        }
    }

    if ($updated) {
        return yojaka_save_departments($departments);
    }

    return false;
}
