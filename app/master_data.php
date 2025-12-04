<?php
function master_data_dir(): string
{
    return YOJAKA_DATA_PATH . '/master';
}

function master_contractors_path(): string
{
    return master_data_dir() . '/contractors.json';
}

function contractors_data_path(?string $departmentId = null): string
{
    // Department is the top-level container (legacy office folder). We keep
    // legacy master_data storage for compatibility.
    if ($departmentId !== null && $departmentId !== '') {
        return rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR) . '/' . trim($departmentId, '/ ') . '/contractors.json';
    }

    return master_contractors_path();
}

function load_json_list(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_json_list(string $path, array $list): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    $result = false;
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        $result = fwrite($handle, json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return $result;
}

function standardize_contractor(array $record, ?string $departmentId = null): array
{
    $record['contractor_id'] = $record['contractor_id'] ?? ($record['id'] ?? null);
    $record['id'] = $record['id'] ?? $record['contractor_id'];
    $record['name'] = $record['name'] ?? '';
    $record['category'] = $record['category'] ?? '';
    $primaryDepartment = $record['department_id'] ?? ($record['office_id'] ?? $departmentId);
    $record['department_id'] = $primaryDepartment;
    $record['office_id'] = $record['office_id'] ?? $primaryDepartment; // legacy mirror only
    $record['address'] = $record['address'] ?? '';
    $record['gstin'] = $record['gstin'] ?? ($record['gst_no'] ?? '');
    $record['pan'] = $record['pan'] ?? '';
    $record['phone'] = $record['phone'] ?? '';
    $record['email'] = $record['email'] ?? '';
    $record['active'] = array_key_exists('active', $record) ? (bool) $record['active'] : true;
    return $record;
}

function load_contractors(?string $departmentId = null): array
{
    $paths = [];
    if ($departmentId !== null && $departmentId !== '') {
        $paths[] = contractors_data_path($departmentId);
    }

    // Legacy fallbacks.
    $paths[] = master_contractors_path();
    $paths[] = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR) . '/contractors.json';

    foreach (array_unique($paths) as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $data = load_json_list($path);
        if (!empty($data)) {
            return array_map(static function ($row) use ($departmentId) {
                return standardize_contractor(is_array($row) ? $row : [], $departmentId);
            }, $data);
        }
    }

    return [];
}

function save_contractors(?string $departmentId, array $list): bool
{
    $path = contractors_data_path($departmentId);
    $payload = array_values(array_map(function ($row) use ($departmentId) {
        return standardize_contractor($row, $departmentId);
    }, $list));

    $saved = save_json_list($path, $payload);

    // Maintain legacy copy if it already exists.
    $legacyPath = master_contractors_path();
    if ($saved && file_exists($legacyPath) && $path !== $legacyPath) {
        save_json_list($legacyPath, $payload);
    }

    return $saved;
}

function next_master_id(array $items, string $prefix): string
{
    $max = 0;
    foreach ($items as $item) {
        if (!empty($item['id']) && preg_match('/' . preg_quote($prefix, '/') . '-(\d{4})/', $item['id'], $m)) {
            $num = (int) $m[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }
    $next = $max + 1;
    return sprintf('%s-%04d', $prefix, $next);
}

function upsert_contractor(array $existing, array $contractor): array
{
    $id = $contractor['contractor_id'] ?? ($contractor['id'] ?? null);
    if ($id === null) {
        $contractor['contractor_id'] = next_master_id($existing, 'C');
        $contractor['id'] = $contractor['contractor_id'];
        $existing[] = $contractor;
        return $existing;
    }

    $updated = false;
    foreach ($existing as &$row) {
        if (($row['contractor_id'] ?? $row['id'] ?? null) === $id) {
            $row = array_merge($row, $contractor);
            $updated = true;
            break;
        }
    }
    unset($row);

    if (!$updated) {
        $existing[] = $contractor;
    }

    return $existing;
}

function import_contractors_from_csv(string $fileTmpPath, ?string $departmentId = null): int
{
    $imported = 0;
    if (!is_readable($fileTmpPath)) {
        return 0;
    }
    $contractors = load_contractors($departmentId);
    if (($handle = fopen($fileTmpPath, 'r')) !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }
            [$name, $category, $address, $gstin, $pan, $phone, $email] = array_pad($row, 7, '');
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $contractor = [
                'contractor_id' => next_master_id($contractors, 'C'),
                'name' => $name,
                'category' => trim($category),
                'address' => trim($address),
                'gstin' => trim($gstin),
                'pan' => trim($pan),
                'phone' => trim($phone),
                'email' => trim($email),
                'department_id' => $departmentId,
                'active' => true,
            ];
            $contractors = upsert_contractor($contractors, $contractor);
            $imported++;
        }
        fclose($handle);
    }
    save_contractors($departmentId, $contractors);
    return $imported;
}

?>
