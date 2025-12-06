<?php
// Record-level ACL helpers for Yojaka modules.

function yojaka_acl_can_view_record(array $user, array $record): bool
{
    if (empty($record) || empty($user)) {
        return false;
    }

    $userType = $user['user_type'] ?? '';

    // Superadmin is intentionally restricted from module records by default.
    if ($userType === 'superadmin') {
        return false;
    }

    $recordDept = $record['department_slug'] ?? null;
    $userDept = $user['department_slug'] ?? null;

    if ($recordDept === null || $userDept === null || $recordDept !== $userDept) {
        return false;
    }

    if ($userType !== 'dept_admin' && $userType !== 'dept_user') {
        return false;
    }

    $loginIdentity = $user['login_identity'] ?? ($user['username'] ?? null);
    $roleId = $user['role_id'] ?? null;

    if (($record['owner_username'] ?? null) === $loginIdentity) {
        return true;
    }

    if (($record['assignee_username'] ?? null) === $loginIdentity) {
        return true;
    }

    $allowedUsers = $record['allowed_users'] ?? [];
    if (is_array($allowedUsers) && $loginIdentity !== null && in_array($loginIdentity, $allowedUsers, true)) {
        return true;
    }

    $allowedRoles = $record['allowed_roles'] ?? [];
    if (is_array($allowedRoles) && $roleId !== null && in_array($roleId, $allowedRoles, true)) {
        return true;
    }

    return false;
}

function yojaka_acl_can_edit_record(array $user, array $record): bool
{
    if (!yojaka_acl_can_view_record($user, $record)) {
        return false;
    }

    $loginIdentity = $user['login_identity'] ?? ($user['username'] ?? null);

    if (($record['owner_username'] ?? null) === $loginIdentity) {
        return true;
    }

    if (($record['assignee_username'] ?? null) === $loginIdentity) {
        return true;
    }

    return false;
}

function yojaka_require_record_view(array $record): void
{
    $user = yojaka_current_user();
    if (!$user) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    if (!yojaka_acl_can_view_record($user, $record)) {
        http_response_code(403);
        echo yojaka_render_view('errors/403', ['message' => 'Access denied'], 'main');
        exit;
    }
}

function yojaka_require_record_edit(array $record): void
{
    $user = yojaka_current_user();
    if (!$user) {
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    if (!yojaka_acl_can_edit_record($user, $record)) {
        http_response_code(403);
        echo yojaka_render_view('errors/403', ['message' => 'Edit not allowed'], 'main');
        exit;
    }
}
