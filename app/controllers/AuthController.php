<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function login()
    {
        $error = null;
        $notice = $_SESSION['default_superadmin_notice'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $submittedToken = $_POST['csrf_token'] ?? '';
            $sessionToken = $_SESSION['csrf_token'] ?? '';

            if (!$submittedToken || !$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if ($username === '' || $password === '') {
                    $error = 'Please provide both username and password.';
                } else {
                    if (Auth::attemptLogin($username, $password)) {
                        unset($_SESSION['csrf_token']);
                        unset($_SESSION['default_superadmin_notice']);

                        $redirect = $_SESSION['intended_route'] ?? 'superadmin/index';
                        unset($_SESSION['intended_route']);

                        $this->redirect('?route=' . $redirect);
                    } else {
                        $error = 'Invalid username or password.';
                    }
                }
            }
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('auth/login', [
            'error' => $error,
            'notice' => $notice,
            'csrfToken' => $_SESSION['csrf_token'],
        ]);
    }

    public function logout()
    {
        Auth::logout();
        $this->redirect('?route=auth/login');
    }
}
