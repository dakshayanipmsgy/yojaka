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
    $user['primary_role'] = $user['primary_role'] ?? ($user['role'] ?? 'user');
    $user['extra_roles'] = isset($user['extra_roles']) && is_array($user['extra_roles']) ? $user['extra_roles'] : [];
    $user['role'] = $user['primary_role'];
    $user['full_name'] = $user['full_name'] ?? ($user['username'] ?? '');
    $defaultOffice = function_exists('get_default_office_id') ? get_default_office_id() : 'office_001';
    $user['office_id'] = $user['office_id'] ?? $defaultOffice;
    $user['department_id'] = $user['department_id'] ?? ($user['department'] ?? 'dept_default');
    $user['staff_id'] = $user['staff_id'] ?? null;
    $user['active'] = array_key_exists('active', $user) ? (bool) $user['active'] : true;
    $user['force_password_change'] = !empty($user['force_password_change']);
    $user['password_hash'] = $user['password_hash'] ?? password_hash('changeme', PASSWORD_DEFAULT);
    $user['created_at'] = $user['created_at'] ?? $now;
    $user['updated_at'] = $user['updated_at'] ?? $user['created_at'];

    return $user;
}
