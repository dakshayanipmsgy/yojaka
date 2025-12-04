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
            'permission' => 'manage_users',
        ],
        'admin_logs' => [
            'title' => 'Usage Logs',
            'view' => __DIR__ . '/views/admin_logs.php',
            'permission' => 'view_logs',
        ],
        'letters' => [
            'title' => 'Letters & Notices',
            'view' => __DIR__ . '/views/letters.php',
        ],
        'meeting_minutes' => [
            'title' => 'Meeting Minutes',
            'view' => __DIR__ . '/views/meeting_minutes.php',
        ],
        'work_orders' => [
            'title' => 'Work Orders',
            'view' => __DIR__ . '/views/work_orders.php',
        ],
        'guc' => [
            'title' => 'Grant Utilization Certificates',
            'view' => __DIR__ . '/views/guc.php',
        ],
        'bills' => [
            'title' => 'Contractor Bills',
            'view' => __DIR__ . '/views/bills.php',
        ],
        'rti' => [
            'title' => 'RTI Cases',
            'view' => __DIR__ . '/views/rti.php',
        ],
        'admin_letter_templates' => [
            'title' => 'Letter Templates',
            'view' => __DIR__ . '/views/admin_letter_templates.php',
            'permission' => 'manage_templates',
        ],
        'admin_documents' => [
            'title' => 'Document Templates',
            'view' => __DIR__ . '/views/admin_documents.php',
            'permission' => 'manage_templates',
        ],
        'admin_rti' => [
            'title' => 'RTI Management',
            'view' => __DIR__ . '/views/admin_rti.php',
            'permission' => 'manage_rti',
        ],
        'dak' => [
            'title' => 'Dak & File Movement',
            'view' => __DIR__ . '/views/dak.php',
        ],
        'admin_dak' => [
            'title' => 'Dak Management',
            'view' => __DIR__ . '/views/admin_dak.php',
            'permission' => 'manage_dak',
        ],
        'inspection' => [
            'title' => 'Inspection Reports',
            'view' => __DIR__ . '/views/inspection.php',
        ],
        'admin_inspection' => [
            'title' => 'Inspection Management',
            'view' => __DIR__ . '/views/admin_inspection.php',
            'permission' => 'manage_inspection',
        ],
        'admin_backup' => [
            'title' => 'Backup & Export',
            'view' => __DIR__ . '/views/admin_backup.php',
            'permission' => 'admin_backup',
        ],
        'admin_departments' => [
            'title' => 'Department Profiles',
            'view' => __DIR__ . '/views/admin_departments.php',
            'permission' => 'manage_departments',
        ],
        'admin_office' => [
            'title' => 'Office Settings',
            'view' => __DIR__ . '/views/admin_office.php',
            'permission' => 'manage_office_config',
        ],
        'admin_mis' => [
            'title' => 'Reports & Analytics (MIS)',
            'view' => __DIR__ . '/views/admin_mis.php',
            'permission' => 'view_mis_reports',
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
