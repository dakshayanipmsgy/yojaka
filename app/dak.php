<?php
// Dak & File Movement helper functions

function dak_entries_path(): string
{
    global $config;
    return rtrim($config['dak_data_path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ($config['dak_entries_file'] ?? 'dak_entries.json');
}

function dak_movements_directory(): string
{
    global $config;
    return rtrim($config['dak_data_path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'movements';
}

function movement_logs_path(string $dak_id): string
{
    return dak_movements_directory() . DIRECTORY_SEPARATOR . $dak_id . '.log';
}

function ensure_dak_storage(): void
{
    $basePath = dirname(dak_entries_path());
    if (!is_dir($basePath)) {
        @mkdir($basePath, 0755, true);
    }

    $movementsDir = dak_movements_directory();
    if (!is_dir($movementsDir)) {
        @mkdir($movementsDir, 0755, true);
    }

    $entriesPath = dak_entries_path();
    if (!file_exists($entriesPath)) {
        $handle = fopen($entriesPath, 'c+');
        if ($handle) {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, json_encode([], JSON_PRETTY_PRINT));
                fflush($handle);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    $htaccessPath = $basePath . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccessPath)) {
        @file_put_contents($htaccessPath, "Options -Indexes\nDeny from all\n<FilesMatch \\\"\\.(json|log)$\\\">\n    Require all denied\n</FilesMatch>\n");
    }
}

function load_dak_entries(): array
{
    $path = dak_entries_path();
    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);
    $data = is_array($data) ? $data : [];
    return array_map(function ($entry) {
        return enrich_workflow_defaults('dak', $entry);
    }, $data);
}

function save_dak_entries(array $entries): void
{
    $path = dak_entries_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open dak entries file for writing.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock dak entries file.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode(array_values($entries), JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function generate_next_dak_id(array $entries): string
{
    $maxNumber = 0;
    foreach ($entries as $entry) {
        if (!empty($entry['id']) && preg_match('/DAK-(\d{6})/', $entry['id'], $matches)) {
            $num = (int) $matches[1];
            if ($num > $maxNumber) {
                $maxNumber = $num;
            }
        }
    }

    $next = $maxNumber + 1;
    return sprintf('DAK-%06d', $next);
}

function log_dak_movement(string $dak_id, string $action, ?string $from_user, ?string $to_user, string $remarks = ''): void
{
    $path = movement_logs_path($dak_id);
    $entry = [
        'timestamp' => gmdate('c'),
        'action' => $action,
        'from_user' => $from_user,
        'to_user' => $to_user,
        'remarks' => $remarks,
    ];

    $handle = fopen($path, 'a');
    if (!$handle) {
        return;
    }

    if (flock($handle, LOCK_EX)) {
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n");
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function update_dak_status(array &$entries, string $dak_id, string $new_status): bool
{
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $dak_id) {
            $entry['status'] = $new_status;
            $entry['updated_at'] = gmdate('c');
            log_dak_movement($dak_id, 'status_changed', $entry['assigned_to'] ?? null, $entry['assigned_to'] ?? null, 'Status changed to ' . $new_status);
            return true;
        }
    }
    return false;
}

function assign_dak_to_user(array &$entries, string $dak_id, string $username): bool
{
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $dak_id) {
            $from = $entry['assigned_to'] ?? null;
            $entry['assigned_to'] = $username;
            $entry['status'] = $entry['status'] === 'Closed' ? $entry['status'] : 'Assigned';
            $entry['updated_at'] = gmdate('c');
            log_dak_movement($dak_id, 'assigned', $from, $username, 'Assigned to user');
            return true;
        }
    }
    return false;
}

function forward_dak(array &$entries, string $dak_id, ?string $from_user, string $to_user, string $remarks): bool
{
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $dak_id) {
            $entry['assigned_to'] = $to_user;
            $entry['status'] = $entry['status'] === 'Closed' ? $entry['status'] : 'Assigned';
            $entry['updated_at'] = gmdate('c');
            log_dak_movement($dak_id, 'forwarded', $from_user, $to_user, $remarks);
            return true;
        }
    }
    return false;
}

function is_dak_overdue(array $dak_entry): bool
{
    global $config;
    $limit = (int) ($config['dak_overdue_days'] ?? 7);
    if (($dak_entry['status'] ?? '') === 'Closed') {
        return false;
    }

    $dateReceived = $dak_entry['date_received'] ?? null;
    if (!$dateReceived) {
        return false;
    }

    $receivedTs = strtotime($dateReceived);
    if ($receivedTs === false) {
        return false;
    }

    $ageDays = floor((time() - $receivedTs) / 86400);
    return $ageDays > $limit;
}

function dak_statuses(): array
{
    return [
        'Received',
        'Assigned',
        'In-progress',
        'Replied',
        'Closed',
    ];
}
