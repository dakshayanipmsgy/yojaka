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
    if (isset($nodes[$nextIndex])) {
        $fileEntity['route']['current_node_index'] = $nextIndex;
        $toPosition = $nodes[$nextIndex]['position_id'] ?? null;
    } else {
        $fileEntity['route']['current_node_index'] = null;
    }
    $fileEntity['route']['history'][] = [
        'timestamp' => gmdate('c'),
        'from_position_id' => $from['position_id'] ?? null,
        'to_position_id' => $toPosition,
        'action' => 'forward',
        'user' => $user,
        'remarks' => $remarks,
    ];
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
