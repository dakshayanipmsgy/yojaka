<?php
// Authentication and user helper functions for Yojaka.

function users_file_path(): string
{
    return YOJAKA_DATA_PATH . '/users.json';
}

function permissions_file_path(): string
{
    return YOJAKA_DATA_PATH . '/org/permissions.json';
}

function load_permissions_config(): array
{
    $path = permissions_file_path();
    if (!file_exists($path)) {
        return ['roles' => [], 'custom_roles' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : ['roles' => [], 'custom_roles' => []];
}

function save_permissions_config(array $data): void
{
    $path = permissions_file_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
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

function ensure_users_have_departments(): void
{
    $users = load_users();
    if (empty($users)) {
        return;
    }
    $departments = load_departments();
    $defaultDept = get_default_department($departments);
    if (!$defaultDept) {
        return;
    }
    $defaultId = $defaultDept['id'];
    $defaultOfficeId = get_default_office_id();
    $updated = false;
    foreach ($users as &$user) {
        if (empty($user['department_id'])) {
            $user['department_id'] = $defaultId;
            $updated = true;
        }
        if (empty($user['office_id'])) {
            $user['office_id'] = $defaultOfficeId;
            $updated = true;
        }
    }
    unset($user);
    if ($updated) {
        save_users($users);
    }
}

function create_default_admin_if_needed(array $config): void
{
    $path = users_file_path();
    $users = load_users();

    if (!file_exists($path) || empty($users)) {
        $departments = load_departments();
        $defaultDept = get_default_department($departments);
        $admin = $config['default_admin'];
        $now = gmdate('c');
        $defaultUser = [
            'id' => 1,
            'username' => $admin['username'],
            'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
            'role' => 'admin',
            'department_id' => $defaultDept['id'] ?? 'dept_default',
            'full_name' => $admin['full_name'],
            'created_at' => $now,
            'active' => true,
            'office_id' => get_default_office_id(),
            'preferred_language' => 'en',
        ];
        save_users([$defaultUser]);
    }
}

function login(string $username, string $password): bool
{
    $user = find_user_by_username($username);
    if (!$user) {
        log_event('login_failure', $username, ['reason' => 'user_not_found']);
        return false;
    }

    if (empty($user['active'])) {
        log_event('login_failure', $username, ['reason' => 'inactive_user']);
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        log_event('login_failure', $username, ['reason' => 'invalid_password']);
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['department_id'] = $user['department_id'] ?? null;
    $_SESSION['office_id'] = $user['office_id'] ?? get_default_office_id();

    log_event('login_success', $user['username']);

    return true;
}

function logout(): void
{
    $username = $_SESSION['username'] ?? null;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    if ($username) {
        log_event('logout', $username);
    }
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

function get_current_user_role(): ?string
{
    return $_SESSION['role'] ?? (current_user()['role'] ?? null);
}

function get_user_role_permissions(?string $username = null): array
{
    $user = $username ? find_user_by_username($username) : current_user();
    if (!$user) {
        return [];
    }

    $role = $user['role'] ?? null;
    $permissionsConfig = load_permissions_config();
    $rolePermissions = [];
    if ($role && !empty($permissionsConfig['roles'][$role])) {
        $rolePermissions = $permissionsConfig['roles'][$role];
    }

    if ($role && !empty($permissionsConfig['custom_roles'][$role])) {
        $rolePermissions = array_merge($rolePermissions, $permissionsConfig['custom_roles'][$role]);
    }

    // Backward compatibility with config-based permissions
    global $config;
    if (empty($rolePermissions) && !empty($config['roles_permissions'][$role])) {
        $rolePermissions = $config['roles_permissions'][$role];
    }

    return array_values(array_unique($rolePermissions));
}

function user_has_permission(string $permission): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $permissions = get_user_role_permissions();
    if (in_array('*', $permissions, true)) {
        return true;
    }

    return in_array($permission, $permissions, true);
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
    if (empty($_SESSION['role']) || ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'superadmin')) {
        include __DIR__ . '/views/access_denied.php';
        exit;
    }
}

function require_permission(string $permission): void
{
    if (!user_has_permission($permission)) {
        include __DIR__ . '/views/access_denied.php';
        exit;
    }
}

?>
