<?php
// UI customization helper

function default_menu_config(): array
{
    return [
        'main' => [
            ['page' => 'dashboard', 'visible' => true, 'label_key' => 'nav.dashboard'],
            ['page' => 'my_tasks', 'visible' => true, 'label_key' => 'nav.my_tasks'],
            ['page' => 'letters', 'visible' => true, 'label_key' => 'nav.letters'],
            ['page' => 'global_search', 'visible' => true, 'label_key' => 'nav.global_search'],
            ['page' => 'rti', 'visible' => true, 'label_key' => 'nav.rti'],
            ['page' => 'dak', 'visible' => true, 'label_key' => 'nav.dak'],
            ['page' => 'inspection', 'visible' => true, 'label_key' => 'nav.inspection'],
            ['page' => 'meeting_minutes', 'visible' => true, 'label_key' => 'nav.meeting_minutes'],
            ['page' => 'work_orders', 'visible' => true, 'label_key' => 'nav.work_orders'],
            ['page' => 'guc', 'visible' => true, 'label_key' => 'nav.guc'],
            ['page' => 'bills', 'visible' => true, 'label_key' => 'nav.bills'],
        ],
        'admin' => [
            ['page' => 'admin_users', 'visible' => true, 'label_key' => 'nav.admin_users'],
            ['page' => 'admin_departments', 'visible' => true, 'label_key' => 'nav.admin_departments'],
            ['page' => 'admin_office', 'visible' => true, 'label_key' => 'nav.admin_office'],
            ['page' => 'admin_license', 'visible' => true, 'label_key' => 'nav.license'],
            ['page' => 'admin_letter_templates', 'visible' => true, 'label_key' => 'nav.templates_letters'],
            ['page' => 'admin_documents', 'visible' => true, 'label_key' => 'nav.documents_templates'],
            ['page' => 'admin_rti', 'visible' => true, 'label_key' => 'nav.admin_rti'],
            ['page' => 'admin_dak', 'visible' => true, 'label_key' => 'nav.admin_dak'],
            ['page' => 'admin_inspection', 'visible' => true, 'label_key' => 'nav.admin_inspection'],
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
