<?php
namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index()
    {
        $config = $GLOBALS['config'] ?? [];
        $dataPath = $config['paths']['data'] ?? __DIR__ . '/../data';

        $diagnostics = [
            'environment' => $config['environment'] ?? 'development',
            'php_version' => PHP_VERSION,
            'data_writable' => is_writable($dataPath),
            'base_url' => $config['base_url'] ?? '',
        ];

        $this->render('home/index', [
            'appName' => 'Yojaka',
            'tagline' => 'Government Work, Files & Documents – Digitally Organized',
            'diagnostics' => $diagnostics,
            'notice' => $_SESSION['default_superadmin_notice'] ?? null,
        ]);
    }
}
