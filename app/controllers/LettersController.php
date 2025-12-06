<?php
class LettersController
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

    protected function requireLettersPermission(array $user, string $permission): void
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

    public function list()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireLettersPermission($user, 'letters.view');

        $deptSlug = $user['department_slug'] ?? '';
        yojaka_letters_ensure_storage($deptSlug);

        $records = yojaka_letters_list_records_for_user($deptSlug, $user);
        $templates = yojaka_templates_list_letters_for_department($deptSlug);
        $templateMap = [];
        foreach ($templates as $tpl) {
            $templateMap[$tpl['id']] = $tpl['name'] ?? $tpl['id'];
        }

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'template_id' => trim($_GET['template_id'] ?? ''),
            'created_from' => trim($_GET['created_from'] ?? ''),
            'created_to' => trim($_GET['created_to'] ?? ''),
        ];

        $records = array_values(array_filter($records, function (array $record) use ($filters) {
            $q = strtolower($filters['q']);
            if ($q !== '') {
                $haystacks = [
                    strtolower($record['fields']['subject'] ?? ''),
                    strtolower($record['fields']['to_name'] ?? ''),
                    strtolower($record['fields']['body'] ?? ''),
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

            if ($filters['template_id'] !== '' && ($record['template_id'] ?? '') !== $filters['template_id']) {
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

        $data = [
            'title' => 'Letters & Notices',
            'records' => $records,
            'templateMap' => $templateMap,
            'filters' => $filters,
            'statusOptions' => ['draft' => 'Draft', 'finalized' => 'Finalized'],
            'templates' => $templates,
        ];

        return yojaka_render_view('letters/list', $data, 'main');
    }

    public function create()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireLettersPermission($user, 'letters.create');

        $deptSlug = $user['department_slug'] ?? '';
        yojaka_letters_ensure_storage($deptSlug);

        $templates = yojaka_templates_list_letters_for_department($deptSlug);
        $templateId = trim($_GET['template_id'] ?? '');
        $selectedTemplate = $templateId ? yojaka_templates_find_letter_for_department($deptSlug, $templateId) : null;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $templateId = trim($_POST['template_id'] ?? '');
            $selectedTemplate = $templateId ? yojaka_templates_find_letter_for_department($deptSlug, $templateId) : null;

            if (!$selectedTemplate) {
                $errors[] = 'Invalid template selected.';
            }

            $fields = [];
            if ($selectedTemplate) {
                foreach ($selectedTemplate['placeholders'] ?? [] as $placeholder) {
                    $key = $placeholder['key'] ?? '';
                    if ($key === '') {
                        continue;
                    }
                    $fields[$key] = trim($_POST[$key] ?? '');
                }
            }

            if (($fields['letter_date'] ?? '') === '') {
                $fields['letter_date'] = date('Y-m-d');
            }

            if (($fields['subject'] ?? '') === '') {
                $errors[] = 'Subject is required.';
            }

            if (($fields['body'] ?? '') === '') {
                $errors[] = 'Body text is required.';
            }

            if (empty($errors) && $selectedTemplate) {
                $now = date('c');
                $id = yojaka_letters_generate_id($deptSlug);
                $loginIdentity = $user['login_identity'] ?? ($user['username'] ?? '');

                $record = [
                    'id' => $id,
                    'module' => 'letters',
                    'department_slug' => $deptSlug,
                    'template_id' => $selectedTemplate['id'],
                    'fields' => $fields,
                    'status' => 'draft',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'owner_username' => $loginIdentity,
                    'assignee_username' => $loginIdentity,
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

                yojaka_letters_save_record($deptSlug, $record);

                yojaka_audit_log_action($deptSlug, 'letters', $id, 'letters.create', 'Created letter', [
                    'template_id' => $selectedTemplate['id'],
                ]);

                header('Location: ' . yojaka_url('index.php?r=letters/view&id=' . urlencode($id)));
                exit;
            }
        }

        $data = [
            'title' => 'Create Letter',
            'templates' => $templates,
            'templateId' => $templateId,
            'template' => $selectedTemplate,
            'errors' => $errors,
        ];

        return yojaka_render_view('letters/create', $data, 'main');
    }

    public function view()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireLettersPermission($user, 'letters.view');

        $deptSlug = $user['department_slug'] ?? '';
        $id = trim($_GET['id'] ?? '');
        $record = $id ? yojaka_letters_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'letters/view'], 'main');
        }

        if (!yojaka_acl_can_view_record($user, $record)) {
            http_response_code(403);
            return yojaka_render_view('errors/403', ['message' => 'Access denied'], 'main');
        }

        if (!isset($record['attachments']) || !is_array($record['attachments'])) {
            $record['attachments'] = [];
        }

        $template = yojaka_templates_find_letter_for_department($deptSlug, $record['template_id'] ?? '');
        $rendered = $template ? yojaka_templates_render_html($template, $record['fields'] ?? []) : '';

        $data = [
            'title' => 'Letter Details',
            'record' => $record,
            'template' => $template,
            'renderedHtml' => $rendered,
            'canEdit' => $record['status'] === 'draft'
                && yojaka_acl_can_edit_record($user, $record)
                && ((($user['user_type'] ?? '') === 'dept_admin') || yojaka_has_permission($user, 'letters.edit')),
        ];

        return yojaka_render_view('letters/view', $data, 'main');
    }

    public function edit()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireLettersPermission($user, 'letters.edit');

        $deptSlug = $user['department_slug'] ?? '';
        $id = trim($_GET['id'] ?? '');
        $record = $id ? yojaka_letters_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'letters/edit'], 'main');
        }

        if (!yojaka_acl_can_edit_record($user, $record)) {
            http_response_code(403);
            return yojaka_render_view('errors/403', ['message' => 'Edit not allowed'], 'main');
        }

        if (($record['status'] ?? '') !== 'draft') {
            http_response_code(400);
            return yojaka_render_view('errors/500', ['message' => 'Only draft letters can be edited.'], 'main');
        }

        $template = yojaka_templates_find_letter_for_department($deptSlug, $record['template_id'] ?? '');
        if (!$template) {
            http_response_code(400);
            return yojaka_render_view('errors/500', ['message' => 'Template not available for this letter.'], 'main');
        }

        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = $record['fields'] ?? [];
            foreach ($template['placeholders'] ?? [] as $placeholder) {
                $key = $placeholder['key'] ?? '';
                if ($key === '') {
                    continue;
                }
                $fields[$key] = trim($_POST[$key] ?? '');
            }

            if (($fields['letter_date'] ?? '') === '') {
                $fields['letter_date'] = date('Y-m-d');
            }

            if (($fields['subject'] ?? '') === '') {
                $errors[] = 'Subject is required.';
            }

            if (($fields['body'] ?? '') === '') {
                $errors[] = 'Body text is required.';
            }

            if (empty($errors)) {
                $record['fields'] = $fields;
                $record['updated_at'] = date('c');

                yojaka_letters_save_record($deptSlug, $record);

                yojaka_audit_log_action($deptSlug, 'letters', $record['id'], 'letters.edit', 'Edited letter', [
                    'template_id' => $template['id'],
                ]);

                header('Location: ' . yojaka_url('index.php?r=letters/view&id=' . urlencode($record['id'])));
                exit;
            }
        }

        $data = [
            'title' => 'Edit Letter',
            'record' => $record,
            'template' => $template,
            'errors' => $errors,
        ];

        return yojaka_render_view('letters/edit', $data, 'main');
    }

    public function print()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireLettersPermission($user, 'letters.print');

        $deptSlug = $user['department_slug'] ?? '';
        $id = trim($_GET['id'] ?? '');
        $record = $id ? yojaka_letters_load_record($deptSlug, $id) : null;

        if (!$record) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'letters/print'], 'main');
        }

        if (!yojaka_acl_can_view_record($user, $record)) {
            http_response_code(403);
            return yojaka_render_view('errors/403', ['message' => 'Access denied'], 'main');
        }

        $template = yojaka_templates_find_letter_for_department($deptSlug, $record['template_id'] ?? '');
        $rendered = $template ? yojaka_templates_render_html($template, $record['fields'] ?? []) : '';

        $data = [
            'record' => $record,
            'template' => $template,
            'renderedHtml' => $rendered,
        ];

        return yojaka_render_view('letters/print', $data, '');
    }
}
