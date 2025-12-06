<?php
// User repository helpers for Yojaka.

function yojaka_users_file_path(): string
{
    $systemDir = yojaka_config('paths.data_path') . '/system';
    if (!is_dir($systemDir)) {
        mkdir($systemDir, 0777, true);
    }

    return $systemDir . '/users.json';
}

function yojaka_load_users(): array
{
    $filePath = yojaka_users_file_path();

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);

    if (!is_array($data)) {
        // If decoding fails, return an empty array to avoid breaking the app.
        return [];
    }

    $normalized = [];

    foreach ($data as $user) {
        $normalized[] = yojaka_users_normalize_user($user);
    }

    return $normalized;
}

function yojaka_save_users(array $users): bool
{
    $filePath = yojaka_users_file_path();
    $json = json_encode($users, JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_users_normalize_user(array $user): array
{
    // Provide backward-compatible defaults for new fields.
    if (!isset($user['user_type'])) {
        $user['user_type'] = ($user['username'] ?? '') === 'superadmin' ? 'superadmin' : 'dept_user';
    }

    if (!array_key_exists('department_slug', $user)) {
        $user['department_slug'] = null;
    }

    if (!isset($user['display_name'])) {
        $user['display_name'] = $user['username'] ?? '';
    }

    if (!isset($user['created_at'])) {
        $user['created_at'] = date('c');
    }

    if (!isset($user['status'])) {
        $user['status'] = 'active';
    }

    return $user;
}

function yojaka_users_find_by_username(string $username): ?array
{
    $users = yojaka_load_users();

    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }

    return null;
}

// Backward compatibility wrapper
function yojaka_find_user_by_username(string $username): ?array
{
    return yojaka_users_find_by_username($username);
}

function yojaka_find_user_by_id(string $id): ?array
{
    $users = yojaka_load_users();

    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] === $id) {
            return $user;
        }
    }

    return null;
}

function yojaka_generate_user_id(array $users): string
{
    $maxNumber = 0;

    foreach ($users as $user) {
        if (isset($user['id']) && preg_match('/u_(\d+)/', $user['id'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $maxNumber) {
                $maxNumber = $num;
            }
        }
    }

    $nextNumber = $maxNumber + 1;
    return 'u_' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

function yojaka_users_add(array $userData): ?array
{
    $users = yojaka_load_users();
    $userData['id'] = $userData['id'] ?? yojaka_generate_user_id($users);
    $userData = yojaka_users_normalize_user($userData);
    $users[] = $userData;

    if (yojaka_save_users($users)) {
        return $userData;
    }

    return null;
}

// Backward compatibility wrapper
function yojaka_add_user(array $userData): ?array
{
    return yojaka_users_add($userData);
}

function yojaka_seed_superadmin(): void
{
    $users = yojaka_load_users();
    $hasSuperadmin = false;

    foreach ($users as &$user) {
        if (($user['username'] ?? '') === 'superadmin') {
            $user = yojaka_users_normalize_user($user);
            $user['user_type'] = 'superadmin';
            $user['department_slug'] = null;
            $hasSuperadmin = true;
        }
    }

    if ($hasSuperadmin) {
        yojaka_save_users($users);
        return;
    }

    $superadmin = [
        'id' => 'u_0001',
        'username' => 'superadmin',
        'display_name' => 'Super Administrator',
        'password_hash' => password_hash('ChangeMe@123', PASSWORD_DEFAULT),
        'user_type' => 'superadmin',
        'department_slug' => null,
        'created_at' => date('c'),
        'status' => 'active',
    ];

    $users[] = $superadmin;
    yojaka_save_users($users);
}

function yojaka_users_create_department_admin(string $deptSlug, string $deptName, string $password): ?array
{
    $username = 'admin.' . $deptSlug;
    $existing = yojaka_users_find_by_username($username);
    if ($existing) {
        return null;
    }

    $userData = [
        'username' => $username,
        'display_name' => 'Admin - ' . $deptName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'user_type' => 'dept_admin',
        'department_slug' => $deptSlug,
        'status' => 'active',
    ];

    return yojaka_users_add($userData);
}
