<?php
require_once __DIR__ . '/master_data.php';

function staff_data_path(?string $departmentId = null): string
{
    // Department is the outer folder (legacy office). Staff entries are stored
    // by department only after the refactor.
    $base = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR);
    if ($departmentId !== null && $departmentId !== '') {
        return $base . '/' . trim($departmentId, '/ ') . '/staff.json';
    }

    // Legacy shared staff file used by older master_data implementation.
    return $base . '/master/staff.json';
}

function load_staff(?string $departmentId = null): array
{
    $base = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR);
    $paths = [];

    if ($departmentId !== null && $departmentId !== '') {
        $paths[] = staff_data_path($departmentId);
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
            return array_map(static function ($row) use ($departmentId) {
                return standardize_staff_record(is_array($row) ? $row : [], $departmentId);
            }, $json);
        }
    }
    return [];
}

function save_staff(?string $departmentId, array $staffList): bool
{
    $path = ($departmentId !== null && $departmentId !== '') ? staff_data_path($departmentId) : staff_data_path(null);
    bootstrap_ensure_directory(dirname($path));
    $payload = array_values(array_map(function ($row) use ($departmentId) {
        return standardize_staff_record($row, $departmentId);
    }, $staffList));
    $saved = false !== @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // Also persist a legacy flat copy if it already exists to maintain backward compatibility.
    $legacyPath = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR) . '/staff.json';
    if ($saved && file_exists($legacyPath) && $path !== $legacyPath) {
        bootstrap_ensure_directory(dirname($legacyPath));
        @file_put_contents($legacyPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    return $saved;
}

function find_staff_by_id(?string $departmentId, string $staffId): ?array
{
    foreach (load_staff($departmentId) as $staff) {
        if (isset($staff['staff_id']) && (string) $staff['staff_id'] === (string) $staffId) {
            return $staff;
        }
    }
    return null;
}

function upsert_staff(array $existing, array $staff): array
{
    $id = $staff['staff_id'] ?? ($staff['id'] ?? null);
    if ($id === null) {
        $staff['staff_id'] = next_master_id($existing, 'S');
        $staff['id'] = $staff['staff_id'];
        $existing[] = $staff;
        return $existing;
    }

    $updated = false;
    foreach ($existing as &$row) {
        if (($row['staff_id'] ?? $row['id'] ?? null) === $id) {
            $row = array_merge($row, $staff);
            $updated = true;
            break;
        }
    }
    unset($row);

    if (!$updated) {
        $existing[] = $staff;
    }

    return $existing;
}

function import_staff_from_csv(string $fileTmpPath, ?string $departmentId = null): int
{
    $imported = 0;
    if (!is_readable($fileTmpPath)) {
        return 0;
    }

    $staffList = load_staff($departmentId);
    if (($handle = fopen($fileTmpPath, 'r')) !== false) {
        $header = fgetcsv($handle);
        $hasHeader = is_array($header) && count(array_filter($header, 'strlen')) > 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }

            if ($hasHeader) {
                $data = array_combine($header, $row);
            } else {
                [$fullName, $designation, $role, $deptId, $phone, $email] = array_pad($row, 6, '');
                $data = [
                    'full_name' => $fullName,
                    'designation' => $designation,
                    'role' => $role,
                    'department_id' => $deptId,
                    'phone' => $phone,
                    'email' => $email,
                ];
            }

            if (empty(trim($data['full_name'] ?? '')) && empty(trim($data['name'] ?? ''))) {
                continue;
            }

            $staff = [
                'staff_id' => trim($data['staff_id'] ?? ($data['id'] ?? '')),
                'full_name' => trim($data['full_name'] ?? ($data['name'] ?? '')),
                'designation' => trim($data['designation'] ?? ''),
                'role' => trim($data['role'] ?? ''),
                'department_id' => trim($data['department_id'] ?? ($data['office_id'] ?? $departmentId)),
                'phone' => trim($data['phone'] ?? ''),
                'email' => trim($data['email'] ?? ''),
                'active' => strtolower(trim($data['active'] ?? 'true')) !== 'false',
            ];

            $staffList = upsert_staff($staffList, $staff);
            $imported++;
        }
        fclose($handle);
    }
    save_staff($departmentId, $staffList);
    return $imported;
}

function standardize_staff_record(array $record, ?string $departmentId = null): array
{
    $now = gmdate('c');
    $record['staff_id'] = $record['staff_id'] ?? ($record['id'] ?? null);
    $record['id'] = $record['id'] ?? $record['staff_id'];
    $record['full_name'] = $record['full_name'] ?? ($record['name'] ?? '');
    $record['designation'] = $record['designation'] ?? ($record['title'] ?? '');
    $legacyDepartment = $record['department_id'] ?? ($record['department'] ?? null);
    $primaryDepartment = $record['office_id'] ?? $legacyDepartment ?? $departmentId;
    $record['department_id'] = $primaryDepartment;
    if (!empty($legacyDepartment) && $legacyDepartment !== $primaryDepartment) {
        $record['subunit_id'] = $record['subunit_id'] ?? $legacyDepartment;
    }
    $record['office_id'] = $record['office_id'] ?? $primaryDepartment; // legacy mirror only
    $record['phone'] = $record['phone'] ?? null;
    $record['email'] = $record['email'] ?? null;
    $record['active'] = array_key_exists('active', $record) ? (bool) $record['active'] : true;
    $record['created_at'] = $record['created_at'] ?? $now;
    $record['updated_at'] = $record['updated_at'] ?? $record['created_at'];
    return $record;
}

?>
