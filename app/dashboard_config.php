<?php

function dashboard_roles_config_path(): string
{
    return YOJAKA_DATA_PATH . '/dashboard_roles.json';
}

function load_dashboard_roles_config(): array
{
    $path = dashboard_roles_config_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function get_role_dashboard_config(string $role): array
{
    $config = load_dashboard_roles_config();
    return $config[$role] ?? [];
}

