<?php
// Basic RBAC helpers for Yojaka. This will be extended in future phases.

function yojaka_has_permission(array $user, string $permission): bool
{
    // Superadmin has full access.
    if (($user['user_type'] ?? '') === 'superadmin') {
        return true;
    }

    // Department admins currently have broad access within their department.
    // We grant them all administrative permissions to bootstrap configuration screens.
    if (($user['user_type'] ?? '') === 'dept_admin') {
        if (strpos($permission, 'dept.') === 0) {
            return true;
        }
    }

    if (($user['user_type'] ?? '') === 'dept_user') {
        $rolePermissions = yojaka_current_role_permissions();
        return in_array($permission, $rolePermissions, true);
    }

    return false;
}

function yojaka_require_permission(string $permission): void
{
    $user = yojaka_current_user();
    if (!$user) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    if (!yojaka_has_permission($user, $permission)) {
        http_response_code(403);
        echo yojaka_render_view('errors/403', ['message' => 'Permission denied'], 'main');
        exit;
    }
}

function yojaka_require_dept_admin(): void
{
    $user = yojaka_current_user();
    if (!$user) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    if (($user['user_type'] ?? '') !== 'dept_admin' || ($user['status'] ?? '') !== 'active') {
        http_response_code(403);
        echo yojaka_render_view('errors/403', ['message' => 'Department admin access required'], 'main');
        exit;
    }
}

function yojaka_require_dept_user(): void
{
    $user = yojaka_current_user();
    if (!$user) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    if (($user['user_type'] ?? '') !== 'dept_user' || ($user['status'] ?? '') !== 'active') {
        http_response_code(403);
        echo yojaka_render_view('errors/403', ['message' => 'Department user access required'], 'main');
        exit;
    }
}

function yojaka_current_role_permissions(): array
{
    $user = yojaka_current_user();
    if (!$user || ($user['user_type'] ?? '') !== 'dept_user') {
        return [];
    }

    $deptSlug = $user['department_slug'] ?? '';
    $roleId = $user['role_id'] ?? null;
    if ($deptSlug === '' || !$roleId) {
        return [];
    }

    $role = yojaka_roles_find_by_role_id($deptSlug, $roleId);
    if (!$role) {
        return [];
    }

    return $role['permissions'] ?? [];
}
