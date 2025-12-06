<?php
// Department user repository helpers.

function yojaka_dept_users_file_path(string $deptSlug): string
{
    $basePath = yojaka_config('paths.data_path') . '/departments/' . $deptSlug . '/users';
    if (!is_dir($basePath)) {
        mkdir($basePath, 0777, true);
    }

    return $basePath . '/users.json';
}

function yojaka_dept_users_load(string $deptSlug): array
{
    $filePath = yojaka_dept_users_file_path($deptSlug);

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return [];
    }

    $normalized = [];
    foreach ($data as $user) {
        $normalized[] = yojaka_dept_users_normalize_user($deptSlug, $user);
    }

    return $normalized;
}

function yojaka_dept_users_save(string $deptSlug, array $list): bool
{
    $filePath = yojaka_dept_users_file_path($deptSlug);
    $json = json_encode($list, JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_dept_users_normalize_user(string $deptSlug, array $user): array
{
    $now = date('c');
    $user['department_slug'] = $deptSlug;
    $user['status'] = $user['status'] ?? 'active';
    $user['created_at'] = $user['created_at'] ?? $now;
    $user['updated_at'] = $user['updated_at'] ?? $now;
    $user['role_ids'] = array_values($user['role_ids'] ?? []);
    $user['username_base'] = $user['username_base'] ?? ($user['username'] ?? '');

    if (!isset($user['login_identities']) || !is_array($user['login_identities'])) {
        $user['login_identities'] = [];
    }

    if (!empty($user['username_base']) && !empty($user['role_ids'])) {
        $generated = [];
        foreach ($user['role_ids'] as $roleId) {
            $generated[] = $user['username_base'] . '.' . $roleId;
        }
        $user['login_identities'] = $generated;
    }

    return $user;
}

function yojaka_dept_users_generate_id(array $users): string
{
    $max = 0;
    foreach ($users as $user) {
        if (isset($user['id']) && preg_match('/user_(\d+)/', $user['id'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }

    $next = $max + 1;
    return 'user_' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function yojaka_dept_users_add(string $deptSlug, array $userData): ?array
{
    $users = yojaka_dept_users_load($deptSlug);
    $userData['id'] = $userData['id'] ?? yojaka_dept_users_generate_id($users);
    $userData = yojaka_dept_users_normalize_user($deptSlug, $userData);
    $users[] = $userData;

    if (yojaka_dept_users_save($deptSlug, $users)) {
        return $userData;
    }

    return null;
}

function yojaka_dept_users_find_by_base(string $deptSlug, string $usernameBase): ?array
{
    $users = yojaka_dept_users_load($deptSlug);

    foreach ($users as $user) {
        if (isset($user['username_base']) && strtolower($user['username_base']) === strtolower($usernameBase)) {
            return $user;
        }
    }

    return null;
}

function yojaka_dept_users_find_by_id(string $deptSlug, string $userId): ?array
{
    $users = yojaka_dept_users_load($deptSlug);

    foreach ($users as $user) {
        if (($user['id'] ?? null) === $userId) {
            return $user;
        }
    }

    return null;
}

function yojaka_parse_login_identity(string $identity): ?array
{
    if (!preg_match('/^([a-z0-9]+)\.([a-z0-9_]+)\.([a-z0-9_]+)$/', $identity, $matches)) {
        return null;
    }

    $usernameBase = $matches[1];
    $roleLocalKey = $matches[2];
    $deptSlug = $matches[3];

    return [
        'username_base' => $usernameBase,
        'role_local_key' => $roleLocalKey,
        'department_slug' => $deptSlug,
        'role_id' => $roleLocalKey . '.' . $deptSlug,
    ];
}

function yojaka_dept_users_find_by_login_identity(string $identity): ?array
{
    $parts = yojaka_parse_login_identity($identity);
    if (!$parts) {
        return null;
    }

    $users = yojaka_dept_users_load($parts['department_slug']);
    foreach ($users as $user) {
        if (isset($user['username_base']) && strtolower($user['username_base']) === strtolower($parts['username_base'])) {
            $roleIds = $user['role_ids'] ?? [];
            if (in_array($parts['role_id'], $roleIds, true)) {
                return $user;
            }
        }
    }

    return null;
}
