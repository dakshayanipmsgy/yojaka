<?php

function file_flow_initialize(array &$fileEntity, string $fileType, string $departmentId, string $officeId, ?string $routeTemplateId = null): void
{
    $route = null;
    if ($routeTemplateId) {
        $route = get_route_by_id($officeId, $routeTemplateId);
    }
    if (!$route) {
        $route = get_default_route_for_file_type($officeId, $fileType, $departmentId);
    }
    if (!$route) {
        $fileEntity['route'] = [
            'route_template_id' => null,
            'nodes' => [],
            'current_node_index' => null,
            'history' => [],
        ];
        return;
    }
    $nodes = sort_route_nodes($route['nodes'] ?? []);
    foreach ($nodes as &$node) {
        $node['status'] = $node['status'] ?? 'pending';
        $node['completed_at'] = $node['completed_at'] ?? null;
    }
    unset($node);
    $fileEntity['route'] = [
        'route_template_id' => $route['id'] ?? null,
        'nodes' => $nodes,
        'current_node_index' => 0,
        'history' => [],
    ];
    file_flow_sync_assignment($fileEntity);
    $fileEntity = file_flow_apply_acceptance_defaults($fileEntity);
}

function file_flow_get_current_node(array $fileEntity): ?array
{
    $route = $fileEntity['route'] ?? null;
    if (!$route || $route['current_node_index'] === null) {
        return null;
    }
    return $route['nodes'][$route['current_node_index']] ?? null;
}

function file_flow_forward(array &$fileEntity, string $user, string $remarks = ''): bool
{
    if (empty($fileEntity['route']) || $fileEntity['route']['current_node_index'] === null) {
        return false;
    }
    $index = $fileEntity['route']['current_node_index'];
    $nodes = &$fileEntity['route']['nodes'];
    if (!isset($nodes[$index])) {
        return false;
    }
    $from = $nodes[$index];
    $nodes[$index]['status'] = 'completed';
    $nodes[$index]['completed_at'] = gmdate('c');
    $nextIndex = $index + 1;
    $toPosition = null;
    $nextNode = $nodes[$nextIndex] ?? null;
    if ($nextNode !== null) {
        $fileEntity['route']['current_node_index'] = $nextIndex;
        $toPosition = $nextNode['position_id'] ?? null;
    } else {
        $fileEntity['route']['current_node_index'] = null;
    }
    $historyEntry = [
        'timestamp' => gmdate('c'),
        'from_position_id' => $from['position_id'] ?? null,
        'to_position_id' => $toPosition,
        'action' => 'forward',
        'user' => $user,
        'from_user' => $user,
        'to_user' => $nextNode['user_username'] ?? null,
        'remarks' => $remarks,
        'acceptance' => [
            'status' => 'accepted',
            'accepted_by' => $nextNode['user_username'] ?? $user,
            'accepted_at' => gmdate('c'),
            'rejected_reason' => null,
        ],
    ];
    $fileEntity['route']['history'][] = $historyEntry;
    $fileEntity['pending_acceptance'] = false;
    $fileEntity['current_holder'] = $fileEntity['assigned_to'] ?? ($nodes[$nextIndex]['user_username'] ?? $user);
    file_flow_sync_assignment($fileEntity);
    return true;
}

function file_flow_return(array &$fileEntity, string $user, string $remarks = '', int $targetNodeIndex = -1): bool
{
    if (empty($fileEntity['route']) || $fileEntity['route']['current_node_index'] === null) {
        return false;
    }
    $index = $fileEntity['route']['current_node_index'];
    if ($targetNodeIndex === -1) {
        $targetNodeIndex = $index - 1;
    }
    if ($targetNodeIndex < 0 || $targetNodeIndex >= $index) {
        return false;
    }
    $nodes = &$fileEntity['route']['nodes'];
    $fileEntity['route']['current_node_index'] = $targetNodeIndex;
    $fileEntity['route']['history'][] = [
        'timestamp' => gmdate('c'),
        'from_position_id' => $nodes[$index]['position_id'] ?? null,
        'to_position_id' => $nodes[$targetNodeIndex]['position_id'] ?? null,
        'action' => 'return',
        'user' => $user,
        'remarks' => $remarks,
    ];
    file_flow_sync_assignment($fileEntity);
    return true;
}

function file_flow_forward_with_handover(array &$fileEntity, string $fromUser, string $toUser, string $remarks = ''): bool
{
    if (empty($fileEntity['route']) || $fileEntity['route']['current_node_index'] === null) {
        return false;
    }
    $fileEntity = file_flow_apply_acceptance_defaults($fileEntity);
    $officeId = $fileEntity['office_id'] ?? get_current_office_id();
    $index = $fileEntity['route']['current_node_index'];
    $nodes = &$fileEntity['route']['nodes'];
    if (!isset($nodes[$index])) {
        return false;
    }
    $from = $nodes[$index];
    $nodes[$index]['status'] = 'completed';
    $nodes[$index]['completed_at'] = gmdate('c');
    $nextIndex = $index + 1;
    $toPosition = null;
    if (isset($nodes[$nextIndex])) {
        $fileEntity['route']['current_node_index'] = $nextIndex;
        $toPosition = $nodes[$nextIndex]['position_id'] ?? null;
        $nodes[$nextIndex]['user_username'] = $nodes[$nextIndex]['user_username'] ?? $toUser;
    } else {
        $fileEntity['route']['current_node_index'] = null;
    }

    $fromPos = get_user_position($fromUser, $officeId);
    $toPos = get_user_position($toUser, $officeId);
    $historyEntry = [
        'timestamp' => gmdate('c'),
        'from_position_id' => $from['position_id'] ?? ($fromPos['id'] ?? null),
        'to_position_id' => $toPosition ?? ($toPos['id'] ?? null),
        'action' => 'forward',
        'user' => $fromUser,
        'from_user' => $fromUser,
        'to_user' => $toUser,
        'remarks' => $remarks,
        'acceptance' => [
            'status' => 'pending',
            'accepted_by' => null,
            'accepted_at' => null,
            'rejected_reason' => null,
        ],
    ];
    $fileEntity['route']['history'][] = $historyEntry;
    $fileEntity['pending_acceptance'] = true;
    $fileEntity['current_holder'] = $toUser;
    $fileEntity['assigned_to'] = $toUser;
    $fileEntity['workflow_state'] = $fileEntity['workflow_state'] ?? 'under_transfer';
    return true;
}

function file_flow_accept_handover(array &$fileEntity, string $currentUser, string $remarks = ''): bool
{
    $fileEntity = file_flow_apply_acceptance_defaults($fileEntity);
    if (empty($fileEntity['pending_acceptance']) || ($fileEntity['current_holder'] ?? null) !== $currentUser) {
        return false;
    }
    $history = &$fileEntity['route']['history'];
    if (empty($history)) {
        return false;
    }
    $lastIndex = count($history) - 1;
    $acceptance = $history[$lastIndex]['acceptance'] ?? null;
    if (!is_array($acceptance) || ($acceptance['status'] ?? 'accepted') !== 'pending') {
        return false;
    }

    $history[$lastIndex]['acceptance'] = [
        'status' => 'accepted',
        'accepted_by' => $currentUser,
        'accepted_at' => gmdate('c'),
        'rejected_reason' => null,
    ];
    if ($remarks !== '') {
        $history[$lastIndex]['remarks'] = trim(($history[$lastIndex]['remarks'] ?? '') . ' ' . $remarks);
    }
    $fileEntity['pending_acceptance'] = false;
    $fileEntity['current_holder'] = $currentUser;
    $fileEntity['workflow_state'] = $fileEntity['workflow_state'] === 'under_transfer' ? 'in_progress' : ($fileEntity['workflow_state'] ?? 'in_progress');
    $fileEntity['last_action'] = 'file_accepted';
    $fileEntity['last_action_at'] = gmdate('c');
    return true;
}

function file_flow_reject_handover(array &$fileEntity, string $currentUser, string $reason): bool
{
    $fileEntity = file_flow_apply_acceptance_defaults($fileEntity);
    if (empty($fileEntity['pending_acceptance']) || ($fileEntity['current_holder'] ?? null) !== $currentUser) {
        return false;
    }
    $history = &$fileEntity['route']['history'];
    if (empty($history)) {
        return false;
    }
    $lastIndex = count($history) - 1;
    $acceptance = $history[$lastIndex]['acceptance'] ?? null;
    if (!is_array($acceptance) || ($acceptance['status'] ?? 'accepted') !== 'pending') {
        return false;
    }

    $previousUser = $history[$lastIndex]['from_user'] ?? null;
    $officeId = $fileEntity['office_id'] ?? get_current_office_id();
    $fromPos = get_user_position($currentUser, $officeId);
    $prevPos = $previousUser ? get_user_position($previousUser, $officeId) : null;
    $history[$lastIndex]['acceptance'] = [
        'status' => 'rejected',
        'accepted_by' => null,
        'accepted_at' => null,
        'rejected_reason' => $reason,
    ];
    $history[] = [
        'timestamp' => gmdate('c'),
        'from_position_id' => $fromPos['id'] ?? null,
        'to_position_id' => $prevPos['id'] ?? null,
        'action' => 'return_after_reject',
        'user' => $currentUser,
        'from_user' => $currentUser,
        'to_user' => $previousUser,
        'remarks' => $reason,
        'acceptance' => [
            'status' => 'accepted',
            'accepted_by' => $previousUser,
            'accepted_at' => gmdate('c'),
            'rejected_reason' => null,
        ],
    ];
    $fileEntity['pending_acceptance'] = false;
    $fileEntity['assigned_to'] = $previousUser;
    $fileEntity['current_holder'] = $previousUser;
    $fileEntity['workflow_state'] = 'rerouted';
    $fileEntity['route']['current_node_index'] = max(0, ($fileEntity['route']['current_node_index'] ?? 1) - 1);
    $fileEntity['last_action'] = 'file_rejected';
    $fileEntity['last_action_at'] = gmdate('c');
    return true;
}

function file_flow_reroute(array &$fileEntity, string $user, string $newPositionId, string $remarks = ''): bool
{
    if (empty($fileEntity['route'])) {
        $fileEntity['route'] = [
            'route_template_id' => null,
            'nodes' => [],
            'current_node_index' => null,
            'history' => [],
        ];
    }
    $fileEntity['route']['history'][] = [
        'timestamp' => gmdate('c'),
        'from_position_id' => null,
        'to_position_id' => $newPositionId,
        'action' => 'reroute',
        'user' => $user,
        'remarks' => $remarks,
    ];
    $fileEntity['assigned_to'] = null;
    $fileEntity['current_position_override'] = $newPositionId;
    return true;
}

function file_flow_sync_assignment(array &$fileEntity): void
{
    $route = $fileEntity['route'] ?? null;
    if (!$route) {
        return;
    }
    if ($route['current_node_index'] === null) {
        $fileEntity['assigned_to'] = null;
        return;
    }
    $node = $route['nodes'][$route['current_node_index']] ?? null;
    if ($node) {
        $fileEntity['assigned_to'] = $node['user_username'] ?? null;
    }
}

function file_flow_apply_acceptance_defaults(array $fileEntity): array
{
    if (!isset($fileEntity['pending_acceptance'])) {
        $fileEntity['pending_acceptance'] = false;
    }
    if (!isset($fileEntity['current_holder'])) {
        $fileEntity['current_holder'] = $fileEntity['assigned_to'] ?? null;
    }
    if (!isset($fileEntity['route']['history']) || !is_array($fileEntity['route']['history'])) {
        return $fileEntity;
    }
    foreach ($fileEntity['route']['history'] as &$row) {
        if (!isset($row['acceptance']) || !is_array($row['acceptance'])) {
            $row['acceptance'] = [
                'status' => 'accepted',
                'accepted_by' => $row['to_user'] ?? ($row['user'] ?? null),
                'accepted_at' => $row['timestamp'] ?? gmdate('c'),
                'rejected_reason' => null,
            ];
        }
    }
    unset($row);
    return $fileEntity;
}

function file_flow_get_available_actions(array $fileEntity, string $currentUser): array
{
    $actions = [];
    $node = file_flow_get_current_node($fileEntity);
    if (!$node) {
        return $actions;
    }
    if (($node['user_username'] ?? null) === $currentUser || user_has_permission('manage_dak')) {
        $actions[] = 'forward';
        if (($fileEntity['route']['current_node_index'] ?? 0) > 0) {
            $actions[] = 'return';
        }
        $actions[] = 'reroute';
    }
    return $actions;
}
