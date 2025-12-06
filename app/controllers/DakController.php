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

    public function list()
    {
        $user = $this->requireDeptUserOrAdmin();
        $this->requireDakPermission($user);

        $deptSlug = $user['department_slug'] ?? '';
        yojaka_dak_ensure_storage($deptSlug);

        $records = yojaka_dak_list_records_for_user($deptSlug, $user);

        $data = [
            'title' => 'Dak – My Files',
            'records' => $records,
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
                    'status' => 'draft',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'owner_username' => $loginIdentity,
                    'assignee_username' => $loginIdentity,
                    'allowed_users' => [],
                    'allowed_roles' => [],
                    'workflow' => [
                        'workflow_id' => null,
                        'current_step' => null,
                        'status' => null,
                        'history' => [],
                    ],
                ];

                yojaka_dak_save_record($deptSlug, $record);

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

        $data = [
            'title' => 'View Dak',
            'record' => $record,
            'canEdit' => $canEdit,
            'message' => $_GET['message'] ?? '',
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
}
