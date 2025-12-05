<?php
// Authentication and user helper functions for Yojaka.

function permissions_config_path(): string
{
    return YOJAKA_DATA_PATH . '/org/permissions.json';
}

function permissions_file_path(): string
{
    return permissions_config_path();
}

function load_permissions_config(): array
{
    $path = permissions_config_path();
    if (!file_exists($path)) {
        return ['roles' => [], 'custom_roles' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return ['roles' => [], 'custom_roles' => []];
    }

    if (!isset($data['roles']) || !is_array($data['roles'])) {
        $data['roles'] = [];
    }
    if (!isset($data['custom_roles']) || !is_array($data['custom_roles'])) {
        $data['custom_roles'] = [];
    }

    return $data;
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

function get_all_roles_for_dropdown(): array
{
    $config = load_permissions_config();
    $roles = $config['roles'] ?? [];
    $customRoles = $config['custom_roles'] ?? [];
    $list = [];

    foreach ([$roles, $customRoles] as $roleSet) {
        foreach ($roleSet as $roleId => $roleDef) {
            $label = null;
            if (is_array($roleDef) && isset($roleDef['label'])) {
                $label = $roleDef['label'];
            }
            $label = $label ?: ucfirst(str_replace('_', ' ', (string) $roleId));
            $list[$roleId] = [
                'id' => (string) $roleId,
                'label' => $label,
            ];
        }
    }

    if (!array_key_exists('admin', $list)) {
        $list['admin'] = [
            'id' => 'admin',
            'label' => 'Admin',
        ];
    }

    return array_values($list);
}

function ensure_users_have_departments(): void
{
    // Legacy shim retained for backward compatibility; departments are now
    // inferred from usernames and role suffixes, so we avoid mutating stored
    // records here.
    return;
}

function create_default_admin_if_needed(array $config): void
{
    $path = users_data_path();
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
            'updated_at' => $now,
            'active' => true,
            'office_id' => get_default_office_id(),
            'preferred_language' => 'en',
            'force_password_change' => true,
        ];
        save_users([$defaultUser]);
    }
}

function login(string $username, string $password): array
{
    $user = find_user_by_username($username);
    if (!$user) {
        log_event('login_failure', $username, ['reason' => 'user_not_found']);
        return ['success' => false, 'error' => 'user_not_found'];
    }

    if (empty($user['active'])) {
        log_event('login_failure', $username, ['reason' => 'inactive_user']);
        return ['success' => false, 'error' => 'inactive_user'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        log_event('login_failure', $username, ['reason' => 'invalid_password']);
        return ['success' => false, 'error' => 'invalid_password'];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['department_id'] = $user['department_id'] ?? null;
    $_SESSION['office_id'] = $user['office_id'] ?? get_default_office_id();

    log_event('login_success', $user['username']);
    log_event('user_login', $user['username'], ['office_id' => $_SESSION['office_id'], 'role' => $_SESSION['role']]);
    write_audit_log('auth', $user['username'], 'login', ['office_id' => $_SESSION['office_id']]);

    return [
        'success' => true,
        'user' => $user,
        'force_password_change' => !empty($user['force_password_change']),
    ];
}

function logout(): void
{
    $username = $_SESSION['username'] ?? null;
    $officeId = $_SESSION['office_id'] ?? get_default_office_id();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    if ($username) {
        log_event('logout', $username);
        log_event('user_logout', $username, ['office_id' => $officeId]);
        write_audit_log('auth', $username, 'logout', ['office_id' => $officeId]);
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
    $roleSource = $permissionsConfig['roles'][$role] ?? $permissionsConfig['custom_roles'][$role] ?? null;
    if ($role && $roleSource) {
        if (is_array($roleSource) && isset($roleSource['permissions']) && is_array($roleSource['permissions'])) {
            $rolePermissions = $roleSource['permissions'];
        } elseif (is_array($roleSource)) {
            $rolePermissions = $roleSource;
        }
    }

    // Backward compatibility with config-based permissions
    global $config;
    if (empty($rolePermissions) && !empty($config['roles_permissions'][$role])) {
        $rolePermissions = $config['roles_permissions'][$role];
    }

    return array_values(array_unique($rolePermissions));
}

function user_has_permission(?string $permission, ?bool $strictMode = null): bool
{
    if ($permission === null || $permission === '') {
        return true;
    }

    if (!is_logged_in()) {
        return false;
    }

    $permissions = get_user_role_permissions();
    if (in_array('*', $permissions, true)) {
        return true;
    }

    if (in_array($permission, $permissions, true)) {
        return true;
    }

    global $config;
    $strictMode = $strictMode ?? (bool) ($config['permissions_strict'] ?? false);
    return !$strictMode;
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
