<?php
class DeptAdminController
{
    public function dashboard()
    {
        yojaka_require_dept_admin();

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $roles = $deptSlug ? yojaka_roles_load_for_department($deptSlug) : [];

        $message = $_SESSION['deptadmin_success'] ?? null;
        unset($_SESSION['deptadmin_success']);

        $data = [
            'title' => 'Department Admin Dashboard',
            'department' => $department,
            'roles' => $roles,
            'message' => $message,
        ];

        return yojaka_render_view('deptadmin/dashboard', $data, 'main');
    }

    public function roles_create()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.roles.manage');

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $catalog = yojaka_permissions_catalog();
        $allPermissions = yojaka_permissions_all();

        $errors = [];
        $form = [
            'local_key' => '',
            'label' => '',
            'permissions' => [],
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form['local_key'] = isset($_POST['local_key']) ? trim($_POST['local_key']) : '';
            $form['label'] = isset($_POST['label']) ? trim($_POST['label']) : '';
            $form['permissions'] = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_values($_POST['permissions']) : [];

            if ($form['local_key'] === '' || !preg_match('/^[a-z0-9_]+$/', $form['local_key'])) {
                $errors[] = 'Local key is required and must be lowercase letters, numbers, or underscores.';
            }

            if ($form['label'] === '') {
                $errors[] = 'Label is required.';
            }

            // Validate permissions against global catalog.
            foreach ($form['permissions'] as $permission) {
                if (!in_array($permission, $allPermissions, true)) {
                    $errors[] = 'Invalid permission selected: ' . yojaka_escape($permission);
                    break;
                }
            }

            $existingRoles = yojaka_roles_load_for_department($deptSlug);
            foreach ($existingRoles as $role) {
                if (($role['local_key'] ?? '') === $form['local_key']) {
                    $errors[] = 'A role with that local key already exists in this department.';
                    break;
                }
            }

            if (empty($errors)) {
                $roleData = [
                    'local_key' => $form['local_key'],
                    'label' => $form['label'],
                    'permissions' => $form['permissions'],
                ];

                $result = yojaka_roles_add($deptSlug, $roleData);
                if ($result) {
                    yojaka_audit_log_action(
                        $deptSlug,
                        'roles',
                        $result['role_id'] ?? null,
                        'roles.create',
                        'Created department role',
                        [
                            'role_id' => $result['role_id'] ?? null,
                            'label' => $result['label'] ?? null,
                            'permissions' => $result['permissions'] ?? [],
                        ]
                    );

                    $_SESSION['deptadmin_success'] = 'Role created successfully';
                    header('Location: ' . yojaka_url('index.php?r=deptadmin/dashboard'));
                    exit;
                } else {
                    $errors[] = 'Unable to save role. Please try again.';
                }
            }
        }

        $data = [
            'title' => 'Create Role',
            'department' => $department,
            'catalog' => $catalog,
            'form' => $form,
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/roles_create', $data, 'main');
    }

    public function workflows()
    {
        yojaka_require_dept_admin();

        $user = yojaka_current_user();
        $deptSlug = $user['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $workflows = $deptSlug ? yojaka_workflows_load_for_department($deptSlug) : [];

        $data = [
            'title' => 'Department Workflows',
            'department' => $department,
            'workflows' => $workflows,
        ];

        return yojaka_render_view('deptadmin/workflows', $data, 'main');
    }

    public function audit()
    {
        yojaka_require_dept_admin();

        $current = yojaka_current_user();
        $deptSlug = $current['department_slug'] ?? '';

        $entries = $deptSlug !== '' ? yojaka_audit_load_recent($deptSlug, 200) : [];

        $data = [
            'title' => 'Department Audit Log',
            'entries' => $entries,
        ];

        return yojaka_render_view('deptadmin/audit', $data, 'main');
    }

    public function change_password()
    {
        yojaka_require_dept_admin();

        $user = yojaka_current_user();
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!isset($user['password_hash']) || !password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }

            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation do not match.';
            }

            if (empty($errors)) {
                $users = yojaka_load_users();

                foreach ($users as &$storedUser) {
                    if (($storedUser['id'] ?? null) === ($user['id'] ?? null)) {
                        $storedUser['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        $storedUser['must_change_password'] = false;
                        break;
                    }
                }
                unset($storedUser);

                yojaka_save_users($users);

                $updated = yojaka_users_find_by_username($user['username'] ?? '');
                if ($updated) {
                    yojaka_auth_login($updated);
                }

                $_SESSION['deptadmin_success'] = 'Password updated successfully.';
                header('Location: ' . yojaka_url('index.php?r=deptadmin/dashboard'));
                exit;
            }
        }

        $data = [
            'title' => 'Change Password',
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/change_password', $data, 'main');
    }
}
