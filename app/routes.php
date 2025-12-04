<?php
// Simple routing map for pages handled by app.php

function resolve_route(string $page): array
{
    $routes = [
        'dashboard' => [
            'title' => 'Dashboard',
            'view' => __DIR__ . '/views/dashboard.php',
        ],
        'admin_users' => [
            'title' => 'User List',
            'view' => __DIR__ . '/views/admin_users.php',
            'role' => 'admin',
        ],
        'admin_logs' => [
            'title' => 'Usage Logs',
            'view' => __DIR__ . '/views/admin_logs.php',
            'role' => 'admin',
        ],
        'letters' => [
            'title' => 'Letters & Notices',
            'view' => __DIR__ . '/views/letters.php',
        ],
        'rti' => [
            'title' => 'RTI Cases',
            'view' => __DIR__ . '/views/rti.php',
        ],
        'admin_letter_templates' => [
            'title' => 'Letter Templates',
            'view' => __DIR__ . '/views/admin_letter_templates.php',
            'role' => 'admin',
        ],
        'admin_rti' => [
            'title' => 'RTI Management',
            'view' => __DIR__ . '/views/admin_rti.php',
            'role' => 'admin',
        ],
        'dak' => [
            'title' => 'Dak & File Movement',
            'view' => __DIR__ . '/views/dak.php',
        ],
        'admin_dak' => [
            'title' => 'Dak Management',
            'view' => __DIR__ . '/views/admin_dak.php',
            'role' => 'admin',
        ],
    ];

    if (isset($routes[$page])) {
        return $routes[$page];
    }

    return [
        'title' => 'Page Not Found',
        'view' => null,
    ];
}
