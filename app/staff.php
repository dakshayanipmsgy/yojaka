<?php

function staff_data_path(?string $officeId = null): string
{
    $base = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR);
    if ($officeId !== null && $officeId !== '') {
        return $base . '/' . trim($officeId, '/ ') . '/staff.json';
    }

    // Legacy shared staff file used by older master_data implementation.
    return $base . '/master/staff.json';
}

function load_staff(?string $officeId = null): array
{
    $base = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR);
    $paths = [];

    if ($officeId !== null && $officeId !== '') {
        $paths[] = staff_data_path($officeId);
    }

    // Default (legacy) path and flat legacy fallback.
    $paths[] = staff_data_path(null);
    $paths[] = $base . '/staff.json';

    foreach (array_unique($paths) as $path) {
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

function save_staff(?string $officeId, array $staffList): bool
{
    $path = ($officeId !== null && $officeId !== '') ? staff_data_path($officeId) : staff_data_path(null);
    bootstrap_ensure_directory(dirname($path));
    $payload = array_values(array_map('standardize_staff_record', $staffList));
    $saved = false !== @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // Also persist a legacy flat copy if it already exists to maintain backward compatibility.
    $legacyPath = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR) . '/staff.json';
    if ($saved && file_exists($legacyPath) && $path !== $legacyPath) {
        bootstrap_ensure_directory(dirname($legacyPath));
        @file_put_contents($legacyPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    return $saved;
}

function find_staff_by_id(?string $officeId, string $staffId): ?array
{
    foreach (load_staff($officeId) as $staff) {
        if (isset($staff['staff_id']) && (string) $staff['staff_id'] === (string) $staffId) {
            return $staff;
        }
    }
    return null;
}

function import_staff_from_csv(string $fileTmpPath, ?string $officeId = null): int
{
    $imported = 0;
    if (!is_readable($fileTmpPath)) {
        return 0;
    }

    $staff = load_staff($officeId);
    if (($handle = fopen($fileTmpPath, 'r')) !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }
            [$name, $designation, $role, $departmentId, $phone, $email] = array_pad($row, 6, '');
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $nextId = next_master_id($staff, 'S');
            $staff[] = [
                'staff_id' => $nextId,
                'id' => $nextId,
                'full_name' => $name,
                'name' => $name,
                'designation' => trim($designation),
                'role' => trim($role),
                'department_id' => trim($departmentId),
                'phone' => trim($phone),
                'email' => trim($email),
                'active' => true,
            ];
            $imported++;
        }
        fclose($handle);
    }
    save_staff($officeId, $staff);
    return $imported;
}

function standardize_staff_record(array $record): array
{
    $now = gmdate('c');
    $record['staff_id'] = $record['staff_id'] ?? ($record['id'] ?? null);
    $record['id'] = $record['id'] ?? $record['staff_id'];
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
