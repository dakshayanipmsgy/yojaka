<?php
// Authentication and user helper functions for Yojaka.

function users_file_path(): string
{
    return YOJAKA_DATA_PATH . '/users.json';
}

function load_users(): array
{
    $path = users_file_path();
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_users(array $users): void
{
    $path = users_file_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open users file for writing.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock users file.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($users, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function find_user_by_username(string $username): ?array
{
    $users = load_users();
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }
    return null;
}

function find_user_by_id($id): ?array
{
    $users = load_users();
    foreach ($users as $user) {
        if (isset($user['id']) && (string) $user['id'] === (string) $id) {
            return $user;
        }
    }
    return null;
}

function create_default_admin_if_needed(array $config): void
{
    $path = users_file_path();
    $users = load_users();

    if (!file_exists($path) || empty($users)) {
        $admin = $config['default_admin'];
        $now = gmdate('c');
        $defaultUser = [
            'id' => 1,
            'username' => $admin['username'],
            'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
            'role' => 'admin',
            'full_name' => $admin['full_name'],
            'created_at' => $now,
            'active' => true,
        ];
        save_users([$defaultUser]);
    }
}

function login(string $username, string $password): bool
{
    $user = find_user_by_username($username);
    if (!$user || empty($user['active'])) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return find_user_by_id($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . YOJAKA_BASE_URL . '/login.php');
        exit;
    }
}

function require_role(string $role): void
{
    if (!is_logged_in()) {
        require_login();
    }
    if (empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
        include __DIR__ . '/views/access_denied.php';
        exit;
    }
}
