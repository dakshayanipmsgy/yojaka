<?php

function staff_data_path(string $officeId = ''): string
{
    $base = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR);
    if ($officeId !== '') {
        return $base . '/' . trim($officeId, '/ ') . '/staff.json';
    }
    return $base . '/staff.json';
}

function load_staff(string $officeId): array
{
    $paths = [staff_data_path($officeId), staff_data_path('')];
    foreach ($paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (is_array($json)) {
            return array_map(static function ($row) {
                return standardize_staff_record(is_array($row) ? $row : []);
            }, $json);
        }
    }
    return [];
}

function save_staff(string $officeId, array $staffList): bool
{
    $path = staff_data_path($officeId);
    bootstrap_ensure_directory(dirname($path));
    $payload = array_values(array_map('standardize_staff_record', $staffList));
    return false !== @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function find_staff_by_id(string $officeId, string $staffId): ?array
{
    foreach (load_staff($officeId) as $staff) {
        if (isset($staff['staff_id']) && (string) $staff['staff_id'] === (string) $staffId) {
            return $staff;
        }
    }
    return null;
}

function standardize_staff_record(array $record): array
{
    $now = gmdate('c');
    $record['staff_id'] = $record['staff_id'] ?? ($record['id'] ?? null);
    $record['full_name'] = $record['full_name'] ?? ($record['name'] ?? '');
    $record['designation'] = $record['designation'] ?? ($record['title'] ?? '');
    $record['department_id'] = $record['department_id'] ?? ($record['department'] ?? null);
    $record['phone'] = $record['phone'] ?? null;
    $record['email'] = $record['email'] ?? null;
    $record['active'] = array_key_exists('active', $record) ? (bool) $record['active'] : true;
    $record['created_at'] = $record['created_at'] ?? $now;
    $record['updated_at'] = $record['updated_at'] ?? $record['created_at'];
    return $record;
}
