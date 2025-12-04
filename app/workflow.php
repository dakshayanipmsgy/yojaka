<?php
// Workflow helper functions for Yojaka v1.3

function workflow_definitions(): array
{
    return [
        'rti' => [
            'states' => ['new', 'assigned', 'under_process', 'reply_ready', 'closed'],
            'default' => 'new',
            'transitions' => [
                'new' => ['assigned', 'under_process'],
                'assigned' => ['under_process', 'reply_ready', 'closed'],
                'under_process' => ['reply_ready', 'closed'],
                'reply_ready' => ['closed'],
                'closed' => [],
            ],
        ],
        'dak' => [
            'states' => ['received', 'assigned', 'in_progress', 'action_taken', 'closed'],
            'default' => 'received',
            'transitions' => [
                'received' => ['assigned', 'in_progress'],
                'assigned' => ['in_progress', 'action_taken', 'closed'],
                'in_progress' => ['action_taken', 'closed'],
                'action_taken' => ['closed'],
                'closed' => [],
            ],
        ],
        'bills' => [
            'states' => ['draft', 'submitted', 'under_check', 'approved', 'rejected', 'paid'],
            'default' => 'draft',
            'transitions' => [
                'draft' => ['submitted'],
                'submitted' => ['under_check', 'rejected'],
                'under_check' => ['approved', 'rejected'],
                'approved' => ['paid'],
                'rejected' => ['submitted'],
                'paid' => [],
            ],
        ],
    ];
}

function get_workflow_states(string $module): array
{
    $definitions = workflow_definitions();
    return $definitions[$module]['states'] ?? [];
}

function get_default_workflow_state(string $module): ?string
{
    $definitions = workflow_definitions();
    return $definitions[$module]['default'] ?? null;
}

function can_transition_workflow(string $module, string $from_state, string $to_state, ?array $user = null): bool
{
    $definitions = workflow_definitions();
    if (!isset($definitions[$module])) {
        return false;
    }
    if (!in_array($from_state, $definitions[$module]['states'], true) || !in_array($to_state, $definitions[$module]['states'], true)) {
        return false;
    }
    $allowed = $definitions[$module]['transitions'][$from_state] ?? [];
    if (!in_array($to_state, $allowed, true)) {
        return false;
    }

    // Permission gates by module
    $permissionMap = [
        'rti' => 'manage_rti',
        'dak' => 'manage_dak',
        'bills' => 'manage_bills',
    ];

    $requiredPerm = $permissionMap[$module] ?? null;
    if ($requiredPerm && user_has_permission($requiredPerm)) {
        return true;
    }

    // Allow creator/assignee to perform basic non-final transitions
    $nonFinalStates = ['new', 'assigned', 'under_process', 'in_progress', 'submitted', 'draft'];
    if ($user && in_array($from_state, $nonFinalStates, true)) {
        return true;
    }

    return false;
}

function enrich_workflow_defaults(string $module, array $record): array
{
    $defaultState = get_default_workflow_state($module);
    if (empty($record['workflow_state']) && $defaultState) {
        $record['workflow_state'] = $defaultState;
    }
    if (empty($record['last_action'])) {
        $record['last_action'] = 'created';
    }
    if (empty($record['last_action_at'])) {
        $record['last_action_at'] = $record['created_at'] ?? gmdate('c');
    }
    if (!array_key_exists('current_approver', $record)) {
        $record['current_approver'] = null;
    }
    if (!array_key_exists('approver_chain', $record)) {
        $record['approver_chain'] = [];
    }
    if (!array_key_exists('assigned_to', $record)) {
        $record['assigned_to'] = null;
    }
    if (!array_key_exists('last_sla_reminder_at', $record)) {
        $record['last_sla_reminder_at'] = null;
    }
    return $record;
}
