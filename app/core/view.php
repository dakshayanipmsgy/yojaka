<?php
// View rendering helper functions.

function yojaka_render_view(string $viewName, array $data = [], string $layout = 'main')
{
    $viewPath = yojaka_config('paths.app_path') . '/views/' . $viewName . '.php';
    $layoutPath = yojaka_config('paths.app_path') . '/layouts/' . $layout . '.php';

    if (!file_exists($viewPath)) {
        return '<p>View not found: ' . htmlspecialchars($viewName, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    extract($data, EXTR_SKIP);

    ob_start();
    include $viewPath;
    $content = ob_get_clean();

    if (!file_exists($layoutPath)) {
        return $content;
    }

    ob_start();
    include $layoutPath;

    return ob_get_clean();
}
