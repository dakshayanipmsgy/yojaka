<?php
// Basic configuration for Yojaka v1.0

return [
    // Installer flag - set to true after running the setup wizard
    'installed' => false,

    // Base URL relative to server root; adjust if deployed under a subdirectory
    'base_url' => '/yojaka/public',

    // Paths
    'root_path' => realpath(__DIR__ . '/..'),
    'data_path' => realpath(__DIR__ . '/../data'),
    'logs_path' => realpath(__DIR__ . '/../logs'),
    'usage_log_file' => 'usage.log',
    'templates_path' => __DIR__ . '/../data/templates',
    'letters_templates_file' => 'letters.json',
    'document_templates_file' => 'documents.json',
    'generated_letters_log' => 'generated_letters.log',

    'documents_data_path' => __DIR__ . '/../data/documents',
    'documents_meeting_minutes_file' => 'meeting_minutes.json',
    'documents_work_orders_file' => 'work_orders.json',
    'documents_guc_file' => 'guc.json',

    // Office / instance configuration
    'office_data_path' => __DIR__ . '/../data/office',
    'office_config_file' => 'office.json',

    // Contractor bills configuration
    'bills_data_path' => __DIR__ . '/../data/bills',
    'bills_file' => 'bills.json',

    // Departments configuration
    'departments_data_path' => __DIR__ . '/../data/departments',
    'departments_file' => 'departments.json',

    // Dak module configuration
    'dak_data_path' => __DIR__ . '/../data/dak',
    'dak_entries_file' => 'dak_entries.json',
    'dak_overdue_days' => 7,

    // RTI module configuration
    'rti_data_path' => __DIR__ . '/../data/rti',
    'rti_cases_file' => 'rti_cases.json',
    'rti_reply_days' => 30,

    // Inspection module configuration
    'inspection_data_path' => __DIR__ . '/../data/inspection',
    'inspection_templates_file' => 'templates.json',
    'inspection_reports_file' => 'reports.json',

    // Pagination defaults
    'pagination_per_page' => 10,
    'logs_pagination_per_page' => 50,

    // Backup configuration (data-only backups for administrators)
    'backup_path' => realpath(__DIR__ . '/..') . '/backup',
    'backup_include_data' => true,
    'backup_include_config' => true,

    // Roles and permissions
    'roles_permissions' => [
        'admin' => [
            'manage_users',
            'manage_departments',
            'manage_templates',
            'view_logs',
            'manage_rti',
            'manage_dak',
            'manage_inspection',
            'create_documents',
            'view_all_records',
            'view_reports_basic',
            'admin_backup',
            'manage_office_config',
            'manage_bills',
        ],
        'officer' => [
            'create_documents',
            'manage_rti',
            'manage_dak',
            'manage_inspection',
            'view_reports_basic',
            'manage_bills',
        ],
        'clerk' => [
            'create_documents',
            'manage_dak',
            'view_reports_basic',
            'manage_bills',
        ],
        'viewer' => [
            'view_reports_basic',
        ],
    ],

    // Default admin credentials used only when seeding users.json on first run
    'default_admin' => [
        'username' => 'admin',
        'password' => 'admin123', // Change immediately after first login
        'full_name' => 'System Administrator'
    ],

    // Security settings
    'display_errors' => false,
];
