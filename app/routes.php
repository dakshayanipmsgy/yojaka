<?php
// Simple routing map for pages handled by app.php

function resolve_route(string $page): array
{
    $routes = [
        'login' => [
            'title' => 'Login',
            'view' => __DIR__ . '/views/login_form.php',
            'requires_auth' => false,
            'layout' => false,
        ],
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
        'admin_housekeeping' => [
            'title' => 'Housekeeping & Retention',
            'view' => __DIR__ . '/views/admin_housekeeping.php',
            'permission' => 'manage_housekeeping',
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
        'admin_license' => [
            'title' => 'License & Trial Status',
            'view' => __DIR__ . '/views/admin_license.php',
            'permission' => 'manage_office_config',
        ],
        'admin_master_data' => [
            'title' => 'Master Data',
            'view' => __DIR__ . '/views/admin_master_data.php',
            'permission' => 'manage_office_config',
        ],
        'admin_hierarchy' => [
            'title' => 'Hierarchy & Posts',
            'view' => __DIR__ . '/views/admin_hierarchy.php',
            'permission' => 'manage_office_config',
        ],
        'admin_routes' => [
            'title' => 'File Routes',
            'view' => __DIR__ . '/views/admin_routes.php',
            'permission' => 'manage_office_config',
        ],
        'admin_mis' => [
            'title' => 'Reports & Analytics (MIS)',
            'view' => __DIR__ . '/views/admin_mis.php',
            'permission' => 'view_mis_reports',
        ],
        'admin_repository' => [
            'title' => 'Documents Repository',
            'view' => __DIR__ . '/views/admin_documents_repository.php',
            'permission' => 'view_all_records',
        ],
        'global_search' => [
            'title' => 'Global Search',
            'view' => __DIR__ . '/views/global_search.php',
        ],
        'my_tasks' => [
            'title' => 'My Tasks',
            'view' => __DIR__ . '/views/my_tasks.php',
        ],
        'notifications' => [
            'title' => 'Notifications',
            'view' => __DIR__ . '/views/notifications.php',
        ],
        'change_language' => [
            'title' => 'Change Language',
            'view' => __DIR__ . '/views/change_language.php',
        ],
        'change_password' => [
            'title' => 'Change Password',
            'view' => __DIR__ . '/views/change_password.php',
        ],
        'case_export' => [
            'title' => 'Case Bundle Export',
            'view' => __DIR__ . '/views/case_export.php',
            'permission' => 'admin_backup',
        ],
        'admin_roles' => [
            'title' => 'Role Management',
            'view' => __DIR__ . '/views/admin_roles.php',
            'permission' => 'manage_users',
        ],
        'superadmin_offices' => [
            'title' => 'Super Admin Offices',
            'view' => __DIR__ . '/views/superadmin_offices.php',
            'role' => 'superadmin',
        ],
        'superadmin_dashboard' => [
            'title' => 'Super Admin Dashboard',
            'view' => __DIR__ . '/views/superadmin_dashboard.php',
            'role' => 'superadmin',
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
