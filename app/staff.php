<?php
// Staff helpers for unified staff directory

function staff_data_path(string $officeId): string
{
    $safeOffice = preg_replace('/[^A-Za-z0-9_\-]/', '_', $officeId);
    return YOJAKA_DATA_PATH . '/master/staff_' . $safeOffice . '.json';
}

function load_staff(string $officeId): array
{
    $path = staff_data_path($officeId);
    if (!file_exists($path)) {
        $legacy = YOJAKA_DATA_PATH . '/master/staff.json';
        if (!file_exists($legacy)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($legacy), true);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter($raw, static function ($row) use ($officeId) {
            return !isset($row['office_id']) || ($row['office_id'] === $officeId);
        }));
    }
    $raw = json_decode((string) file_get_contents($path), true);
    return is_array($raw) ? $raw : [];
}

function next_staff_identifier(array $existing, array $pending): string
{
    $max = 0;
    foreach (array_merge($existing, $pending) as $row) {
        $id = $row['staff_id'] ?? ($row['id'] ?? '');
        if (preg_match('/S-(\d{4})/', (string) $id, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('S-%04d', $max + 1);
}

function save_staff(string $officeId, array $staff): bool
{
    $path = staff_data_path($officeId);
    $existing = load_staff($officeId);

    $normalized = [];
    $now = date('c');
    foreach ($staff as $entry) {
        if (empty($entry['staff_id']) && !empty($entry['id'])) {
            $entry['staff_id'] = $entry['id'];
            unset($entry['id']);
        }
        if (empty($entry['staff_id'])) {
            $entry['staff_id'] = next_staff_identifier($existing, $normalized);
        }
        $entry['full_name'] = $entry['full_name'] ?? ($entry['name'] ?? '');
        $entry['created_at'] = $entry['created_at'] ?? $now;
        $entry['updated_at'] = $now;
        $entry['office_id'] = $entry['office_id'] ?? $officeId;
        $entry['active'] = array_key_exists('active', $entry) ? (bool) $entry['active'] : true;
        $normalized[] = $entry;
    }

    bootstrap_ensure_directory(dirname($path));
    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $fp = fopen($path, 'c+');
    if (!$fp) {
        return false;
    }
    $result = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $result = fwrite($fp, $json) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $result;
}

function find_staff_by_id(string $officeId, string $staffId): ?array
{
    foreach (load_staff($officeId) as $record) {
        if (($record['staff_id'] ?? '') === $staffId) {
            return $record;
        }
    }
    return null;
}

function find_staff_by_username(string $officeId, string $username): ?array
{
    $user = find_user_by_username($username);
    if (!$user || empty($user['staff_id'])) {
        return null;
    }
    return find_staff_by_id($officeId, $user['staff_id']);
}

function import_staff_from_csv(string $officeId, string $fileTmpPath): int
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
            $staff[] = [
                'staff_id' => null,
                'full_name' => $name,
                'designation' => trim($designation),
                'role' => trim($role),
                'department_id' => trim($departmentId),
                'phone' => trim($phone),
                'email' => trim($email),
                'active' => true,
                'office_id' => $officeId,
            ];
            $imported++;
        }
        fclose($handle);
    }

    save_staff($officeId, $staff);
    return $imported;
}
