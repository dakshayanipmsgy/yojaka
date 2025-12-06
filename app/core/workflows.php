<?php
// Workflow template helpers for Yojaka.

function yojaka_workflows_base_path(string $deptSlug): string
{
    return rtrim(yojaka_config('paths.data_path'), '/') . '/departments/' . $deptSlug . '/workflows';
}

function yojaka_workflows_file_path(string $deptSlug): string
{
    $base = yojaka_workflows_base_path($deptSlug);
    if (!is_dir($base)) {
        mkdir($base, 0777, true);
    }

    return $base . '/workflows.json';
}

function yojaka_workflows_load_for_department(string $deptSlug): array
{
    $filePath = yojaka_workflows_file_path($deptSlug);

    if (!file_exists($filePath)) {
        yojaka_workflows_seed_default($deptSlug);
    }

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function yojaka_workflows_save_for_department(string $deptSlug, array $workflows)
{
    $filePath = yojaka_workflows_file_path($deptSlug);
    $json = json_encode(array_values($workflows), JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function yojaka_workflows_find(string $deptSlug, string $workflowId): ?array
{
    $workflows = yojaka_workflows_load_for_department($deptSlug);

    foreach ($workflows as $workflow) {
        if (($workflow['id'] ?? '') === $workflowId) {
            return $workflow;
        }
    }

    return null;
}

function yojaka_workflows_list_for_module(string $deptSlug, string $module): array
{
    $workflows = yojaka_workflows_load_for_department($deptSlug);
    $filtered = [];

    foreach ($workflows as $workflow) {
        if (($workflow['module'] ?? '') === $module) {
            $filtered[] = $workflow;
        }
    }

    return $filtered;
}

function yojaka_workflow_get_step(array $workflow, string $stepId): ?array
{
    foreach ($workflow['steps'] ?? [] as $step) {
        if (($step['id'] ?? '') === $stepId) {
            return $step;
        }
    }

    return null;
}

function yojaka_workflow_allowed_next_steps(array $workflow, string $currentStepId): array
{
    $currentStep = yojaka_workflow_get_step($workflow, $currentStepId);
    if (!$currentStep) {
        return [];
    }

    $allowed = $currentStep['allow_forward_to'] ?? [];
    $result = [];

    foreach ($workflow['steps'] ?? [] as $step) {
        if (in_array($step['id'] ?? '', $allowed, true)) {
            $result[] = $step;
        }
    }

    return $result;
}

function yojaka_workflow_allowed_prev_steps(array $workflow, string $currentStepId): array
{
    $currentStep = yojaka_workflow_get_step($workflow, $currentStepId);
    if (!$currentStep) {
        return [];
    }

    $allowed = $currentStep['allow_return_to'] ?? [];
    $result = [];

    foreach ($workflow['steps'] ?? [] as $step) {
        if (in_array($step['id'] ?? '', $allowed, true)) {
            $result[] = $step;
        }
    }

    return $result;
}

function yojaka_workflows_seed_default(string $deptSlug): void
{
    $filePath = yojaka_workflows_file_path($deptSlug);
    $existing = [];
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    $defaultDakWorkflow = [
        'id' => 'dak_default',
        'module' => 'dak',
        'name' => 'Default Dak Route',
        'description' => 'Standard dak movement route',
        'steps' => [
            [
                'id' => 'clerk',
                'label' => 'Clerk',
                'allowed_roles' => ['clerk.' . $deptSlug],
                'allow_forward_to' => ['ee'],
                'allow_return_to' => [],
                'is_terminal' => false,
                'default_due_days' => 3,
            ],
            [
                'id' => 'ee',
                'label' => 'Executive Engineer',
                'allowed_roles' => ['ee.' . $deptSlug],
                'allow_forward_to' => ['se'],
                'allow_return_to' => ['clerk'],
                'is_terminal' => false,
                'default_due_days' => 5,
            ],
            [
                'id' => 'se',
                'label' => 'Superintending Engineer',
                'allowed_roles' => ['se.' . $deptSlug],
                'allow_forward_to' => [],
                'allow_return_to' => ['ee'],
                'is_terminal' => true,
                'default_due_days' => 7,
            ],
        ],
    ];

    $defaultRtiWorkflow = [
        'id' => 'rti_default',
        'module' => 'rti',
        'name' => 'Default RTI Route',
        'description' => 'Clerk → PIO → FAA route for RTI cases.',
        'steps' => [
            [
                'id' => 'rticlerk',
                'label' => 'RTI Clerk',
                'allowed_roles' => ['rticlerk.' . $deptSlug],
                'allow_forward_to' => ['pio'],
                'allow_return_to' => [],
                'is_terminal' => false,
                'default_due_days' => 0,
            ],
            [
                'id' => 'pio',
                'label' => 'Public Information Officer',
                'allowed_roles' => ['pio.' . $deptSlug],
                'allow_forward_to' => ['faa'],
                'allow_return_to' => ['rticlerk'],
                'is_terminal' => false,
                'default_due_days' => 30,
            ],
            [
                'id' => 'faa',
                'label' => 'First Appellate Authority',
                'allowed_roles' => ['faa.' . $deptSlug],
                'allow_forward_to' => [],
                'allow_return_to' => ['pio'],
                'is_terminal' => true,
                'default_due_days' => 45,
            ],
        ],
    ];

    $workflows = [];
    $dakExists = false;
    $rtiExists = false;

    foreach ($existing as $workflow) {
        if (($workflow['id'] ?? '') === 'dak_default') {
            $dakExists = true;
        }
        if (($workflow['id'] ?? '') === 'rti_default') {
            $rtiExists = true;
        }
        $workflows[] = $workflow;
    }

    if (!$dakExists) {
        $workflows[] = $defaultDakWorkflow;
    }

    if (!$rtiExists) {
        $workflows[] = $defaultRtiWorkflow;
    }

    yojaka_workflows_save_for_department($deptSlug, $workflows);
}
