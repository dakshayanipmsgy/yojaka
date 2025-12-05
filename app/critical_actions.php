<?php
// Critical action queue for superadmin approvals.
require_once __DIR__ . '/departments.php';

function critical_actions_path(): string
{
    return YOJAKA_DATA_PATH . '/org/critical_actions.json';
}

function ensure_critical_actions_file(): void
{
    $dir = dirname(critical_actions_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (!file_exists(critical_actions_path())) {
        file_put_contents(critical_actions_path(), json_encode([]));
    }
}

function load_critical_actions(): array
{
    ensure_critical_actions_file();
    $data = json_decode((string) file_get_contents(critical_actions_path()), true);
    return is_array($data) ? $data : [];
}

function save_critical_actions(array $actions): bool
{
    ensure_critical_actions_file();
    return (bool) file_put_contents(critical_actions_path(), json_encode(array_values($actions), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function queue_critical_action(array $action): string
{
    $actions = load_critical_actions();
    $id = uniqid('crit_', true);
    $now = gmdate('c');
    $action = array_merge([
        'id' => $id,
        'department' => null,
        'type' => null,
        'requested_by' => null,
        'payload' => new stdClass(),
        'status' => 'pending',
        'created_at' => $now,
        'approved_at' => null,
        'rejected_at' => null,
    ], $action);
    $actions[] = $action;
    save_critical_actions($actions);
    return $id;
}

function update_critical_action_status(string $id, string $status): bool
{
    $actions = load_critical_actions();
    foreach ($actions as &$action) {
        if (($action['id'] ?? '') === $id) {
            $action['status'] = $status;
            if ($status === 'approved') {
                $action['approved_at'] = gmdate('c');
            }
            if ($status === 'rejected') {
                $action['rejected_at'] = gmdate('c');
            }
            save_critical_actions($actions);
            return true;
        }
    }
    return false;
}

function approve_critical_action(string $id): bool
{
    $actions = load_critical_actions();
    foreach ($actions as $action) {
        if (($action['id'] ?? '') !== $id) {
            continue;
        }
        $ok = execute_critical_action($action);
        if ($ok) {
            return update_critical_action_status($id, 'approved');
        }
        return false;
    }
    return false;
}

function reject_critical_action(string $id): bool
{
    return update_critical_action_status($id, 'rejected');
}

function execute_critical_action(array $action): bool
{
    $type = $action['type'] ?? '';
    $dept = $action['department'] ?? '';
    $payload = $action['payload'] ?? [];
    require_once __DIR__ . '/roles.php';
    require_once __DIR__ . '/users.php';

    switch ($type) {
        case 'role.update':
            return apply_role_update($dept, (string) ($payload['role_id'] ?? ''), (array) ($payload['changes'] ?? []));
        case 'role.delete':
            return apply_role_delete($dept, (string) ($payload['role_id'] ?? ''));
        case 'user.delete':
            return apply_user_delete($dept, (string) ($payload['username'] ?? ''));
        case 'user.password_reset':
            return apply_user_password_reset($dept, (string) ($payload['username'] ?? ''), (string) ($payload['new_password'] ?? ''));
        case 'department.update':
            if (isset($payload['status'])) {
                return set_department_status($dept, (string) $payload['status']);
            }
            return false;
        default:
            return false;
    }
}
?>
