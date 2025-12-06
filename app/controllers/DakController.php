<?php
class DakController
{
    protected function requireDeptUserOrAdmin(): array
    {
        yojaka_require_login();

        $user = yojaka_current_user();
        if (!$user || !in_array($user['user_type'] ?? '', ['dept_user', 'dept_admin'], true)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Department access required'], 'main');
            exit;
        }

        if (($user['status'] ?? '') !== 'active') {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Inactive account'], 'main');
            exit;
        }

        return $user;
    }

    protected function requireDakPermission(array $user): void
    {
        if (($user['user_type'] ?? '') === 'dept_admin') {
            return;
        }

        if (!yojaka_has_permission($user, 'dak.create')) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Dak permission required'], 'main');
            exit;
        }
    }

    protected function loadWorkflowActionContext(array $user, string $id, string $permission): array
    {
        $deptSlug = $user['department_slug'] ?? '';
        $record = $id ? yojaka_dak_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            echo yojaka_render_view('errors/404', ['route' => 'dak/workflow'], 'main');
            exit;
        }

        if (!yojaka_acl_can_view_record($user, $record)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Access denied'], 'main');
            exit;
        }

        if (!yojaka_has_permission($user, $permission)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Permission denied'], 'main');
            exit;
        }

        if (($record['status'] ?? '') === 'closed') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'This dak is already closed.'], 'main');
            exit;
        }

        $workflowId = $record['workflow']['workflow_id'] ?? null;
        if (!$workflowId) {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Workflow missing for this record.'], 'main');
            exit;
        }

        $workflow = yojaka_workflows_find($deptSlug, $workflowId);
        if (!$workflow) {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Workflow template not found for this record.'], 'main');
            exit;
        }

        $currentStepId = $record['workflow']['current_step'] ?? '';
        $currentStep = $currentStepId ? yojaka_workflow_get_step($workflow, $currentStepId) : null;
        if (!$currentStep) {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Current workflow step is invalid.'], 'main');
            exit;
        }

        $roleId = $user['role_id'] ?? null;
        if (!$roleId || !in_array($roleId, $currentStep['allowed_roles'] ?? [], true)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Workflow action not allowed for your role.'], 'main');
            exit;
        }

        return [
            'deptSlug' => $deptSlug,
            'record' => $record,
            'workflow' => $workflow,
            'currentStep' => $currentStep,
            'currentStepId' => $currentStepId,
        ];
    }

    public function list()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $deptSlug = $user['department_slug'] ?? '';
        yojaka_dak_ensure_storage($deptSlug);

        $records = yojaka_dak_list_records_for_user($deptSlug, $user);

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'current_step' => trim($_GET['current_step'] ?? ''),
            'assignee' => trim($_GET['assignee'] ?? ''),
            'created_from' => trim($_GET['created_from'] ?? ''),
            'created_to' => trim($_GET['created_to'] ?? ''),
        ];

        $records = array_values(array_filter($records, function (array $record) use ($filters) {
            $q = strtolower($filters['q']);
            if ($q !== '') {
                $haystacks = [
                    strtolower($record['title'] ?? ''),
                    strtolower($record['reference_no'] ?? ''),
                    strtolower($record['subject'] ?? ''),
                ];

                $match = false;
                foreach ($haystacks as $text) {
                    if ($text !== '' && strpos($text, $q) !== false) {
                        $match = true;
                        break;
                    }
                }

                if (!$match) {
                    return false;
                }
            }

            if ($filters['status'] !== '' && ($record['status'] ?? '') !== $filters['status']) {
                return false;
            }

            if ($filters['current_step'] !== '') {
                $currentStep = $record['workflow']['current_step'] ?? '';
                if ($currentStep !== $filters['current_step']) {
                    return false;
                }
            }

            if ($filters['assignee'] !== '' && ($record['assignee_username'] ?? '') !== $filters['assignee']) {
                return false;
            }

            $createdAt = $record['created_at'] ?? '';
            $createdDate = $createdAt ? date_create_immutable($createdAt) : null;
            $createdDay = $createdDate ? $createdDate->format('Y-m-d') : null;

            if ($filters['created_from'] !== '' && ($createdDay === null || strcmp($createdDay, $filters['created_from']) < 0)) {
                return false;
            }

            if ($filters['created_to'] !== '' && ($createdDay === null || strcmp($createdDay, $filters['created_to']) > 0)) {
                return false;
            }

            return true;
        }));

        $workflows = yojaka_workflows_list_for_module($deptSlug, 'dak');
        $workflowSteps = [];
        foreach ($workflows as $workflow) {
            foreach ($workflow['steps'] ?? [] as $step) {
                $id = $step['id'] ?? '';
                if ($id === '') {
                    continue;
                }
                $workflowSteps[$id] = $step['label'] ?? $id;
            }
        }

        $deptUsers = yojaka_dept_users_load($deptSlug);
        $assignees = [];
        foreach ($deptUsers as $deptUser) {
            $display = $deptUser['display_name'] ?? ($deptUser['username_base'] ?? '');
            foreach ($deptUser['login_identities'] ?? [] as $identity) {
                $assignees[$identity] = $display . ' (' . $identity . ')';
            }
        }

        $adminIdentity = $user['login_identity'] ?? ($user['username'] ?? '');
        if ($adminIdentity !== '' && !isset($assignees[$adminIdentity])) {
            $assignees[$adminIdentity] = ($user['display_name'] ?? 'Department Admin') . ' (' . $adminIdentity . ')';
        }

        $data = [
            'title' => 'Dak – My Files',
            'records' => $records,
            'filters' => $filters,
            'statusOptions' => ['draft' => 'Draft', 'in_progress' => 'In Progress', 'closed' => 'Closed'],
            'workflowSteps' => $workflowSteps,
            'assignees' => $assignees,
        ];

        return yojaka_render_view('dak/list', $data, 'main');
    }

    public function create()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $errors = [];
        $deptSlug = $user['department_slug'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $referenceNo = trim($_POST['reference_no'] ?? '');
            $receivedDate = trim($_POST['received_date'] ?? '');
            $receivedVia = trim($_POST['received_via'] ?? '');
            $fromName = trim($_POST['from_name'] ?? '');
            $fromAddress = trim($_POST['from_address'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($title === '') {
                $errors[] = 'Title is required';
            }

            if ($receivedDate === '') {
                $receivedDate = date('Y-m-d');
            }

            if (empty($errors)) {
                yojaka_dak_ensure_storage($deptSlug);

                $now = date('c');
                $id = yojaka_dak_generate_id($deptSlug);
                $loginIdentity = $user['login_identity'] ?? ($user['username'] ?? '');

                $workflows = yojaka_workflows_list_for_module($deptSlug, 'dak');
                $selectedWorkflow = $workflows[0] ?? null;
                $initialStepId = null;
                $workflowHistory = [];
                $workflowStatus = 'draft';

                if ($selectedWorkflow) {
                    $firstStep = ($selectedWorkflow['steps'] ?? [])[0] ?? null;
                    $initialStepId = $firstStep['id'] ?? null;
                    $workflowStatus = 'in_progress';

                    $workflowHistory[] = [
                        'timestamp' => $now,
                        'action' => 'created',
                        'from_step' => null,
                        'to_step' => $initialStepId,
                        'from_user' => null,
                        'to_user' => $loginIdentity,
                        'actor_user' => $loginIdentity,
                        'comment' => 'Created Dak',
                        'external_actor' => null,
                    ];
                }

                $record = [
                    'id' => $id,
                    'module' => 'dak',
                    'department_slug' => $deptSlug,
                    'title' => $title,
                    'reference_no' => $referenceNo,
                    'received_date' => $receivedDate,
                    'received_via' => $receivedVia,
                    'from_name' => $fromName,
                    'from_address' => $fromAddress,
                    'subject' => $subject,
                    'remarks' => $remarks,
                    'status' => $workflowStatus,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'owner_username' => $loginIdentity,
                    'assignee_username' => $loginIdentity,
                    'allowed_users' => [],
                    'allowed_roles' => [],
                    'workflow' => [
                        'workflow_id' => $selectedWorkflow['id'] ?? null,
                        'current_step' => $initialStepId,
                        'status' => $workflowStatus,
                        'history' => $workflowHistory,
                    ],
                ];

                yojaka_dak_save_record($deptSlug, $record);

                yojaka_audit_log_action(
                    $deptSlug,
                    'dak',
                    $record['id'],
                    'dak.create',
                    'Created Dak record',
                    [
                        'title' => $record['title'],
                        'reference_no' => $record['reference_no'],
                        'status' => $record['status'],
                    ]
                );

                header('Location: ' . yojaka_url('index.php?r=dak/view&id=' . urlencode($id) . '&message=created'));
                exit;
            }
        }

        $data = [
            'title' => 'Create Dak',
            'errors' => $errors,
            'values' => [
                'title' => $_POST['title'] ?? '',
                'reference_no' => $_POST['reference_no'] ?? '',
                'received_date' => $_POST['received_date'] ?? date('Y-m-d'),
                'received_via' => $_POST['received_via'] ?? 'post',
                'from_name' => $_POST['from_name'] ?? '',
                'from_address' => $_POST['from_address'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'remarks' => $_POST['remarks'] ?? '',
            ],
        ];

        return yojaka_render_view('dak/create', $data, 'main');
    }

    public function view()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $deptSlug = $user['department_slug'] ?? '';
        $id = $_GET['id'] ?? '';

        $record = $id ? yojaka_dak_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'dak/view'], 'main');
        }

        if (!yojaka_acl_can_view_record($user, $record)) {
            http_response_code(403);
            return yojaka_render_view('errors/403', ['message' => 'Access denied'], 'main');
        }

        $canEdit = yojaka_acl_can_edit_record($user, $record);

        $workflowTemplate = null;
        $currentStep = null;
        $allowedNextSteps = [];
        $allowedPrevSteps = [];
        $roleAllowedAtStep = false;

        $workflowId = $record['workflow']['workflow_id'] ?? null;
        if ($workflowId) {
            $workflowTemplate = yojaka_workflows_find($deptSlug, $workflowId);
            if ($workflowTemplate) {
                $currentStepId = $record['workflow']['current_step'] ?? '';
                $currentStep = $currentStepId ? yojaka_workflow_get_step($workflowTemplate, $currentStepId) : null;
                if ($currentStep) {
                    $allowedNextSteps = yojaka_workflow_allowed_next_steps($workflowTemplate, $currentStepId);
                    $allowedPrevSteps = yojaka_workflow_allowed_prev_steps($workflowTemplate, $currentStepId);
                    $roleId = $user['role_id'] ?? null;
                    if ($roleId && in_array($roleId, $currentStep['allowed_roles'] ?? [], true)) {
                        $roleAllowedAtStep = true;
                    }
                }
            }
        }

        $isClosed = ($record['status'] ?? '') === 'closed';
        $canForward = $roleAllowedAtStep && !$isClosed && yojaka_has_permission($user, 'dak.forward') && !empty($allowedNextSteps);
        $canReturn = $roleAllowedAtStep && !$isClosed && yojaka_has_permission($user, 'dak.forward') && !empty($allowedPrevSteps);
        $canClose = $roleAllowedAtStep && !$isClosed && yojaka_has_permission($user, 'dak.close') && (!empty($currentStep['is_terminal']) || empty($currentStep));

        $auditEntries = yojaka_audit_load_for_record($deptSlug, 'dak', $record['id'], 200);
        $timeline = yojaka_record_timeline($record, $auditEntries);

        $data = [
            'title' => 'View Dak',
            'record' => $record,
            'canEdit' => $canEdit,
            'message' => $_GET['message'] ?? '',
            'workflowTemplate' => $workflowTemplate,
            'currentStep' => $currentStep,
            'canForward' => $canForward,
            'canReturn' => $canReturn,
            'canClose' => $canClose,
            'allowedNextSteps' => $allowedNextSteps,
            'allowedPrevSteps' => $allowedPrevSteps,
            'timeline' => $timeline,
            'auditEntries' => $auditEntries,
        ];

        return yojaka_render_view('dak/view', $data, 'main');
    }

    public function edit()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $deptSlug = $user['department_slug'] ?? '';
        $id = $_GET['id'] ?? '';
        $errors = [];

        $record = $id ? yojaka_dak_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'dak/edit'], 'main');
        }

        if (!yojaka_acl_can_edit_record($user, $record)) {
            http_response_code(403);
            return yojaka_render_view('errors/403', ['message' => 'Edit not allowed'], 'main');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $referenceNo = trim($_POST['reference_no'] ?? '');
            $receivedDate = trim($_POST['received_date'] ?? '');
            $receivedVia = trim($_POST['received_via'] ?? '');
            $fromName = trim($_POST['from_name'] ?? '');
            $fromAddress = trim($_POST['from_address'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($title === '') {
                $errors[] = 'Title is required';
            }

            if ($receivedDate === '') {
                $receivedDate = date('Y-m-d');
            }

            if (empty($errors)) {
                $record['title'] = $title;
                $record['reference_no'] = $referenceNo;
                $record['received_date'] = $receivedDate;
                $record['received_via'] = $receivedVia;
                $record['from_name'] = $fromName;
                $record['from_address'] = $fromAddress;
                $record['subject'] = $subject;
                $record['remarks'] = $remarks;
                $record['updated_at'] = date('c');

                yojaka_dak_save_record($deptSlug, $record);

                yojaka_audit_log_action(
                    $deptSlug,
                    'dak',
                    $record['id'],
                    'dak.edit',
                    'Edited Dak record',
                    [
                        'title' => $record['title'],
                        'reference_no' => $record['reference_no'],
                        'status' => $record['status'],
                    ]
                );

                header('Location: ' . yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id']) . '&message=updated'));
                exit;
            }
        }

        $data = [
            'title' => 'Edit Dak',
            'record' => $record,
            'errors' => $errors,
            'values' => [
                'title' => $_POST['title'] ?? ($record['title'] ?? ''),
                'reference_no' => $_POST['reference_no'] ?? ($record['reference_no'] ?? ''),
                'received_date' => $_POST['received_date'] ?? ($record['received_date'] ?? date('Y-m-d')),
                'received_via' => $_POST['received_via'] ?? ($record['received_via'] ?? 'post'),
                'from_name' => $_POST['from_name'] ?? ($record['from_name'] ?? ''),
                'from_address' => $_POST['from_address'] ?? ($record['from_address'] ?? ''),
                'subject' => $_POST['subject'] ?? ($record['subject'] ?? ''),
                'remarks' => $_POST['remarks'] ?? ($record['remarks'] ?? ''),
            ],
        ];

        return yojaka_render_view('dak/edit', $data, 'main');
    }

    public function forward()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $id = $_GET['id'] ?? '';
        $context = $this->loadWorkflowActionContext($user, $id, 'dak.forward');

        $deptSlug = $context['deptSlug'];
        $record = $context['record'];
        $allowedSteps = yojaka_workflow_allowed_next_steps($context['workflow'], $context['currentStepId']);
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $toStep = trim($_POST['to_step'] ?? '');
            $comment = trim($_POST['comment'] ?? '');
            $toUser = trim($_POST['to_user'] ?? '');

            $validStepIds = array_map(function ($step) {
                return $step['id'] ?? '';
            }, $allowedSteps);

            if ($toStep === '' || !in_array($toStep, $validStepIds, true)) {
                $errors[] = 'Please select a valid next step.';
            }

            $assigneeUsername = $user['login_identity'] ?? ($user['username'] ?? '');
            if ($toUser !== '') {
                $assigneeUsername = $toUser;
                $targetUser = yojaka_dept_users_find_by_login_identity($toUser);
                if (!$targetUser || ($targetUser['department_slug'] ?? '') !== $deptSlug) {
                    $errors[] = 'Assignee not found in this department.';
                }
            }

            if (empty($errors)) {
                $timestamp = date('c');
                $record['workflow']['current_step'] = $toStep;
                $record['workflow']['status'] = 'in_progress';
                $record['assignee_username'] = $assigneeUsername;
                $record['updated_at'] = $timestamp;
                $actor = $user['login_identity'] ?? ($user['username'] ?? '');

                $record['workflow']['history'][] = [
                    'timestamp' => $timestamp,
                    'action' => 'forwarded',
                    'from_step' => $context['currentStepId'],
                    'to_step' => $toStep,
                    'from_user' => $actor,
                    'to_user' => $assigneeUsername,
                    'actor_user' => $actor,
                    'comment' => $comment,
                    'external_actor' => null,
                ];

                yojaka_dak_save_record($deptSlug, $record);

                yojaka_audit_log_action(
                    $deptSlug,
                    'dak',
                    $record['id'],
                    'dak.forward',
                    'Forwarded Dak',
                    [
                        'workflow_step_from' => $context['currentStepId'],
                        'workflow_step_to' => $toStep,
                        'from_user' => $actor,
                        'to_user' => $assigneeUsername,
                        'comment' => $comment,
                    ]
                );

                header('Location: ' . yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id']) . '&message=forwarded'));
                exit;
            }
        }

        $data = [
            'title' => 'Forward Dak',
            'record' => $record,
            'workflow' => $context['workflow'],
            'currentStep' => $context['currentStep'],
            'allowedSteps' => $allowedSteps,
            'errors' => $errors,
        ];

        return yojaka_render_view('dak/forward', $data, 'main');
    }

    public function return_action()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $id = $_GET['id'] ?? '';
        $context = $this->loadWorkflowActionContext($user, $id, 'dak.forward');

        $deptSlug = $context['deptSlug'];
        $record = $context['record'];
        $allowedSteps = yojaka_workflow_allowed_prev_steps($context['workflow'], $context['currentStepId']);
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $toStep = trim($_POST['to_step'] ?? '');
            $comment = trim($_POST['comment'] ?? '');
            $toUser = trim($_POST['to_user'] ?? '');

            $validStepIds = array_map(function ($step) {
                return $step['id'] ?? '';
            }, $allowedSteps);

            if ($toStep === '' || !in_array($toStep, $validStepIds, true)) {
                $errors[] = 'Please select a valid previous step.';
            }

            $assigneeUsername = $user['login_identity'] ?? ($user['username'] ?? '');
            if ($toUser !== '') {
                $assigneeUsername = $toUser;
                $targetUser = yojaka_dept_users_find_by_login_identity($toUser);
                if (!$targetUser || ($targetUser['department_slug'] ?? '') !== $deptSlug) {
                    $errors[] = 'Assignee not found in this department.';
                }
            }

            if (empty($errors)) {
                $timestamp = date('c');
                $record['workflow']['current_step'] = $toStep;
                $record['workflow']['status'] = 'in_progress';
                $record['assignee_username'] = $assigneeUsername;
                $record['updated_at'] = $timestamp;
                $actor = $user['login_identity'] ?? ($user['username'] ?? '');

                $record['workflow']['history'][] = [
                    'timestamp' => $timestamp,
                    'action' => 'returned',
                    'from_step' => $context['currentStepId'],
                    'to_step' => $toStep,
                    'from_user' => $actor,
                    'to_user' => $assigneeUsername,
                    'actor_user' => $actor,
                    'comment' => $comment,
                    'external_actor' => null,
                ];

                yojaka_dak_save_record($deptSlug, $record);

                yojaka_audit_log_action(
                    $deptSlug,
                    'dak',
                    $record['id'],
                    'dak.return',
                    'Returned Dak',
                    [
                        'workflow_step_from' => $context['currentStepId'],
                        'workflow_step_to' => $toStep,
                        'from_user' => $actor,
                        'to_user' => $assigneeUsername,
                        'comment' => $comment,
                    ]
                );

                header('Location: ' . yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id']) . '&message=returned'));
                exit;
            }
        }

        $data = [
            'title' => 'Return Dak',
            'record' => $record,
            'workflow' => $context['workflow'],
            'currentStep' => $context['currentStep'],
            'allowedSteps' => $allowedSteps,
            'errors' => $errors,
        ];

        return yojaka_render_view('dak/return', $data, 'main');
    }

    public function close()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $id = $_GET['id'] ?? '';
        $context = $this->loadWorkflowActionContext($user, $id, 'dak.close');

        $currentStep = $context['currentStep'];
        if (empty($currentStep['is_terminal'])) {
            http_response_code(403);
            return yojaka_render_view('errors/403', ['message' => 'Closing is only allowed at terminal steps.'], 'main');
        }

        $deptSlug = $context['deptSlug'];
        $record = $context['record'];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');
            $timestamp = date('c');
            $actor = $user['login_identity'] ?? ($user['username'] ?? '');

            $record['status'] = 'closed';
            $record['workflow']['status'] = 'closed';
            $record['updated_at'] = $timestamp;

            $record['workflow']['history'][] = [
                'timestamp' => $timestamp,
                'action' => 'closed',
                'from_step' => $context['currentStepId'],
                'to_step' => $context['currentStepId'],
                'from_user' => $actor,
                'to_user' => $record['assignee_username'] ?? $actor,
                'actor_user' => $actor,
                'comment' => $comment,
                'external_actor' => null,
            ];

            yojaka_dak_save_record($deptSlug, $record);

            yojaka_audit_log_action(
                $deptSlug,
                'dak',
                $record['id'],
                'dak.close',
                'Closed Dak record',
                [
                    'workflow_step' => $context['currentStepId'],
                    'status' => $record['status'],
                    'comment' => $comment,
                ]
            );

            header('Location: ' . yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id']) . '&message=closed'));
            exit;
        }

        $data = [
            'title' => 'Close Dak',
            'record' => $record,
            'workflow' => $context['workflow'],
            'currentStep' => $currentStep,
            'errors' => $errors,
        ];

        return yojaka_render_view('dak/close', $data, 'main');
    }
}
