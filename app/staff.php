<?php
// Staff helpers for unified staff directory

function staff_data_path(): string
{
    return YOJAKA_DATA_PATH . '/master/staff.json';
}

function load_all_staff(): array
{
    $path = staff_data_path();
    if (!file_exists($path)) {
        return [];
    }
    $raw = json_decode((string) file_get_contents($path), true);
    return is_array($raw) ? $raw : [];
}

function load_staff(string $officeId): array
{
    $records = load_all_staff();
    return array_values(array_filter($records, static function ($row) use ($officeId) {
        return ($row['office_id'] ?? null) === $officeId;
    }));
}

function save_staff(string $officeId, array $staff): bool
{
    $existing = load_all_staff();
    $filtered = array_values(array_filter($existing, static function ($row) use ($officeId) {
        return ($row['office_id'] ?? null) !== $officeId;
    }));

    $normalized = [];
    $now = date('c');
    foreach ($staff as $entry) {
        if (empty($entry['staff_id'])) {
            $entry['staff_id'] = sprintf('S-%04d', count($normalized) + 1);
        }
        $entry['created_at'] = $entry['created_at'] ?? $now;
        $entry['updated_at'] = $now;
        $entry['office_id'] = $entry['office_id'] ?? $officeId;
        $entry['active'] = array_key_exists('active', $entry) ? (bool) $entry['active'] : true;
        $normalized[] = $entry;
    }
    $payload = array_merge($filtered, $normalized);
    bootstrap_ensure_directory(dirname(staff_data_path()));
    return false !== @file_put_contents(staff_data_path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
