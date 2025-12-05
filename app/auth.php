<?php
// Authentication and user helper functions for Yojaka.

require_once __DIR__ . '/users.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/permissions_catalog.php';

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
    $departments = load_departments();
    [$baseUsername, , $deptSlugFromInput] = parse_username_parts($username);
    $resolvedUser = null;
    $resolvedDept = null;
    $scope = 'department';

    if ($deptSlugFromInput !== null) {
        $resolvedUser = find_department_user($deptSlugFromInput, $baseUsername);
        $resolvedDept = $departments[$deptSlugFromInput] ?? null;
    }

    if ($resolvedUser === null) {
        foreach ($departments as $slug => $dept) {
            $candidate = find_department_user($slug, $baseUsername);
            if ($candidate) {
                $resolvedUser = $candidate;
                $resolvedDept = $dept;
                break;
            }
        }
    }

    if ($resolvedUser === null) {
        // try global user (superadmin / legacy)
        foreach (load_global_users() as $globalUser) {
            if (($globalUser['username'] ?? '') === $username) {
                $resolvedUser = $globalUser;
                $scope = 'global';
                break;
            }
        }
    }

    if (!$resolvedUser) {
        log_event('login_failure', $username, ['reason' => 'user_not_found']);
        return ['success' => false, 'error' => 'user_not_found'];
    }

    if (isset($resolvedDept['status'])) {
        if ($resolvedDept['status'] === 'suspended') {
            log_event('login_failure', $username, ['reason' => 'department_suspended']);
            return ['success' => false, 'error' => 'department_suspended'];
        }
    }

    if (empty($resolvedUser['active'])) {
        log_event('login_failure', $username, ['reason' => 'inactive_user']);
        return ['success' => false, 'error' => 'inactive_user'];
    }

    if (!password_verify($password, $resolvedUser['password_hash'] ?? '')) {
        log_event('login_failure', $username, ['reason' => 'invalid_password']);
        return ['success' => false, 'error' => 'invalid_password'];
    }

    $loginUsername = $scope === 'global' ? ($resolvedUser['username'] ?? $baseUsername) : ($resolvedUser['base_username'] . '.' . ($resolvedDept['slug'] ?? ''));
    $_SESSION['user_id'] = $resolvedUser['id'] ?? $resolvedUser['base_username'] ?? $loginUsername;
    $_SESSION['username'] = $loginUsername;
    $_SESSION['full_name'] = $resolvedUser['full_name'] ?? $loginUsername;
    $_SESSION['department_id'] = $resolvedDept['slug'] ?? null;
    $_SESSION['office_id'] = $resolvedUser['office_id'] ?? get_default_office_id();
    $_SESSION['department_read_only'] = ($resolvedDept['status'] ?? 'active') === 'archived';

    $availableRoles = $scope === 'global' ? [($resolvedUser['role'] ?? 'superadmin')] : list_user_roles_for_selection($resolvedUser);
    $_SESSION['available_roles'] = $availableRoles;

    if (count($availableRoles) === 1) {
        $_SESSION['acting_role'] = $availableRoles[0];
        $_SESSION['role'] = $availableRoles[0];
    } else {
        $_SESSION['acting_role'] = null;
        $_SESSION['role'] = null;
    }

    log_event('login_success', $loginUsername);
    log_event('user_login', $loginUsername, ['office_id' => $_SESSION['office_id'], 'role' => $_SESSION['acting_role']]);
    write_audit_log('auth', $loginUsername, 'login', ['office_id' => $_SESSION['office_id']]);

    return [
        'success' => true,
        'user' => $resolvedUser,
        'force_password_change' => !empty($resolvedUser['force_password_change']),
        'require_role_selection' => count($availableRoles) > 1,
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
    return find_user_by_username($_SESSION['username']);
}

function yojaka_current_user(): ?array
{
    return current_user();
}

function yojaka_current_user_role(): ?string
{
    return $_SESSION['acting_role'] ?? ($_SESSION['role'] ?? (current_user()['role'] ?? null));
}

function yojaka_acting_role(): ?string
{
    return yojaka_current_user_role();
}

function is_superadmin(?array $user = null): bool
{
    $user = $user ?? current_user();
    $role = yojaka_current_user_role();
    return $role === 'superadmin' || (is_array($user) && (($user['role'] ?? null) === 'superadmin'));
}

function parse_username_parts(string $username): array
{
    $parts = explode('.', $username);
    if (count($parts) >= 3) {
        $deptSlug = array_pop($parts);
        $baseRoleId = array_pop($parts);
        $baseUser = implode('.', $parts);
        return [$baseUser, $baseRoleId, $deptSlug];
    }

    if (count($parts) === 2) {
        $deptSlug = array_pop($parts);
        $baseUser = implode('.', $parts);
        return [$baseUser, null, $deptSlug];
    }

    return [$username, null, null];
}

function get_current_department_slug(array $user): ?string
{
    [$baseUser, $baseRoleId, $deptSlug] = parse_username_parts($user['username'] ?? '');
    return $deptSlug;
}

function get_user_role_permissions(?string $username = null): array
{
    $user = $username ? find_user_by_username($username) : current_user();
    if (!$user) {
        return [];
    }

    $role = yojaka_current_user_role();
    if ($role === null) {
        return [];
    }

    if ($role === 'superadmin') {
        return ['*'];
    }

    $permissions = get_role_permissions($role);
    if (!empty($permissions)) {
        return array_values(array_unique($permissions));
    }

    [$baseUser, $baseRoleId, $deptSlug] = parse_username_parts($role);
    if ($deptSlug !== null) {
        return [];
    }

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

    global $config;
    if (empty($rolePermissions) && !empty($config['roles_permissions'][$role])) {
        $rolePermissions = $config['roles_permissions'][$role];
    }

    return array_values(array_unique($rolePermissions));
}

function is_superadmin_user(array $user): bool
{
    $roleId = $user['role'] ?? yojaka_current_user_role();
    return $roleId === 'superadmin';
}

function has_permission(array $user, string $permission): bool
{
    if ($permission === '') {
        return true;
    }

    if (is_superadmin_user($user)) {
        return true;
    }

    $roleId = $user['role'] ?? yojaka_current_user_role();
    if ($roleId === null) {
        return false;
    }

    $perms = get_role_permissions($roleId);
    if (in_array('*', $perms, true)) {
        return true;
    }

    return in_array($permission, $perms, true);
}

function user_has_permission(?string $permission, ?bool $strictMode = null): bool
{
    if ($permission === null || $permission === '') {
        return true;
    }

    if (!is_logged_in()) {
        return false;
    }

    $currentUser = yojaka_current_user();
    if ($currentUser === null) {
        return false;
    }

    if (has_permission($currentUser, $permission)) {
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

function require_permission($userOrPermission, ?string $permission = null): void
{
    $currentUser = is_array($userOrPermission) ? $userOrPermission : yojaka_current_user();
    $permissionKey = is_string($userOrPermission) && $permission === null ? $userOrPermission : $permission;

    if ($permissionKey === null) {
        return;
    }

    if (!is_array($currentUser) || !has_permission($currentUser, $permissionKey)) {
        http_response_code(403);
        echo 'You do not have permission to perform this action.';
        exit;
    }
}

?>
