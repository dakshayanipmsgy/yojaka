<?php
// Authentication helpers for Yojaka.

function yojaka_auth_login(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['auth'] = [
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? ($user['username_base'] ?? null),
        'user_type' => $user['user_type'] ?? null,
        'department_slug' => $user['department_slug'] ?? null,
        'login_identity' => $user['login_identity'] ?? null,
        'role_id' => $user['role_id'] ?? null,
        'username_base' => $user['username_base'] ?? null,
    ];
}

function yojaka_auth_logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['auth'] = null;
        unset($_SESSION['auth']);
        session_regenerate_id(true);
    }
}

function yojaka_current_user(): ?array
{
    if (!yojaka_is_logged_in()) {
        return null;
    }

    $userId = $_SESSION['auth']['user_id'] ?? null;
    if ($userId === null) {
        return null;
    }

    $userType = $_SESSION['auth']['user_type'] ?? null;

    if ($userType === 'dept_user') {
        $deptSlug = $_SESSION['auth']['department_slug'] ?? '';
        $deptUser = $deptSlug ? yojaka_dept_users_find_by_id($deptSlug, $userId) : null;
        if ($deptUser) {
            $deptUser['user_type'] = 'dept_user';
            $deptUser['login_identity'] = $_SESSION['auth']['login_identity'] ?? null;
            $deptUser['role_id'] = $_SESSION['auth']['role_id'] ?? null;
            $deptUser['username_base'] = $_SESSION['auth']['username_base'] ?? ($deptUser['username_base'] ?? null);
        }

        return $deptUser ?: null;
    }

    $user = yojaka_find_user_by_id($userId);
    return $user ?: null;
}

function yojaka_is_logged_in(): bool
{
    return isset($_SESSION['auth']) && !empty($_SESSION['auth']['user_id']);
}

function yojaka_is_superadmin(): bool
{
    if (!yojaka_is_logged_in()) {
        return false;
    }

    return isset($_SESSION['auth']['user_type']) && $_SESSION['auth']['user_type'] === 'superadmin';
}

function yojaka_is_dept_admin(): bool
{
    if (!yojaka_is_logged_in()) {
        return false;
    }

    return isset($_SESSION['auth']['user_type']) && $_SESSION['auth']['user_type'] === 'dept_admin';
}

function yojaka_is_dept_user(): bool
{
    if (!yojaka_is_logged_in()) {
        return false;
    }

    return isset($_SESSION['auth']['user_type']) && $_SESSION['auth']['user_type'] === 'dept_user';
}

function yojaka_require_login(): void
{
    if (!yojaka_is_logged_in()) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }
}

function yojaka_require_superadmin(): void
{
    if (!yojaka_is_logged_in()) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    if (!yojaka_is_superadmin()) {
        http_response_code(403);
        echo yojaka_render_view('errors/403', [], 'main');
        exit;
    }
}
