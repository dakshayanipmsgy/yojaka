<?php
class AuthController
{
    public function login()
    {
        if (yojaka_is_logged_in()) {
            if (yojaka_is_superadmin()) {
                header('Location: ' . yojaka_url('index.php?r=superadmin/dashboard'));
                exit;
            }

            header('Location: ' . yojaka_url('index.php'));
            exit;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            if ($username !== '' && $password !== '') {
                $user = yojaka_find_user_by_username($username);
                if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash']) && ($user['status'] ?? '') === 'active') {
                    yojaka_auth_login($user);
                    header('Location: ' . yojaka_url('index.php?r=superadmin/dashboard'));
                    exit;
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
}
