<?php
class DeptUsersController
{
    public function index()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.users.manage');

        $current = yojaka_current_user();
        $deptSlug = $current['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
            $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
            if ($userId !== '') {
                $users = yojaka_dept_users_load($deptSlug);
                foreach ($users as &$user) {
                    if (($user['id'] ?? '') === $userId) {
                        $user['status'] = ($user['status'] ?? 'active') === 'active' ? 'disabled' : 'active';
                        $user['updated_at'] = date('c');
                        break;
                    }
                }
                unset($user);
                yojaka_dept_users_save($deptSlug, $users);
                $_SESSION['deptadmin_success'] = 'User status updated successfully';
                header('Location: ' . yojaka_url('index.php?r=deptadmin/users'));
                exit;
            }
        }

        $message = $_SESSION['deptadmin_success'] ?? null;
        unset($_SESSION['deptadmin_success']);

        $users = $deptSlug ? yojaka_dept_users_load($deptSlug) : [];
        $roles = $deptSlug ? yojaka_roles_load_for_department($deptSlug) : [];

        $data = [
            'title' => 'Department Users',
            'department' => $department,
            'users' => $users,
            'roles' => $roles,
            'message' => $message,
        ];

        return yojaka_render_view('deptadmin/users_list', $data, 'main');
    }

    public function create()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.users.manage');

        $current = yojaka_current_user();
        $deptSlug = $current['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $roles = $deptSlug ? yojaka_roles_load_for_department($deptSlug) : [];

        $errors = [];
        $form = [
            'username_base' => '',
            'display_name' => '',
            'role_ids' => [],
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form['username_base'] = isset($_POST['username_base']) ? trim($_POST['username_base']) : '';
            $form['display_name'] = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
            $form['role_ids'] = isset($_POST['role_ids']) && is_array($_POST['role_ids']) ? array_values($_POST['role_ids']) : [];

            if ($form['username_base'] === '' || !preg_match('/^[a-z0-9]+$/', $form['username_base'])) {
                $errors[] = 'Username base is required and must be lowercase letters or numbers.';
            }

            if ($form['display_name'] === '') {
                $errors[] = 'Display name is required.';
            }

            $validRoleIds = array_map(function ($role) {
                return $role['role_id'] ?? '';
            }, $roles);

            foreach ($form['role_ids'] as $roleId) {
                if (!in_array($roleId, $validRoleIds, true)) {
                    $errors[] = 'Invalid role selected: ' . yojaka_escape($roleId);
                    break;
                }
            }

            if (yojaka_dept_users_find_by_base($deptSlug, $form['username_base'])) {
                $errors[] = 'A user with that username base already exists in this department.';
            }

            if (empty($form['role_ids'])) {
                $errors[] = 'At least one role must be selected.';
            }

            if (empty($errors)) {
                $now = date('c');
                $passwordPlain = 'User@123';
                $loginIdentities = [];
                foreach ($form['role_ids'] as $roleId) {
                    $loginIdentities[] = $form['username_base'] . '.' . $roleId;
                }

                $userData = [
                    'username_base' => $form['username_base'],
                    'display_name' => $form['display_name'],
                    'role_ids' => $form['role_ids'],
                    'login_identities' => $loginIdentities,
                    'password_hash' => password_hash($passwordPlain, PASSWORD_DEFAULT),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $saved = yojaka_dept_users_add($deptSlug, $userData);
                if ($saved) {
                    $_SESSION['deptadmin_success'] = 'User created. Initial password: ' . $passwordPlain;
                    header('Location: ' . yojaka_url('index.php?r=deptadmin/users'));
                    exit;
                }

                $errors[] = 'Unable to save user. Please try again.';
            }
        }

        $data = [
            'title' => 'Create Department User',
            'department' => $department,
            'roles' => $roles,
            'form' => $form,
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/users_create', $data, 'main');
    }

    public function edit()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.users.manage');

        $current = yojaka_current_user();
        $deptSlug = $current['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;
        $roles = $deptSlug ? yojaka_roles_load_for_department($deptSlug) : [];

        $userId = isset($_GET['id']) ? trim($_GET['id']) : '';
        $user = $userId !== '' ? yojaka_dept_users_find_by_id($deptSlug, $userId) : null;

        if (!$user) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'deptadmin/users/edit'], 'main');
        }

        $errors = [];
        $form = [
            'display_name' => $user['display_name'] ?? '',
            'role_ids' => $user['role_ids'] ?? [],
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form['display_name'] = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
            $form['role_ids'] = isset($_POST['role_ids']) && is_array($_POST['role_ids']) ? array_values($_POST['role_ids']) : [];

            if ($form['display_name'] === '') {
                $errors[] = 'Display name is required.';
            }

            if (empty($form['role_ids'])) {
                $errors[] = 'At least one role must be selected.';
            }

            $validRoleIds = array_map(function ($role) {
                return $role['role_id'] ?? '';
            }, $roles);
            foreach ($form['role_ids'] as $roleId) {
                if (!in_array($roleId, $validRoleIds, true)) {
                    $errors[] = 'Invalid role selected: ' . yojaka_escape($roleId);
                    break;
                }
            }

            if (empty($errors)) {
                $users = yojaka_dept_users_load($deptSlug);
                foreach ($users as &$existing) {
                    if (($existing['id'] ?? '') === $userId) {
                        $existing['display_name'] = $form['display_name'];
                        $existing['role_ids'] = $form['role_ids'];
                        $existing['login_identities'] = [];
                        foreach ($form['role_ids'] as $roleId) {
                            $existing['login_identities'][] = ($existing['username_base'] ?? '') . '.' . $roleId;
                        }
                        $existing['updated_at'] = date('c');
                        break;
                    }
                }
                unset($existing);

                if (yojaka_dept_users_save($deptSlug, $users)) {
                    $_SESSION['deptadmin_success'] = 'User updated successfully';
                    header('Location: ' . yojaka_url('index.php?r=deptadmin/users'));
                    exit;
                }

                $errors[] = 'Unable to save user. Please try again.';
            }
        }

        $data = [
            'title' => 'Edit Department User',
            'department' => $department,
            'roles' => $roles,
            'user' => $user,
            'form' => $form,
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/users_edit', $data, 'main');
    }

    public function password()
    {
        yojaka_require_dept_admin();
        yojaka_require_permission('dept.users.manage');

        $current = yojaka_current_user();
        $deptSlug = $current['department_slug'] ?? '';
        $department = $deptSlug ? yojaka_find_department_by_slug($deptSlug) : null;

        $userId = isset($_GET['id']) ? trim($_GET['id']) : '';
        $user = $userId !== '' ? yojaka_dept_users_find_by_id($deptSlug, $userId) : null;
        if (!$user) {
            http_response_code(404);
            return yojaka_render_view('errors/404', ['route' => 'deptadmin/users/password'], 'main');
        }

        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

            if ($newPassword === '' || $confirmPassword === '') {
                $errors[] = 'Password and confirmation are required.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $users = yojaka_dept_users_load($deptSlug);
                foreach ($users as &$existing) {
                    if (($existing['id'] ?? '') === $userId) {
                        $existing['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        $existing['updated_at'] = date('c');
                        break;
                    }
                }
                unset($existing);

                if (yojaka_dept_users_save($deptSlug, $users)) {
                    $_SESSION['deptadmin_success'] = 'Password updated successfully.';
                    header('Location: ' . yojaka_url('index.php?r=deptadmin/users'));
                    exit;
                }

                $errors[] = 'Unable to update password. Please try again.';
            }
        }

        $data = [
            'title' => 'Change User Password',
            'department' => $department,
            'user' => $user,
            'errors' => $errors,
        ];

        return yojaka_render_view('deptadmin/users_password', $data, 'main');
    }
}
