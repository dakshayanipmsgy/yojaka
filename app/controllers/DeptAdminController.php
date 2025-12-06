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
}
