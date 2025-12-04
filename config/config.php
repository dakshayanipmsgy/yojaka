<?php
// Basic configuration for Yojaka v0.2

return [
    // Base URL relative to server root; adjust if deployed under a subdirectory
    'base_url' => '/yojaka/public',

    // Paths
    'root_path' => realpath(__DIR__ . '/..'),
    'data_path' => realpath(__DIR__ . '/../data'),
    'logs_path' => realpath(__DIR__ . '/../logs'),
    'usage_log_file' => 'usage.log',
    'templates_path' => __DIR__ . '/../data/templates',
    'letters_templates_file' => 'letters.json',
    'generated_letters_log' => 'generated_letters.log',

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

    // Default admin credentials used only when seeding users.json on first run
    'default_admin' => [
        'username' => 'admin',
        'password' => 'admin123', // Change immediately after first login
        'full_name' => 'System Administrator'
    ],

    // Security settings
    'display_errors' => false,
];
