<?php
namespace App\Core;

class Controller
{
    protected $layout = __DIR__ . '/../views/layouts/base.php';

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
