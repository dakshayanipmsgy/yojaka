<?php
// Simple routing map for pages handled by app.php

function resolve_route(string $page): array
{
    $routes = [
        'dashboard' => [
            'title' => 'Dashboard',
            'view' => __DIR__ . '/views/dashboard.php',
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
