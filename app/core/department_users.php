<?php
// Department user repository helpers.

function yojaka_dept_users_base_path(string $deptSlug): string
{
    $dataPath = yojaka_config('data_path', yojaka_config('paths.data_path'));
    return rtrim($dataPath, '/') . '/departments/' . $deptSlug . '/users';
}

function yojaka_dept_users_file_path(string $deptSlug): string
{
    return yojaka_dept_users_base_path($deptSlug) . '/users.json';
}

function yojaka_dept_users_ensure_storage(string $deptSlug): void
{
    $departmentsDir = yojaka_config('data_path', yojaka_config('paths.data_path')) . '/departments';
    if (!is_dir($departmentsDir)) {
        mkdir($departmentsDir, 0777, true);
    }

    $basePath = yojaka_dept_users_base_path($deptSlug);
    if (!is_dir($basePath)) {
        mkdir($basePath, 0777, true);
    }

    $filePath = yojaka_dept_users_file_path($deptSlug);
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    }
}

function yojaka_dept_users_load(string $deptSlug): array
{
    yojaka_dept_users_ensure_storage($deptSlug);

    $filePath = yojaka_dept_users_file_path($deptSlug);
    $content = @file_get_contents($filePath);
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
    yojaka_dept_users_ensure_storage($deptSlug);

    $filePath = yojaka_dept_users_file_path($deptSlug);
    $json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_dept_users_generate_login_identities(string $deptSlug, string $usernameBase, array $roleIds): array
{
    $identities = [];

    foreach ($roleIds as $roleId) {
        $roleLocalKey = $roleId;
        $parts = explode('.', $roleId, 2);
        if (count($parts) >= 1 && $parts[0] !== '') {
            $roleLocalKey = $parts[0];
        }

        $identities[] = $usernameBase . '.' . $roleLocalKey . '.' . $deptSlug;
    }

    return array_values(array_unique($identities));
}

function yojaka_dept_users_normalize_user(string $deptSlug, array $user): array
{
    $now = date('c');
    $user['department_slug'] = $deptSlug;
    $user['user_type'] = $user['user_type'] ?? 'dept_user';
    $user['status'] = $user['status'] ?? 'active';
    $user['created_at'] = $user['created_at'] ?? $now;
    $user['updated_at'] = $user['updated_at'] ?? $now;
    $user['role_ids'] = array_values($user['role_ids'] ?? []);
    $user['username_base'] = $user['username_base'] ?? ($user['username'] ?? '');
    $user['must_change_password'] = $user['must_change_password'] ?? false;

    if (!isset($user['login_identities']) || !is_array($user['login_identities'])) {
        $user['login_identities'] = [];
    }

    if (!empty($user['username_base']) && !empty($user['role_ids'])) {
        $user['login_identities'] = yojaka_dept_users_generate_login_identities($deptSlug, $user['username_base'], $user['role_ids']);
    }

    return $user;
}

function yojaka_dept_users_generate_id(array $users): string
{
    $max = 0;
    foreach ($users as $user) {
        if (isset($user['id']) && preg_match('/user_(\d+)/', $user['id'], $matches)) {
            $num = (int) $matches[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }

    $next = $max + 1;
    return 'user_' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function yojaka_dept_users_upsert(string $deptSlug, array $user): array
{
    $users = yojaka_dept_users_load($deptSlug);
    $user['id'] = $user['id'] ?? yojaka_dept_users_generate_id($users);
    $normalized = yojaka_dept_users_normalize_user($deptSlug, $user);

    $updated = false;
    foreach ($users as &$existing) {
        if (($existing['id'] ?? null) === $normalized['id']) {
            $existing = $normalized;
            $updated = true;
            break;
        }
    }
    unset($existing);

    if (!$updated) {
        $users[] = $normalized;
    }

    yojaka_dept_users_save($deptSlug, $users);

    return $normalized;
}

function yojaka_dept_users_add(string $deptSlug, array $userData): ?array
{
    $saved = yojaka_dept_users_upsert($deptSlug, $userData);
    return $saved ?: null;
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
    $parts = explode('.', $identity, 3);
    if (count($parts) !== 3) {
        return null;
    }

    [$usernameBase, $roleLocalKey, $deptSlug] = $parts;
    if ($usernameBase === '' || $roleLocalKey === '' || $deptSlug === '') {
        return null;
    }

    return [
        'username_base' => $usernameBase,
        'role_local_key' => $roleLocalKey,
        'department_slug' => $deptSlug,
        'role_id' => $roleLocalKey . '.' . $deptSlug,
    ];
}

function yojaka_dept_users_find_by_login_identity(string $deptSlug, string $loginIdentity): ?array
{
    $users = yojaka_dept_users_load($deptSlug);

    foreach ($users as $user) {
        $identities = $user['login_identities'] ?? [];
        if (in_array($loginIdentity, $identities, true)) {
            return $user;
        }
    }

    return null;
}
