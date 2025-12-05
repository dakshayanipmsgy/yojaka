<?php
// User storage helpers for Yojaka using JSON files.

function users_data_path(): string
{
    return YOJAKA_DATA_PATH . '/users.json';
}

function load_users(): array
{
    $path = users_data_path();
    if (!file_exists($path)) {
        return [];
    }

    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        return [];
    }

    $users = [];
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $users[] = standardize_user_record($entry);
    }

    return $users;
}

function save_users(array $users): bool
{
    $path = users_data_path();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }

    $success = false;
    if (flock($handle, LOCK_EX)) {
        $payload = array_values(array_map('standardize_user_record', $users));
        ftruncate($handle, 0);
        rewind($handle);
        $success = (bool) fwrite($handle, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return $success;
}

function find_user(string $username): ?array
{
    foreach (load_users() as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }
    return null;
}

function find_user_by_username(string $username): ?array
{
    return find_user($username);
}

function find_user_by_id($id): ?array
{
    foreach (load_users() as $user) {
        if (isset($user['id']) && (string) $user['id'] === (string) $id) {
            return $user;
        }
    }
    return null;
}

function user_exists(string $username): bool
{
    return find_user($username) !== null;
}

function save_user(array $user): bool
{
    $existing = load_users();
    $found = false;
    $normalized = standardize_user_record($user);
    foreach ($existing as &$record) {
        if (isset($record['username']) && strtolower($record['username']) === strtolower($normalized['username'])) {
            $record = array_merge($record, $normalized);
            $record['updated_at'] = gmdate('c');
            $found = true;
            break;
        }
    }
    unset($record);

    if (!$found) {
        $normalized['created_at'] = $normalized['created_at'] ?? gmdate('c');
        $normalized['updated_at'] = $normalized['updated_at'] ?? $normalized['created_at'];
        if (!isset($normalized['id'])) {
            $normalized['id'] = count($existing) + 1;
        }
        $existing[] = $normalized;
    }

    return save_users($existing);
}

function standardize_user_record(array $user): array
{
    $now = gmdate('c');

    if (!empty($user['password']) && empty($user['password_hash'])) {
        $user['password_hash'] = password_hash((string) $user['password'], PASSWORD_DEFAULT);
    }
    unset($user['password']);

    $user['username'] = $user['username'] ?? ($user['email'] ?? null);
    $user['role'] = $user['role'] ?? 'user';
    $user['full_name'] = $user['full_name'] ?? ($user['username'] ?? '');
    // Departments are inferred from usernames; keep legacy fields if present but do not auto-populate.
    $user['department_id'] = $user['department_id'] ?? null;
    $user['office_id'] = $user['office_id'] ?? null;
    $user['staff_id'] = $user['staff_id'] ?? ($user['staff'] ?? null);
    $user['active'] = array_key_exists('active', $user) ? (bool) $user['active'] : true;
    $user['force_password_change'] = !empty($user['force_password_change']);
    $user['password_hash'] = $user['password_hash'] ?? password_hash('changeme', PASSWORD_DEFAULT);
    $user['created_at'] = $user['created_at'] ?? $now;
    $user['updated_at'] = $user['updated_at'] ?? $user['created_at'];

    return $user;
}

function next_user_id(array $users): int
{
    $max = 0;
    foreach ($users as $user) {
        $id = isset($user['id']) ? (int) $user['id'] : 0;
        if ($id > $max) {
            $max = $id;
        }
    }

    return $max + 1;
}

function create_department_admin_user(string $deptSlug, string $deptName, string $deptAdminRoleId): array
{
    $username = 'admin.' . $deptSlug;
    $existing = find_user($username);
    if ($existing) {
        return [
            'username' => $username,
            'password_plain' => null,
            'created' => false,
        ];
    }

    $passwordPlain = bin2hex(random_bytes(6));
    $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
    $now = date('c');

    $users = load_users();
    $newUser = [
        'id' => next_user_id($users),
        'username' => $username,
        'full_name' => 'Admin - ' . $deptName,
        'password_hash' => $passwordHash,
        'role' => $deptAdminRoleId,
        'active' => true,
        'force_password_change' => true,
        'created_at' => $now,
        'updated_at' => $now,
        'department_id' => $deptSlug,
        'department_slug' => $deptSlug,
        'base_role_id' => 'dept_admin',
    ];

    $users[] = $newUser;
    save_users($users);

    return [
        'username' => $username,
        'password_plain' => $passwordPlain,
        'created' => true,
    ];
}
