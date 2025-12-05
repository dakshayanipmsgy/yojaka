<?php

require_once __DIR__ . '/core_helpers.php';
require_once __DIR__ . '/auth.php';

/**
 * Normalize record ACL fields in memory.
 *
 * Ensures keys exist:
 * - owner (string|null)
 * - assignee (string|null)
 * - allowed_users (array of usernames)
 * - allowed_roles (array of role ids)
 * - department_slug (string|null)
 */
function acl_normalize(array $record): array {
    if (!array_key_exists('owner', $record)) {
        $record['owner'] = null;
    }
    if (!array_key_exists('assignee', $record)) {
        $record['assignee'] = null;
    }
    if (!isset($record['allowed_users']) || !is_array($record['allowed_users'])) {
        $record['allowed_users'] = [];
    }
    if (!isset($record['allowed_roles']) || !is_array($record['allowed_roles'])) {
        $record['allowed_roles'] = [];
    }
    if (!array_key_exists('department_slug', $record)) {
        // If there is an older field like "department" or "office", reuse if present
        if (isset($record['department']) && is_string($record['department'])) {
            $record['department_slug'] = $record['department'];
        } else {
            $record['department_slug'] = null;
        }
    }
    return $record;
}

/**
 * Initialize ACL for a newly created record.
 *
 * - department_slug from current user.
 * - owner = current user's username.
 * - assignee defaults to owner if not provided.
 * - allowed_users = [owner, assignee].
 */
function acl_initialize_new(array $record, array $currentUser, ?string $assigneeUsername = null): array {
    $record = acl_normalize($record);

    $username = $currentUser['username'] ?? null;
    [, , $deptSlug] = parse_username_parts($username);

    $record['department_slug'] = $deptSlug;
    $record['owner'] = $username;

    if ($assigneeUsername === null) {
        $record['assignee'] = $username;
    } else {
        $record['assignee'] = $assigneeUsername;
    }

    $record['allowed_users'] = array_values(array_unique(array_filter([
        $record['owner'],
        $record['assignee']
    ])));

    if (!isset($record['allowed_roles']) || !is_array($record['allowed_roles'])) {
        $record['allowed_roles'] = [];
    }

    return $record;
}

/**
 * Can the given user view this record?
 *
 * Rules:
 * - If user has no department_slug (e.g. superadmin), deny by default.
 * - If record has department_slug and it's different, deny.
 * - Allow if:
 *   - user is owner, or
 *   - user is assignee, or
 *   - user in allowed_users, or
 *   - user role in allowed_roles.
 */
function acl_can_view(array $user, array $record): bool {
    $record = acl_normalize($record);

    $username = $user['username'] ?? null;
    $userRole = $user['role'] ?? null;

    [, , $userDeptSlug] = parse_username_parts($username);

    if ($userDeptSlug === null) {
        // superadmin or system user: no auto access
        return false;
    }

    if ($record['department_slug'] !== null && $record['department_slug'] !== $userDeptSlug) {
        return false;
    }

    if ($record['owner'] === $username || $record['assignee'] === $username) {
        return true;
    }

    if (in_array($username, $record['allowed_users'], true)) {
        return true;
    }

    if ($userRole !== null && in_array($userRole, $record['allowed_roles'], true)) {
        return true;
    }

    return false;
}

/**
 * Simple edit check: must be allowed to view AND be owner or assignee.
 * (We will refine with role-based edit later if needed.)
 */
function acl_can_edit(array $user, array $record): bool {
    if (!acl_can_view($user, $record)) {
        return false;
    }
    $username = $user['username'] ?? null;
    return $record['owner'] === $username || $record['assignee'] === $username;
}

/**
 * Add a user to allowed_users.
 */
function acl_share_with_user(array $record, string $username): array {
    $record = acl_normalize($record);
    $record['allowed_users'][] = $username;
    $record['allowed_users'] = array_values(array_unique($record['allowed_users']));
    return $record;
}

/**
 * Add a role to allowed_roles.
 */
function acl_share_with_role(array $record, string $roleId): array {
    $record = acl_normalize($record);
    $record['allowed_roles'][] = $roleId;
    $record['allowed_roles'] = array_values(array_unique($record['allowed_roles']));
    return $record;
}
