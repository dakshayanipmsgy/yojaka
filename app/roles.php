<?php
// Department-scoped role helpers with JSON storage.
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/auth.php';

function roles_directory(): string
{
    return YOJAKA_DATA_PATH . '/org/roles';
}

function department_roles_path(string $deptSlug): string
{
    return roles_directory() . '/' . $deptSlug . '.json';
}

function ensure_roles_storage(?string $deptSlug = null): void
{
    $dir = roles_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if ($deptSlug !== null) {
        $path = department_roles_path($deptSlug);
        if (!file_exists($path)) {
            save_department_roles($deptSlug, []);
        }
    }
}

function normalize_role(array $role, string $deptSlug): array
{
    $role['permissions'] = isset($role['permissions']) && is_array($role['permissions']) ? array_values(array_unique($role['permissions'])) : [];
    $role['label'] = $role['label'] ?? ucfirst(str_replace('_', ' ', (string) ($role['id'] ?? '')));
    $role['department'] = $deptSlug;
    return $role;
}

function load_department_roles(string $deptSlug): array
{
    ensure_roles_storage($deptSlug);
    $path = department_roles_path($deptSlug);
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    $normalized = [];
    foreach ($data as $roleId => $role) {
        if (!is_array($role)) {
            continue;
        }
        $role['id'] = $role['id'] ?? $roleId;
        $roleKey = $role['id'];
        $normalized[$roleKey] = normalize_role($role, $deptSlug);
    }
    return $normalized;
}

function save_department_roles(string $deptSlug, array $roles): bool
{
    ensure_roles_storage();
    $path = department_roles_path($deptSlug);
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    $payload = [];
    foreach ($roles as $roleId => $role) {
        if (!is_array($role)) {
            continue;
        }
        $role['id'] = $role['id'] ?? $roleId;
        $payload[$role['id']] = normalize_role($role, $deptSlug);
    }

    $success = false;
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        $success = (bool) fwrite($handle, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return $success;
}

function make_department_role_id(string $baseRoleId, string $deptSlug): string
{
    return $baseRoleId . '.' . $deptSlug;
}

function create_department_role(string $deptSlug, string $baseRoleId, string $label, array $permissions = []): array
{
    $roles = load_department_roles($deptSlug);
    $roleId = make_department_role_id($baseRoleId, $deptSlug);
    $roles[$roleId] = [
        'id' => $roleId,
        'label' => $label,
        'permissions' => array_values(array_unique($permissions)),
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
    save_department_roles($deptSlug, $roles);
    return $roles[$roleId];
}

function request_role_change(string $deptSlug, string $roleId, array $payload, string $requestedBy): string
{
    require_once __DIR__ . '/critical_actions.php';
    return queue_critical_action([
        'department' => $deptSlug,
        'type' => 'role.update',
        'requested_by' => $requestedBy,
        'payload' => ['role_id' => $roleId, 'changes' => $payload],
    ]);
}

function request_role_delete(string $deptSlug, string $roleId, string $requestedBy): string
{
    require_once __DIR__ . '/critical_actions.php';
    return queue_critical_action([
        'department' => $deptSlug,
        'type' => 'role.delete',
        'requested_by' => $requestedBy,
        'payload' => ['role_id' => $roleId],
    ]);
}

function apply_role_update(string $deptSlug, string $roleId, array $changes): bool
{
    $roles = load_department_roles($deptSlug);
    if (!isset($roles[$roleId])) {
        return false;
    }
    $roles[$roleId] = array_merge($roles[$roleId], $changes);
    $roles[$roleId]['updated_at'] = gmdate('c');
    return save_department_roles($deptSlug, $roles);
}

function apply_role_delete(string $deptSlug, string $roleId): bool
{
    $roles = load_department_roles($deptSlug);
    if (!isset($roles[$roleId])) {
        return false;
    }
    unset($roles[$roleId]);
    return save_department_roles($deptSlug, $roles);
}

function get_role_permissions(string $roleId): array
{
    [$baseUser, $baseRoleId, $deptSlug] = parse_username_parts($roleId);
    if ($deptSlug === null) {
        return [];
    }
    $roles = load_department_roles($deptSlug);
    $targetId = make_department_role_id($baseRoleId ?? $roleId, $deptSlug);
    $role = $roles[$targetId] ?? null;
    if (!$role) {
        return [];
    }
    return $role['permissions'] ?? [];
}

function list_department_roles_with_labels(string $deptSlug): array
{
    $roles = load_department_roles($deptSlug);
    $list = [];
    foreach ($roles as $roleId => $role) {
        $list[$roleId] = [
            'id' => $roleId,
            'label' => $role['label'] ?? $roleId,
            'permissions' => $role['permissions'] ?? [],
        ];
    }
    return $list;
}
?>
