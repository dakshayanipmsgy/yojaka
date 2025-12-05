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

// --- Prompt F workflow engine ---

function workflow_data_path_for_department(string $deptSlug): string
{
    return YOJAKA_DATA_PATH . '/org/workflows/' . $deptSlug . '.json';
}

function load_workflows_for_department(string $deptSlug): array
{
    $path = workflow_data_path_for_department($deptSlug);
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_workflows_for_department(string $deptSlug, array $workflows): bool
{
    $path = workflow_data_path_for_department($deptSlug);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $encoded = json_encode($workflows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return (bool) file_put_contents($path, $encoded, LOCK_EX);
}

function get_workflow_template(array $deptWorkflows, string $workflowId): ?array
{
    return $deptWorkflows[$workflowId] ?? null;
}

function workflow_find_step(array $workflowTemplate, string $stepId): ?array
{
    foreach ($workflowTemplate['steps'] ?? [] as $step) {
        if (($step['id'] ?? '') === $stepId) {
            return $step;
        }
    }
    return null;
}

function workflow_first_step(array $workflowTemplate): ?array
{
    $steps = $workflowTemplate['steps'] ?? [];
    return $steps[0] ?? null;
}

function workflow_record_department(array $record, ?array $currentUser = null): ?string
{
    if (!empty($record['department_slug'])) {
        return $record['department_slug'];
    }
    if ($currentUser) {
        [, , $deptSlug] = acl_parse_username_parts($currentUser['username'] ?? null);
        return $deptSlug;
    }
    return null;
}

function initialize_record_workflow(array $record, array $currentUser, array $workflowTemplate, ?string $assigneeUsername): array
{
    $record = acl_normalize($record);
    [, , $userDept] = acl_parse_username_parts($currentUser['username'] ?? null);
    if (!$userDept) {
        return $record;
    }
    $templateDept = $workflowTemplate['department_slug'] ?? $userDept;
    if ($templateDept !== $userDept) {
        return $record;
    }

    $firstStep = workflow_first_step($workflowTemplate);
    if (!$firstStep) {
        return $record;
    }

    $record['workflow'] = [
        'workflow_id' => $workflowTemplate['id'] ?? '',
        'current_step' => $firstStep['id'] ?? null,
        'status' => 'in_progress',
        'history' => [],
    ];

    $chosenAssignee = $assigneeUsername;
    $userRole = $currentUser['role'] ?? null;
    if ($chosenAssignee === null && $userRole && in_array($userRole, $firstStep['allowed_roles'] ?? [], true)) {
        $chosenAssignee = $currentUser['username'] ?? null;
    }

    $record['workflow']['history'][] = [
        'timestamp' => date('c'),
        'action' => 'created',
        'from_step' => null,
        'to_step' => $firstStep['id'] ?? null,
        'from_user' => null,
        'to_user' => $chosenAssignee,
        'actor_user' => $currentUser['username'] ?? null,
        'comment' => 'Workflow initialized',
        'external_actor' => null,
    ];

    $record['assignee'] = $chosenAssignee;
    $record['allowed_users'] = array_values(array_unique(array_filter(array_merge($record['allowed_users'], [$chosenAssignee, $record['owner']]))));
    return $record;
}

function workflow_get_allowed_actions(array $record, array $currentUser, array $workflowTemplate): array
{
    $actions = [
        'forward_to' => [],
        'return_to' => [],
        'may_close' => false,
    ];
    $record = acl_normalize($record);
    if (!acl_can_edit($currentUser, $record)) {
        return $actions;
    }
    [, , $userDept] = acl_parse_username_parts($currentUser['username'] ?? null);
    if ($userDept === null || $record['department_slug'] !== $userDept) {
        return $actions;
    }

    $currentStepId = $record['workflow']['current_step'] ?? null;
    $currentStep = $currentStepId ? workflow_find_step($workflowTemplate, $currentStepId) : null;
    if (!$currentStep) {
        return $actions;
    }
    $userRole = $currentUser['role'] ?? null;
    if ($userRole && !in_array($userRole, $currentStep['allowed_roles'] ?? [], true)) {
        return $actions;
    }

    $actions['forward_to'] = $currentStep['allow_forward_to'] ?? [];
    $actions['return_to'] = $currentStep['allow_return_to'] ?? [];
    $actions['may_close'] = !empty($currentStep['is_terminal']);
    return $actions;
}

function workflow_append_history(array $record, array $entry): array
{
    if (!isset($record['workflow']['history']) || !is_array($record['workflow']['history'])) {
        $record['workflow']['history'] = [];
    }
    $record['workflow']['history'][] = $entry;
    return $record;
}

function workflow_advance(array $record, array $currentUser, array $workflowTemplate, string $targetStepId, ?string $toUser, string $comment = '', ?string $externalActorName = null): array
{
    $record = acl_normalize($record);
    if (!acl_can_edit($currentUser, $record)) {
        return $record;
    }
    [, , $userDept] = acl_parse_username_parts($currentUser['username'] ?? null);
    if ($userDept === null || $record['department_slug'] !== $userDept) {
        return $record;
    }

    $currentStepId = $record['workflow']['current_step'] ?? null;
    $currentStep = $currentStepId ? workflow_find_step($workflowTemplate, $currentStepId) : null;
    $targetStep = workflow_find_step($workflowTemplate, $targetStepId);
    if (!$currentStep || !$targetStep) {
        return $record;
    }

    $userRole = $currentUser['role'] ?? null;
    if ($userRole && !in_array($userRole, $currentStep['allowed_roles'] ?? [], true)) {
        return $record;
    }
    if (!in_array($targetStepId, $currentStep['allow_forward_to'] ?? [], true)) {
        return $record;
    }

    $previousAssignee = $record['assignee'] ?? null;
    $record['workflow']['current_step'] = $targetStepId;
    if (!empty($targetStep['is_terminal'])) {
        $record['workflow']['status'] = 'closed';
    }

    // External actor handling: keep existing assignee to avoid ACL gaps.
    $newAssignee = $toUser ?? $previousAssignee;
    $historyEntry = [
        'timestamp' => date('c'),
        'action' => 'forwarded',
        'from_step' => $currentStepId,
        'to_step' => $targetStepId,
        'from_user' => $previousAssignee,
        'to_user' => $toUser,
        'actor_user' => $currentUser['username'] ?? null,
        'comment' => $comment,
        'external_actor' => $toUser ? null : $externalActorName,
    ];
    $record = workflow_append_history($record, $historyEntry);

    $record['assignee'] = $newAssignee;
    if ($newAssignee) {
        $record['allowed_users'][] = $newAssignee;
    }
    $record['allowed_users'] = array_values(array_unique(array_filter($record['allowed_users'])));
    return $record;
}

function workflow_return(array $record, array $currentUser, array $workflowTemplate, string $targetStepId, ?string $toUser, string $comment = '', ?string $externalActorName = null): array
{
    $record = acl_normalize($record);
    if (!acl_can_edit($currentUser, $record)) {
        return $record;
    }
    [, , $userDept] = acl_parse_username_parts($currentUser['username'] ?? null);
    if ($userDept === null || $record['department_slug'] !== $userDept) {
        return $record;
    }

    $currentStepId = $record['workflow']['current_step'] ?? null;
    $currentStep = $currentStepId ? workflow_find_step($workflowTemplate, $currentStepId) : null;
    $targetStep = workflow_find_step($workflowTemplate, $targetStepId);
    if (!$currentStep || !$targetStep) {
        return $record;
    }

    $userRole = $currentUser['role'] ?? null;
    if ($userRole && !in_array($userRole, $currentStep['allowed_roles'] ?? [], true)) {
        return $record;
    }
    if (!in_array($targetStepId, $currentStep['allow_return_to'] ?? [], true)) {
        return $record;
    }

    $previousAssignee = $record['assignee'] ?? null;
    $record['workflow']['current_step'] = $targetStepId;
    $record['workflow']['status'] = 'in_progress';

    $newAssignee = $toUser ?? $previousAssignee;
    $historyEntry = [
        'timestamp' => date('c'),
        'action' => 'returned',
        'from_step' => $currentStepId,
        'to_step' => $targetStepId,
        'from_user' => $previousAssignee,
        'to_user' => $toUser,
        'actor_user' => $currentUser['username'] ?? null,
        'comment' => $comment,
        'external_actor' => $toUser ? null : $externalActorName,
    ];
    $record = workflow_append_history($record, $historyEntry);

    $record['assignee'] = $newAssignee;
    if ($newAssignee) {
        $record['allowed_users'][] = $newAssignee;
    }
    $record['allowed_users'] = array_values(array_unique(array_filter($record['allowed_users'])));
    return $record;
}

function workflow_close(array $record, array $currentUser, array $workflowTemplate, string $comment = ''): array
{
    $record = acl_normalize($record);
    if (!acl_can_edit($currentUser, $record)) {
        return $record;
    }
    [, , $userDept] = acl_parse_username_parts($currentUser['username'] ?? null);
    if ($userDept === null || $record['department_slug'] !== $userDept) {
        return $record;
    }

    $currentStepId = $record['workflow']['current_step'] ?? null;
    $currentStep = $currentStepId ? workflow_find_step($workflowTemplate, $currentStepId) : null;
    if (!$currentStep || empty($currentStep['is_terminal'])) {
        return $record;
    }

    $previousAssignee = $record['assignee'] ?? null;
    $record['workflow']['status'] = 'closed';

    $historyEntry = [
        'timestamp' => date('c'),
        'action' => 'closed',
        'from_step' => $currentStepId,
        'to_step' => $currentStepId,
        'from_user' => $previousAssignee,
        'to_user' => $previousAssignee,
        'actor_user' => $currentUser['username'] ?? null,
        'comment' => $comment,
        'external_actor' => null,
    ];
    return workflow_append_history($record, $historyEntry);
}

function apply_workflow_update_structure(string $deptSlug, array $payload): bool
{
    $workflowId = (string) ($payload['workflow_id'] ?? '');
    $newTemplate = (array) ($payload['template'] ?? []);
    if ($workflowId === '' || empty($newTemplate)) {
        return false;
    }
    $workflows = load_workflows_for_department($deptSlug);
    $workflows[$workflowId] = $newTemplate;
    return save_workflows_for_department($deptSlug, $workflows);
}

function apply_workflow_delete(string $deptSlug, array $payload): bool
{
    $workflowId = (string) ($payload['workflow_id'] ?? '');
    if ($workflowId === '') {
        return false;
    }
    $workflows = load_workflows_for_department($deptSlug);
    if (!isset($workflows[$workflowId])) {
        return false;
    }
    unset($workflows[$workflowId]);
    return save_workflows_for_department($deptSlug, $workflows);
}
