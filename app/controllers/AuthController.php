<?php
class AuthController
{
    public function login()
    {
        if (yojaka_is_logged_in()) {
            $this->redirectAfterLogin(yojaka_current_user());
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            if ($username !== '' && $password !== '') {
                $user = yojaka_users_find_by_username($username);
                if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash']) && ($user['status'] ?? '') === 'active') {
                    yojaka_auth_login($user);
                    $this->redirectAfterLogin($user);
                }

                // Department user identity flow.
                $identityParts = yojaka_parse_login_identity($username);
                if ($identityParts) {
                    $deptSlug = $identityParts['department_slug'];
                    $roleId = $identityParts['role_id'];
                    $deptUser = yojaka_dept_users_find_by_login_identity($username);
                    $role = yojaka_roles_find_by_role_id($deptSlug, $roleId);

                    if ($deptUser && $role && ($deptUser['status'] ?? '') === 'active' && in_array($roleId, $deptUser['role_ids'] ?? [], true)) {
                        if (isset($deptUser['password_hash']) && password_verify($password, $deptUser['password_hash'])) {
                            $sessionUser = [
                                'id' => $deptUser['id'] ?? null,
                                'username' => $deptUser['username_base'] ?? null,
                                'username_base' => $deptUser['username_base'] ?? null,
                                'user_type' => 'dept_user',
                                'department_slug' => $deptSlug,
                                'login_identity' => $username,
                                'role_id' => $roleId,
                            ];

                            yojaka_auth_login($sessionUser);
                            $this->redirectAfterLogin($sessionUser);
                        }
                    }
                }
            }

            $error = 'Invalid username or password';
        }

        $data = [
            'title' => 'Login',
            'error' => $error,
        ];

        return yojaka_render_view('auth/login', $data, 'main');
    }

    public function logout()
    {
        yojaka_auth_logout();
        header('Location: ' . yojaka_url('index.php?r=auth/login'));
        exit;
    }

    protected function redirectAfterLogin(array $user): void
    {
        if (($user['user_type'] ?? '') === 'superadmin') {
            header('Location: ' . yojaka_url('index.php?r=superadmin/dashboard'));
            exit;
        }

        if (($user['user_type'] ?? '') === 'dept_admin') {
            header('Location: ' . yojaka_url('index.php?r=deptadmin/dashboard'));
            exit;
        }

        if (($user['user_type'] ?? '') === 'dept_user') {
            header('Location: ' . yojaka_url('index.php?r=deptuser/dashboard'));
            exit;
        }

        header('Location: ' . yojaka_url('index.php'));
        exit;
    }
}
