<?php

function permissions_catalog_path(): string
{
    return __DIR__ . '/../data/org/permissions_catalog.json';
}

function load_permissions_catalog(): array
{
    $path = permissions_catalog_path();
    if (!file_exists($path)) {
        return ['permissions' => []];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['permissions']) || !is_array($data['permissions'])) {
        return ['permissions' => []];
    }

    return $data;
}

function get_all_permission_keys(): array
{
    $catalog = load_permissions_catalog();
    return array_keys($catalog['permissions']);
}

