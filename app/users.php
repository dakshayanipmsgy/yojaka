<?php
// Department-scoped user storage (JSON only).
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/auth.php';

/**
 * Return the path to the users JSON file.
 */
function users_data_path(): string
{
    return __DIR__ . '/../data/users.json';
}

/**
 * Load all users from JSON.
 * Returns associative array keyed by username.
 */
function load_users(): array
{
    $users = [];

    foreach (load_global_users() as $user) {
        if (!isset($user['username'])) {
            continue;
        }
        $users[$user['username']] = $user;
    }

    $departments = load_departments();
    foreach ($departments as $slug => $dept) {
        foreach (load_department_users($slug) as $base => $user) {
            $record = assemble_user_login_record($user, $slug);
            $users[$record['username']] = $record;
        }
    }

    return $users;
}

/**
 * Save all users to JSON.
 */
function save_users(array $users): bool
{
    $normalized = [];
    foreach ($users as $username => $user) {
        if (isset($user['username'])) {
            $normalized[$user['username']] = $user;
            continue;
        }
        if (is_string($username)) {
            $normalized[$username] = $user;
        }
    }

    return save_global_users(array_values($normalized));
}

/**
 * Convenience helper: find a single user by username.
 */
function find_user(string $username): ?array
{
    return find_user_by_username($username);
}

function global_users_data_path(): string
{
    return users_data_path();
}

function department_users_directory(): string
{
    return YOJAKA_DATA_PATH . '/org/users';
}

function department_users_path(string $deptSlug): string
{
    return department_users_directory() . '/' . $deptSlug . '.json';
}

function ensure_users_storage(?string $deptSlug = null): void
{
    $dir = department_users_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if ($deptSlug !== null) {
        $path = department_users_path($deptSlug);
        if (!file_exists($path)) {
            save_department_users($deptSlug, []);
        }
    }
}

function load_department_users(string $deptSlug): array
{
    ensure_users_storage($deptSlug);
    $path = department_users_path($deptSlug);
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    $normalized = [];
    foreach ($data as $username => $user) {
        if (!is_array($user)) {
            continue;
        }
        $base = $user['base_username'] ?? $username;
        $user['base_username'] = $base;
        $user['full_name'] = $user['full_name'] ?? $base;
        $user['roles'] = isset($user['roles']) && is_array($user['roles']) ? array_values(array_unique($user['roles'])) : [];
        $user['active'] = array_key_exists('active', $user) ? (bool) $user['active'] : true;
        $user['created_at'] = $user['created_at'] ?? gmdate('c');
        $user['updated_at'] = $user['updated_at'] ?? $user['created_at'];
        $user['department_slug'] = $deptSlug;
        $normalized[$base] = $user;
    }
    return $normalized;
}

function save_department_users(string $deptSlug, array $users): bool
{
    ensure_users_storage();
    $path = department_users_path($deptSlug);
    $payload = [];
    foreach ($users as $base => $user) {
        if (!is_array($user)) {
            continue;
        }
        $user['base_username'] = $user['base_username'] ?? $base;
        $payload[$user['base_username']] = $user;
    }
    return (bool) file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function find_department_user(string $deptSlug, string $baseUsername): ?array
{
    $users = load_department_users($deptSlug);
    return $users[$baseUsername] ?? null;
}

function find_user_any_department(string $baseUsername): ?array
{
    $departments = load_departments();
    foreach ($departments as $slug => $dept) {
        $user = find_department_user($slug, $baseUsername);
        if ($user) {
            return $user;
        }
    }
    return null;
}

function store_department_user(string $deptSlug, array $user): bool
{
    $users = load_department_users($deptSlug);
    $base = $user['base_username'] ?? ($user['username'] ?? '');
    $user['base_username'] = $base;
    $user['department_slug'] = $deptSlug;
    $user['updated_at'] = gmdate('c');
    if (!isset($users[$base])) {
        $user['created_at'] = $user['created_at'] ?? $user['updated_at'];
        $user['active'] = array_key_exists('active', $user) ? (bool) $user['active'] : true;
    }
    $users[$base] = $user;
    return save_department_users($deptSlug, $users);
}

function create_department_user(string $deptSlug, string $baseUsername, string $fullName, string $passwordPlain, array $roleIds): array
{
    $users = load_department_users($deptSlug);
    if (isset($users[$baseUsername])) {
        return $users[$baseUsername];
    }
    $now = gmdate('c');
    $users[$baseUsername] = [
        'base_username' => $baseUsername,
        'full_name' => $fullName,
        'password_hash' => password_hash($passwordPlain, PASSWORD_DEFAULT),
        'roles' => array_values(array_unique($roleIds)),
        'active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    save_department_users($deptSlug, $users);
    return $users[$baseUsername];
}

function apply_user_delete(string $deptSlug, string $baseUsername): bool
{
    $users = load_department_users($deptSlug);
    if (!isset($users[$baseUsername])) {
        return false;
    }
    unset($users[$baseUsername]);
    return save_department_users($deptSlug, $users);
}

function request_user_delete(string $deptSlug, string $baseUsername, string $requestedBy): string
{
    require_once __DIR__ . '/critical_actions.php';
    return queue_critical_action([
        'department' => $deptSlug,
        'type' => 'user.delete',
        'requested_by' => $requestedBy,
        'payload' => ['username' => $baseUsername],
    ]);
}

function request_user_password_reset(string $deptSlug, string $baseUsername, string $requestedBy, string $newPassword): string
{
    require_once __DIR__ . '/critical_actions.php';
    return queue_critical_action([
        'department' => $deptSlug,
        'type' => 'user.password_reset',
        'requested_by' => $requestedBy,
        'payload' => ['username' => $baseUsername, 'new_password' => $newPassword],
    ]);
}

function apply_user_password_reset(string $deptSlug, string $baseUsername, string $newPassword): bool
{
    $users = load_department_users($deptSlug);
    if (!isset($users[$baseUsername])) {
        return false;
    }
    $users[$baseUsername]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $users[$baseUsername]['updated_at'] = gmdate('c');
    return save_department_users($deptSlug, $users);
}

function build_full_role_ids(string $baseUsername, array $roles, string $deptSlug): array
{
    $roleIds = [];
    foreach ($roles as $role) {
        [$baseUser, $baseRoleId] = parse_username_parts($role);
        $roleIds[] = $baseUsername . '.' . ($baseRoleId ?? $role) . '.' . $deptSlug;
    }
    return array_values(array_unique($roleIds));
}

function create_department_admin_user(string $deptSlug, string $deptName, string $deptAdminRoleId): array
{
    $username = 'admin';
    $user = find_department_user($deptSlug, $username);
    if ($user) {
        return ['username' => 'admin.' . $deptSlug, 'password_plain' => null, 'created' => false];
    }
    $passwordPlain = bin2hex(random_bytes(6));
    $fullRole = $username . '.' . $deptAdminRoleId;
    $record = create_department_user($deptSlug, $username, 'Admin - ' . $deptName, $passwordPlain, [$fullRole]);
    return ['username' => 'admin.' . $deptSlug, 'password_plain' => $passwordPlain, 'created' => true, 'record' => $record];
}

function assemble_user_login_record(array $user, string $deptSlug): array
{
    $user['department_slug'] = $deptSlug;
    $user['username'] = $user['base_username'] . '.' . $deptSlug;
    return $user;
}

function find_user_by_username(string $username): ?array
{
    [$baseUsername, , $deptSlug] = parse_username_parts($username);
    if ($deptSlug === null) {
        $global = load_global_users();
        foreach ($global as $u) {
            if (($u['username'] ?? '') === $username) {
                return $u;
            }
        }
        return null;
    }
    $user = find_department_user($deptSlug, $baseUsername);
    return $user ? assemble_user_login_record($user, $deptSlug) : null;
}

function load_global_users(): array
{
    $path = global_users_data_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_global_users(array $users): bool
{
    return (bool) file_put_contents(global_users_data_path(), json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function list_user_roles_for_selection(array $user): array
{
    $roles = $user['roles'] ?? [];
    $list = [];
    foreach ($roles as $roleId) {
        $list[] = $roleId;
    }
    return array_values(array_unique($list));
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
