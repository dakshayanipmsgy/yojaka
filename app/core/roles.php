<?php
// Department role repository helpers.

function yojaka_roles_file_path(string $deptSlug): string
{
    $basePath = yojaka_config('paths.data_path') . '/departments/' . $deptSlug . '/roles';
    if (!is_dir($basePath)) {
        mkdir($basePath, 0777, true);
    }

    return $basePath . '/roles.json';
}

function yojaka_roles_load_for_department(string $deptSlug): array
{
    $filePath = yojaka_roles_file_path($deptSlug);

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function yojaka_roles_save_for_department(string $deptSlug, array $roles): bool
{
    $filePath = yojaka_roles_file_path($deptSlug);
    $json = json_encode($roles, JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_roles_generate_id(array $roles): string
{
    $max = 0;
    foreach ($roles as $role) {
        if (isset($role['id']) && preg_match('/role_(\d+)/', $role['id'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }

    $next = $max + 1;
    return 'role_' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function yojaka_roles_add(string $deptSlug, array $roleData): ?array
{
    $roles = yojaka_roles_load_for_department($deptSlug);
    $now = date('c');

    $roleData['id'] = $roleData['id'] ?? yojaka_roles_generate_id($roles);
    $roleData['created_at'] = $roleData['created_at'] ?? $now;
    $roleData['updated_at'] = $roleData['updated_at'] ?? $now;

    if (!isset($roleData['role_id']) && isset($roleData['local_key'])) {
        $roleData['role_id'] = $roleData['local_key'] . '.' . $deptSlug;
    }

    $roles[] = $roleData;

    if (yojaka_roles_save_for_department($deptSlug, $roles)) {
        return $roleData;
    }

    return null;
}

function yojaka_roles_find_by_role_id(string $deptSlug, string $roleId): ?array
{
    $roles = yojaka_roles_load_for_department($deptSlug);

    foreach ($roles as $role) {
        if (($role['role_id'] ?? '') === $roleId) {
            return $role;
        }
    }

    return null;
}
