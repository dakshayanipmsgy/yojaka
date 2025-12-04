<?php
// UI customization helper

function default_main_menu_items(): array
{
    return [
        ['page' => 'dashboard', 'label_key' => 'nav.dashboard', 'permission' => null],
        ['page' => 'my_tasks', 'label_key' => 'nav.my_tasks', 'permission' => null],
        ['page' => 'letters', 'label_key' => 'nav.letters_notices', 'permission' => 'view_letters'],
        ['page' => 'global_search', 'label_key' => 'nav.global_search', 'permission' => 'view_global_search'],
        ['page' => 'rti', 'label_key' => 'nav.rti_cases', 'permission' => 'view_rti'],
        ['page' => 'dak', 'label_key' => 'nav.dak_file_movement', 'permission' => 'view_dak'],
        ['page' => 'inspection', 'label_key' => 'nav.inspection_reports', 'permission' => 'view_inspection'],
        ['page' => 'meeting_minutes', 'label_key' => 'nav.meeting_minutes', 'permission' => 'view_meeting_minutes'],
        ['page' => 'work_orders', 'label_key' => 'nav.work_orders', 'permission' => 'view_work_orders'],
        ['page' => 'guc', 'label_key' => 'nav.guc', 'permission' => 'view_guc'],
        ['page' => 'bills', 'label_key' => 'nav.contractor_bills', 'permission' => 'view_bills'],
    ];
}

function default_menu_config(): array
{
    return [
        'main' => array_map(function ($item) {
            $item['visible'] = $item['visible'] ?? true;
            return $item;
        }, default_main_menu_items()),
        'admin' => [
            ['page' => 'admin_users', 'visible' => true, 'label_key' => 'nav.admin_users'],
            ['page' => 'admin_departments', 'visible' => true, 'label_key' => 'nav.admin_departments'],
            ['page' => 'admin_office', 'visible' => true, 'label_key' => 'nav.admin_office'],
            ['page' => 'admin_master_data', 'visible' => true, 'label_key' => 'nav.admin_master_data'],
            ['page' => 'admin_license', 'visible' => true, 'label_key' => 'nav.license'],
            ['page' => 'admin_letter_templates', 'visible' => true, 'label_key' => 'nav.templates_letters'],
            ['page' => 'admin_documents', 'visible' => true, 'label_key' => 'nav.documents_templates'],
            ['page' => 'admin_roles', 'visible' => true, 'label_key' => 'nav.admin_roles'],
            ['page' => 'admin_rti', 'visible' => true, 'label_key' => 'nav.admin_rti'],
            ['page' => 'admin_dak', 'visible' => true, 'label_key' => 'nav.admin_dak'],
            ['page' => 'admin_inspection', 'visible' => true, 'label_key' => 'nav.admin_inspection'],
            ['page' => 'admin_routes', 'visible' => true, 'label_key' => 'nav.admin_routes'],
            ['page' => 'admin_ai', 'visible' => true, 'label_key' => 'nav.admin_ai'],
            ['page' => 'admin_replies', 'visible' => true, 'label_key' => 'nav.admin_replies'],
            ['page' => 'admin_repository', 'visible' => true, 'label_key' => 'nav.repository'],
            ['page' => 'admin_logs', 'visible' => true, 'label_key' => 'nav.logs'],
            ['page' => 'admin_housekeeping', 'visible' => true, 'label_key' => 'nav.housekeeping'],
            ['page' => 'admin_mis', 'visible' => true, 'label_key' => 'nav.mis'],
            ['page' => 'admin_backup', 'visible' => true, 'label_key' => 'nav.housekeeping'],
        ],
    ];
}

function default_dashboard_widgets(): array
{
    return [
        ['id' => 'rti_summary', 'visible' => true],
        ['id' => 'dak_summary', 'visible' => true],
        ['id' => 'inspection_summary', 'visible' => true],
        ['id' => 'documents_summary', 'visible' => true],
        ['id' => 'bills_summary', 'visible' => true],
        ['id' => 'tasks_overview', 'visible' => true],
        ['id' => 'notifications_overview', 'visible' => true],
    ];
}

function get_office_menu_config(): array
{
    $office = get_current_office_config();
    $ui = $office['ui']['menus'] ?? [];
    $defaults = default_menu_config();
    if (!is_array($ui) || empty($ui)) {
        return $defaults;
    }

    return array_replace_recursive($defaults, $ui);
}

function get_office_dashboard_widgets(): array
{
    $office = get_current_office_config();
    $widgets = $office['ui']['dashboard_widgets'] ?? null;
    if (!is_array($widgets) || empty($widgets)) {
        return default_dashboard_widgets();
    }
    return $widgets;
}

function is_widget_visible(string $widgetId): bool
{
    foreach (get_office_dashboard_widgets() as $widget) {
        if (($widget['id'] ?? '') === $widgetId) {
            return !empty($widget['visible']);
        }
    }
    return true;
}
