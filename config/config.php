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
    'offices_data_path' => realpath(__DIR__ . '/../data/offices'),
    'audit_data_path' => realpath(__DIR__ . '/../data/audit'),
    'index_data_path' => realpath(__DIR__ . '/../data/index'),
    'templates_path' => __DIR__ . '/../data/templates',
    'letters_templates_file' => 'letters.json',
    'document_templates_file' => 'documents.json',
    'generated_letters_log' => 'generated_letters.log',

    'documents_data_path' => __DIR__ . '/../data/documents',
    'documents_meeting_minutes_file' => 'meeting_minutes.json',
    'documents_work_orders_file' => 'work_orders.json',
    'documents_guc_file' => 'guc.json',

    // Attachments storage
    'attachments_data_path' => __DIR__ . '/../data/attachments',
    'attachments_meta_file' => 'meta.json',
    'attachments_allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
    'attachments_max_size_bytes' => 5 * 1024 * 1024, // 5 MB

    // Office / instance configuration
    'office_data_path' => __DIR__ . '/../data/office', // legacy path for migration
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

    // SLA configuration
    'sla' => [
        'rti_reply_days' => 30,
        'rti_reminder_before_days' => 5,
        'dak_process_days' => 7,
        'dak_reminder_before_days' => 2,
        'bill_approval_days' => 10,
        'bill_reminder_before_days' => 3,
    ],

    // Notification hooks
    'email_notifications_enabled' => false,
    'email_from_address' => 'no-reply@example.com',

    // Pagination defaults
    'pagination_per_page' => 10,
    'logs_pagination_per_page' => 50,

    // Backup configuration (data-only backups for administrators)
    'backup_path' => realpath(__DIR__ . '/..') . '/backup',
    'backup_include_data' => true,
    'backup_include_config' => true,

    'license_default_trial_days' => 30,

    'retention' => [
        'rti' => [
            'active_days' => 365,
            'delete_days' => null,
        ],
        'dak' => [
            'active_days' => 365,
            'delete_days' => null,
        ],
        'inspection' => [
            'active_days' => 730,
            'delete_days' => null,
        ],
        'documents' => [
            'active_days' => 730,
            'delete_days' => null,
        ],
        'bills' => [
            'active_days' => 730,
            'delete_days' => null,
        ],
        'attachments' => [
            'delete_archived_after_days' => null,
        ],
    ],

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
            'view_mis_reports',
            'admin_backup',
            'manage_office_config',
            'manage_bills',
            'manage_documents_repository',
            'manage_housekeeping',
        ],
        'officer' => [
            'create_documents',
            'manage_rti',
            'manage_dak',
            'manage_inspection',
            'view_reports_basic',
            'manage_bills',
            'manage_documents_repository',
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
