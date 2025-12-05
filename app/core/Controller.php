<?php
namespace App\Core;

class Controller
{
    protected $layout = __DIR__ . '/../views/layouts/base.php';

    protected function getCurrentUser()
    {
        return Auth::currentUser();
    }

    protected function requireLogin($role = null)
    {
        if (!Auth::isLoggedIn()) {
            $_SESSION['intended_route'] = $_GET['route'] ?? '';
            $this->redirect('?route=auth/login');
        }

        if ($role !== null) {
            $user = $this->getCurrentUser();
            if (!$user || ($user['role'] ?? null) !== $role) {
                http_response_code(403);
                echo '403 Forbidden';
                exit;
            }
        }
    }

    protected function render($view, array $data = [])
    {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            echo 'View not found: ' . htmlspecialchars($view);
            return;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        if (file_exists($this->layout)) {
            include $this->layout;
        } else {
            echo $content;
        }
    }

    protected function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}
