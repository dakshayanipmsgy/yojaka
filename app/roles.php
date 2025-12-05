<?php
// Helpers for department-aware role and username handling.

function get_current_department_slug_for_user(array $user): ?string
{
    $username = $user['username'] ?? '';
    if ($username === '' || $username === 'superadmin') {
        return null;
    }

    $parts = explode('.', $username);
    if (count($parts) >= 2) {
        return (string) end($parts);
    }

    return $user['department_slug'] ?? ($user['department_id'] ?? null);
}

function make_department_role_id(string $baseRoleId, string $deptSlug): string
{
    return $baseRoleId . '.' . $deptSlug;
}

function role_matches_department(string $roleId, ?string $deptSlug): bool
{
    if ($deptSlug === null) {
        return true;
    }
    return str_ends_with($roleId, '.' . $deptSlug);
}

function extract_base_role_id(string $roleId): string
{
    $parts = explode('.', $roleId);
    return $parts[0] ?? $roleId;
}

function build_department_username(string $baseUsername, string $baseRoleId, string $deptSlug): string
{
    return $baseUsername . '.' . $baseRoleId . '.' . $deptSlug;
}

function normalize_role_definition($roleDef): array
{
    if (is_array($roleDef) && array_keys($roleDef) !== range(0, count($roleDef) - 1) && isset($roleDef['permissions'])) {
        $label = $roleDef['label'] ?? null;
        $permissions = is_array($roleDef['permissions']) ? $roleDef['permissions'] : [];
        return ['label' => $label, 'permissions' => array_values(array_unique($permissions))];
    }
    if (is_array($roleDef)) {
        return ['label' => null, 'permissions' => array_values(array_unique($roleDef))];
    }
    return ['label' => null, 'permissions' => []];
}

function filter_roles_for_department(array $roles, ?string $deptSlug, bool $includeGlobal = true): array
{
    $filtered = [];
    foreach ($roles as $roleId => $roleDef) {
        if ($includeGlobal && !str_contains($roleId, '.')) {
            $filtered[$roleId] = $roleDef;
            continue;
        }
        if ($deptSlug !== null && role_matches_department($roleId, $deptSlug)) {
            $filtered[$roleId] = $roleDef;
        }
    }
    return $filtered;
}

function format_role_label(string $roleId, array $roleDef): string
{
    $normalized = normalize_role_definition($roleDef);
    if (!empty($normalized['label'])) {
        return (string) $normalized['label'];
    }
    return ucfirst(str_replace('_', ' ', $roleId));
}

function ensure_department_admin_role(string $deptSlug): string
{
    if (!function_exists('load_permissions_config')) {
        require_once __DIR__ . '/auth.php';
    }

    $config = load_permissions_config();
    $roles = $config['roles'] ?? [];

    $baseRoleId = 'dept_admin';
    $deptRoleId = 'dept_admin.' . $deptSlug;

    if (isset($roles[$deptRoleId])) {
        return $deptRoleId;
    }

    if (!isset($roles[$baseRoleId])) {
        $roles[$baseRoleId] = [
            'label' => 'Department Admin (Base)',
            'permissions' => [
                'dept.manage_roles',
                'dept.manage_users',
                'dept.manage_templates',
                'dept.view_stats',
                'dept.raise_requests',
            ],
        ];
    }

    $base = $roles[$baseRoleId];
    $roles[$deptRoleId] = [
        'label' => ($base['label'] ?? 'Department Admin') . ' - ' . $deptSlug,
        'permissions' => $base['permissions'] ?? [],
    ];

    $config['roles'] = $roles;
    save_permissions_config($config);

    return $deptRoleId;
}
?>
