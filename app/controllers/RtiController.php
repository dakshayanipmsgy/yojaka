<?php
class RtiController
{
    protected function requireDeptContext(): array
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

    protected function requirePermission(array $user, string $permission): void
    {
        if (($user['user_type'] ?? '') === 'dept_admin') {
            return;
        }

        if (!yojaka_has_permission($user, $permission)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Permission denied'], 'main');
            exit;
        }
    }

    protected function loadRecordContext(array $user, string $id, string $permission): array
    {
        $deptSlug = $user['department_slug'] ?? '';
        $record = $id ? yojaka_rti_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            echo yojaka_render_view('errors/404', ['route' => 'rti'], 'main');
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

        return ['deptSlug' => $deptSlug, 'record' => $record];
    }

    protected function enforceWorkflowRole(array $user, array $record, array $workflow): void
    {
        $currentStepId = $record['workflow']['current_step'] ?? '';
        $step = $currentStepId ? yojaka_workflow_get_step($workflow, $currentStepId) : null;
        $roleId = $user['role_id'] ?? '';
        if (!$step || !$roleId || !in_array($roleId, $step['allowed_roles'] ?? [], true)) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'Workflow action not allowed for your role.'], 'main');
            exit;
        }
    }

    public function list()
    {
        $user = $this->requireDeptContext();

        if (!yojaka_has_permission($user, 'rti.create') && !yojaka_has_permission($user, 'rti.edit') && !yojaka_has_permission($user, 'rti.forward')) {
            http_response_code(403);
            echo yojaka_render_view('errors/403', ['message' => 'RTI permissions required'], 'main');
            exit;
        }

        $deptSlug = $user['department_slug'] ?? '';
        yojaka_rti_ensure_storage($deptSlug);

        $records = yojaka_rti_list_records_for_user($deptSlug, $user);
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'received_from' => trim($_GET['received_from'] ?? ''),
            'received_to' => trim($_GET['received_to'] ?? ''),
            'due_from' => trim($_GET['due_from'] ?? ''),
            'due_to' => trim($_GET['due_to'] ?? ''),
        ];

        $records = array_values(array_filter($records, function (array $record) use ($filters) {
            $q = strtolower($filters['q']);
            if ($q !== '') {
                $haystacks = [
                    strtolower($record['basic']['applicant_name'] ?? ''),
                    strtolower($record['basic']['subject'] ?? ''),
                    strtolower($record['basic']['rti_number'] ?? ''),
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

            $received = $record['basic']['received_date'] ?? '';
            if ($filters['received_from'] !== '' && ($received === '' || strcmp($received, $filters['received_from']) < 0)) {
                return false;
            }
            if ($filters['received_to'] !== '' && ($received === '' || strcmp($received, $filters['received_to']) > 0)) {
                return false;
            }

            $due = $record['dates']['due_date'] ?? '';
            if ($filters['due_from'] !== '' && ($due === '' || strcmp($due, $filters['due_from']) < 0)) {
                return false;
            }
            if ($filters['due_to'] !== '' && ($due === '' || strcmp($due, $filters['due_to']) > 0)) {
                return false;
            }

            return true;
        }));

        echo yojaka_render_view('rti/list', [
            'title' => 'RTI Cases',
            'records' => $records,
            'filters' => $filters,
        ], 'main');
    }

    public function create()
    {
        $user = $this->requireDeptContext();
        $this->requirePermission($user, 'rti.create');

        $deptSlug = $user['department_slug'] ?? '';
        yojaka_rti_ensure_storage($deptSlug);

        $errors = [];
        $basic = [
            'received_date' => date('Y-m-d'),
            'rti_number' => '',
            'applicant_name' => '',
            'applicant_address' => '',
            'contact_details' => '',
            'subject' => '',
            'information_sought' => '',
            'mode_received' => 'post',
            'fee_details' => '',
            'remarks' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($basic as $key => $val) {
                $basic[$key] = trim($_POST[$key] ?? '');
            }

            if ($basic['received_date'] === '') {
                $errors[] = 'Received date is required.';
            }
            if ($basic['applicant_name'] === '') {
                $errors[] = 'Applicant name is required.';
            }
            if ($basic['subject'] === '') {
                $errors[] = 'Subject is required.';
            }
            if ($basic['information_sought'] === '') {
                $errors[] = 'Information sought is required.';
            }

            if (empty($errors)) {
                $received = DateTimeImmutable::createFromFormat('Y-m-d', $basic['received_date']) ?: new DateTimeImmutable();
                // For now, default due date is 30 days from received date.
                $dueDate = $received->modify('+30 days')->format('Y-m-d');

                $now = date('c');
                $id = yojaka_rti_generate_id($deptSlug);
                $loginIdentity = $user['login_identity'] ?? ($user['username'] ?? '');

                $record = [
                    'id' => $id,
                    'module' => 'rti',
                    'department_slug' => $deptSlug,
                    'basic' => $basic,
                    'dates' => [
                        'due_date' => $dueDate,
                        'reply_sent_date' => null,
                        'closing_date' => null,
                    ],
                    'status' => 'in_progress',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'owner_username' => $loginIdentity,
                    'assignee_username' => $loginIdentity,
                    'allowed_users' => [],
                    'allowed_roles' => [],
                    'workflow' => [
                        'workflow_id' => 'rti_default',
                        'current_step' => 'rticlerk',
                        'status' => 'in_progress',
                        'history' => [
                            [
                                'action' => 'created',
                                'from_step' => null,
                                'to_step' => 'rticlerk',
                                'actor_user' => $loginIdentity,
                                'timestamp' => $now,
                                'comment' => 'RTI case registered',
                            ],
                        ],
                    ],
                    'attachments' => [],
                    'reply_letter_id' => null,
                ];

                yojaka_rti_save_record($deptSlug, $record);

                yojaka_audit_log_action(
                    $deptSlug,
                    'rti',
                    $id,
                    'rti.create',
                    'Created RTI case',
                    ['subject' => $basic['subject']]
                );

                header('Location: ' . yojaka_url('index.php?r=rti/view&id=' . urlencode($id) . '&message=created'));
                exit;
            }
        }

        echo yojaka_render_view('rti/create', [
            'title' => 'Register RTI',
            'basic' => $basic,
            'errors' => $errors,
        ], 'main');
    }

    public function view()
    {
        $user = $this->requireDeptContext();
        $id = trim($_GET['id'] ?? '');
        $deptSlug = $user['department_slug'] ?? '';
        $record = $id ? yojaka_rti_load_record($deptSlug, $id) : null;

        if (!$record || !yojaka_acl_can_view_record($user, $record)) {
            http_response_code(404);
            echo yojaka_render_view('errors/404', ['route' => 'rti/view'], 'main');
            exit;
        }

        $workflowTemplate = yojaka_workflows_find($deptSlug, $record['workflow']['workflow_id'] ?? '');
        $currentStep = $workflowTemplate ? yojaka_workflow_get_step($workflowTemplate, $record['workflow']['current_step'] ?? '') : null;
        $allowedNextSteps = $workflowTemplate ? yojaka_workflow_allowed_next_steps($workflowTemplate, $record['workflow']['current_step'] ?? '') : [];
        $allowedPrevSteps = $workflowTemplate ? yojaka_workflow_allowed_prev_steps($workflowTemplate, $record['workflow']['current_step'] ?? '') : [];

        $auditEntries = yojaka_audit_load_for_record($deptSlug, 'rti', $record['id'] ?? '');
        $timeline = yojaka_record_timeline($record, $auditEntries);

        $roleId = $user['role_id'] ?? '';
        $stepRoles = $currentStep['allowed_roles'] ?? [];
        $hasStepRole = $roleId && in_array($roleId, $stepRoles, true);

        $canEdit = yojaka_has_permission($user, 'rti.edit') && yojaka_acl_can_edit_record($user, $record) && ($record['status'] ?? '') !== 'closed';
        $canForward = yojaka_has_permission($user, 'rti.forward') && $hasStepRole && ($record['status'] ?? '') !== 'closed';
        $canReturn = yojaka_has_permission($user, 'rti.forward') && $hasStepRole && ($record['status'] ?? '') !== 'closed';
        $canClose = yojaka_has_permission($user, 'rti.close') && yojaka_acl_can_edit_record($user, $record) && ($record['status'] ?? '') !== 'closed';
        $canReply = yojaka_has_permission($user, 'rti.reply') && yojaka_acl_can_edit_record($user, $record);

        echo yojaka_render_view('rti/view', [
            'title' => 'RTI Details',
            'record' => $record,
            'workflowTemplate' => $workflowTemplate,
            'currentStep' => $currentStep,
            'allowedNextSteps' => $allowedNextSteps,
            'allowedPrevSteps' => $allowedPrevSteps,
            'timeline' => $timeline,
            'message' => $_GET['message'] ?? null,
            'canEdit' => $canEdit,
            'canForward' => $canForward,
            'canReturn' => $canReturn,
            'canClose' => $canClose,
            'canReply' => $canReply,
        ], 'main');
    }

    public function edit()
    {
        $user = $this->requireDeptContext();
        $id = trim($_GET['id'] ?? '');
        $context = $this->loadRecordContext($user, $id, 'rti.edit');
        $record = $context['record'];
        $deptSlug = $context['deptSlug'];

        if (($record['status'] ?? '') === 'closed') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'RTI case is closed.'], 'main');
            exit;
        }

        $basic = $record['basic'] ?? [];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($basic as $key => $val) {
                $basic[$key] = trim($_POST[$key] ?? '');
            }

            if ($basic['received_date'] === '') {
                $errors[] = 'Received date is required.';
            }
            if ($basic['applicant_name'] === '') {
                $errors[] = 'Applicant name is required.';
            }
            if ($basic['subject'] === '') {
                $errors[] = 'Subject is required.';
            }
            if ($basic['information_sought'] === '') {
                $errors[] = 'Information sought is required.';
            }

            if (empty($errors)) {
                $received = DateTimeImmutable::createFromFormat('Y-m-d', $basic['received_date']) ?: new DateTimeImmutable();
                $dueDate = $received->modify('+30 days')->format('Y-m-d');

                $record['basic'] = $basic;
                $record['dates']['due_date'] = $dueDate;
                $record['updated_at'] = date('c');

                yojaka_rti_save_record($deptSlug, $record);

                yojaka_audit_log_action($deptSlug, 'rti', $record['id'], 'rti.edit', 'Updated RTI case', [
                    'subject' => $basic['subject'],
                ]);

                header('Location: ' . yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id']) . '&message=updated'));
                exit;
            }
        }

        echo yojaka_render_view('rti/edit', [
            'title' => 'Edit RTI',
            'record' => $record,
            'basic' => $basic,
            'errors' => $errors,
        ], 'main');
    }

    public function forward()
    {
        $user = $this->requireDeptContext();
        $id = trim($_GET['id'] ?? '');
        $context = $this->loadRecordContext($user, $id, 'rti.forward');
        $record = $context['record'];
        $deptSlug = $context['deptSlug'];

        if (($record['status'] ?? '') === 'closed') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'RTI case is closed.'], 'main');
            exit;
        }

        $workflow = yojaka_workflows_find($deptSlug, $record['workflow']['workflow_id'] ?? '');
        if (!$workflow) {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Workflow not found.'], 'main');
            exit;
        }

        $this->enforceWorkflowRole($user, $record, $workflow);

        $allowedSteps = yojaka_workflow_allowed_next_steps($workflow, $record['workflow']['current_step'] ?? '');
        $errors = [];
        $selection = [
            'next_step' => '',
            'assignee_username' => $record['assignee_username'] ?? '',
            'comment' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $selection['next_step'] = trim($_POST['next_step'] ?? '');
            $selection['assignee_username'] = trim($_POST['assignee_username'] ?? '');
            $selection['comment'] = trim($_POST['comment'] ?? '');

            $allowedIds = array_map(function ($s) {
                return $s['id'] ?? '';
            }, $allowedSteps);

            if (!in_array($selection['next_step'], $allowedIds, true)) {
                $errors[] = 'Selected step is not allowed.';
            }

            if (empty($errors)) {
                $now = date('c');
                $historyEntry = [
                    'action' => 'forwarded',
                    'from_step' => $record['workflow']['current_step'] ?? '',
                    'to_step' => $selection['next_step'],
                    'actor_user' => $user['login_identity'] ?? ($user['username'] ?? ''),
                    'to_user' => $selection['assignee_username'],
                    'comment' => $selection['comment'],
                    'timestamp' => $now,
                ];

                $record['workflow']['current_step'] = $selection['next_step'];
                $record['workflow']['history'][] = $historyEntry;
                $record['assignee_username'] = $selection['assignee_username'] ?: ($record['assignee_username'] ?? '');
                $record['status'] = $record['status'] === 'draft' ? 'in_progress' : ($record['status'] ?? 'in_progress');
                $record['updated_at'] = $now;

                yojaka_rti_save_record($deptSlug, $record);

                yojaka_audit_log_action($deptSlug, 'rti', $record['id'], 'rti.forward', 'Forwarded RTI case', [
                    'from_step' => $historyEntry['from_step'],
                    'to_step' => $historyEntry['to_step'],
                    'to_user' => $historyEntry['to_user'],
                    'comment' => $historyEntry['comment'],
                ]);

                header('Location: ' . yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id']) . '&message=forwarded'));
                exit;
            }
        }

        echo yojaka_render_view('rti/forward', [
            'title' => 'Forward RTI',
            'record' => $record,
            'allowedSteps' => $allowedSteps,
            'selection' => $selection,
            'errors' => $errors,
            'action' => 'forward',
        ], 'main');
    }

    public function return_action()
    {
        $user = $this->requireDeptContext();
        $id = trim($_GET['id'] ?? '');
        $context = $this->loadRecordContext($user, $id, 'rti.forward');
        $record = $context['record'];
        $deptSlug = $context['deptSlug'];

        if (($record['status'] ?? '') === 'closed') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'RTI case is closed.'], 'main');
            exit;
        }

        $workflow = yojaka_workflows_find($deptSlug, $record['workflow']['workflow_id'] ?? '');
        if (!$workflow) {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'Workflow not found.'], 'main');
            exit;
        }

        $this->enforceWorkflowRole($user, $record, $workflow);

        $allowedSteps = yojaka_workflow_allowed_prev_steps($workflow, $record['workflow']['current_step'] ?? '');
        $errors = [];
        $selection = [
            'prev_step' => '',
            'assignee_username' => $record['assignee_username'] ?? '',
            'comment' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $selection['prev_step'] = trim($_POST['prev_step'] ?? '');
            $selection['assignee_username'] = trim($_POST['assignee_username'] ?? '');
            $selection['comment'] = trim($_POST['comment'] ?? '');

            $allowedIds = array_map(function ($s) {
                return $s['id'] ?? '';
            }, $allowedSteps);

            if (!in_array($selection['prev_step'], $allowedIds, true)) {
                $errors[] = 'Selected step is not allowed.';
            }

            if (empty($errors)) {
                $now = date('c');
                $historyEntry = [
                    'action' => 'returned',
                    'from_step' => $record['workflow']['current_step'] ?? '',
                    'to_step' => $selection['prev_step'],
                    'actor_user' => $user['login_identity'] ?? ($user['username'] ?? ''),
                    'to_user' => $selection['assignee_username'],
                    'comment' => $selection['comment'],
                    'timestamp' => $now,
                ];

                $record['workflow']['current_step'] = $selection['prev_step'];
                $record['workflow']['history'][] = $historyEntry;
                $record['assignee_username'] = $selection['assignee_username'] ?: ($record['assignee_username'] ?? '');
                $record['updated_at'] = $now;

                yojaka_rti_save_record($deptSlug, $record);

                yojaka_audit_log_action($deptSlug, 'rti', $record['id'], 'rti.return', 'Returned RTI case', [
                    'from_step' => $historyEntry['from_step'],
                    'to_step' => $historyEntry['to_step'],
                    'to_user' => $historyEntry['to_user'],
                    'comment' => $historyEntry['comment'],
                ]);

                header('Location: ' . yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id']) . '&message=returned'));
                exit;
            }
        }

        echo yojaka_render_view('rti/return', [
            'title' => 'Return RTI',
            'record' => $record,
            'allowedSteps' => $allowedSteps,
            'selection' => $selection,
            'errors' => $errors,
            'action' => 'return',
        ], 'main');
    }

    public function close()
    {
        $user = $this->requireDeptContext();
        $id = trim($_GET['id'] ?? '');
        $context = $this->loadRecordContext($user, $id, 'rti.close');
        $record = $context['record'];
        $deptSlug = $context['deptSlug'];

        if (($record['status'] ?? '') === 'closed') {
            http_response_code(400);
            echo yojaka_render_view('errors/500', ['message' => 'RTI case is already closed.'], 'main');
            exit;
        }

        $errors = [];
        $comment = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');

            $now = date('c');
            $record['status'] = 'closed';
            $record['dates']['closing_date'] = date('Y-m-d');
            $record['workflow']['status'] = 'closed';
            $record['workflow']['history'][] = [
                'action' => 'closed',
                'from_step' => $record['workflow']['current_step'] ?? '',
                'to_step' => $record['workflow']['current_step'] ?? '',
                'actor_user' => $user['login_identity'] ?? ($user['username'] ?? ''),
                'comment' => $comment,
                'timestamp' => $now,
            ];
            $record['updated_at'] = $now;

            yojaka_rti_save_record($deptSlug, $record);

            yojaka_audit_log_action($deptSlug, 'rti', $record['id'], 'rti.close', 'Closed RTI case', [
                'comment' => $comment,
            ]);

            header('Location: ' . yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id']) . '&message=closed'));
            exit;
        }

        echo yojaka_render_view('rti/close', [
            'title' => 'Close RTI',
            'record' => $record,
            'comment' => $comment,
            'errors' => $errors,
        ], 'main');
    }

    public function reply()
    {
        $user = $this->requireDeptContext();
        $id = trim($_GET['id'] ?? '');
        $context = $this->loadRecordContext($user, $id, 'rti.reply');
        $record = $context['record'];
        $deptSlug = $context['deptSlug'];

        if (($record['reply_letter_id'] ?? null)) {
            header('Location: ' . yojaka_url('index.php?r=letters/view&id=' . urlencode($record['reply_letter_id'])));
            exit;
        }

        $templateId = 'rti_reply_en';
        $template = yojaka_templates_find_letter_for_department($deptSlug, $templateId);

        $fields = [
            'letter_date' => date('Y-m-d'),
            'to_name' => $record['basic']['applicant_name'] ?? '',
            'to_address' => $record['basic']['applicant_address'] ?? '',
            'subject' => 'Reply to RTI application dated ' . ($record['basic']['received_date'] ?? ''),
            'body' => "Dear Applicant,\n\nPlease find the response to your RTI request below.\n\nRegards,\nPIO",
        ];

        // Ensure required placeholders exist if template is present.
        if ($template) {
            foreach ($template['placeholders'] ?? [] as $placeholder) {
                $key = $placeholder['key'] ?? '';
                if ($key !== '' && !isset($fields[$key])) {
                    $fields[$key] = '';
                }
            }
        }

        $now = date('c');
        $letterId = yojaka_letters_generate_id($deptSlug);
        $owner = $user['login_identity'] ?? ($user['username'] ?? '');

        $letterRecord = [
            'id' => $letterId,
            'module' => 'letters',
            'department_slug' => $deptSlug,
            'template_id' => $templateId,
            'fields' => $fields,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
            'owner_username' => $owner,
            'assignee_username' => $owner,
            'allowed_users' => [],
            'allowed_roles' => [],
            'attachments' => [],
            'workflow' => [
                'workflow_id' => null,
                'current_step' => null,
                'status' => null,
                'history' => [],
            ],
        ];

        yojaka_letters_save_record($deptSlug, $letterRecord);

        $record['reply_letter_id'] = $letterId;
        $record['updated_at'] = $now;
        yojaka_rti_save_record($deptSlug, $record);

        yojaka_audit_log_action($deptSlug, 'rti', $record['id'], 'rti.reply', 'Created reply letter', [
            'letter_id' => $letterId,
        ]);

        header('Location: ' . yojaka_url('index.php?r=letters/edit&id=' . urlencode($letterId)));
        exit;
    }
}
