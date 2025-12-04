<?php
// RTI helper functions for Yojaka v0.4

function rti_cases_path(): string
{
    global $config;
    $directory = rtrim($config['rti_data_path'], DIRECTORY_SEPARATOR);
    $file = $config['rti_cases_file'] ?? 'rti_cases.json';
    return $directory . DIRECTORY_SEPARATOR . $file;
}

function ensure_rti_storage(): void
{
    global $config;
    $dir = rtrim($config['rti_data_path'], DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $path = rti_cases_path();
    if (!file_exists($path)) {
        file_put_contents($path, json_encode([]));
    }
}

function load_rti_cases(): array
{
    $path = rti_cases_path();
    if (!file_exists($path)) {
        return [];
    }
    $contents = file_get_contents($path);
    $data = json_decode($contents, true);
    $data = is_array($data) ? $data : [];

    return array_map(function ($case) {
        return enrich_workflow_defaults('rti', $case);
    }, $data);
}

function save_rti_cases(array $cases): void
{
    $path = rti_cases_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open RTI cases file.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock RTI cases file.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($cases, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function generate_next_rti_id(array $cases): string
{
    $max = 0;
    foreach ($cases as $case) {
        if (!empty($case['id']) && preg_match('/RTI-(\d+)/', $case['id'], $matches)) {
            $num = (int) $matches[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }
    $next = $max + 1;
    return 'RTI-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function find_rti_by_id(array $cases, string $id): ?array
{
    foreach ($cases as $case) {
        if (($case['id'] ?? '') === $id) {
            return $case;
        }
    }
    return null;
}

function update_rti_case(array &$cases, array $updatedCase): void
{
    foreach ($cases as $index => $case) {
        if (($case['id'] ?? '') === ($updatedCase['id'] ?? '')) {
            $cases[$index] = $updatedCase;
            return;
        }
    }
}

function compute_rti_reply_deadline(string $dateOfReceipt): string
{
    global $config;
    $days = (int) ($config['rti_reply_days'] ?? 30);
    try {
        $date = new DateTime($dateOfReceipt);
    } catch (Exception $e) {
        $date = new DateTime();
    }
    $date->modify('+' . $days . ' days');
    return $date->format('Y-m-d');
}

function is_rti_overdue(array $case): bool
{
    if (($case['status'] ?? '') !== 'Pending') {
        return false;
    }
    $deadline = $case['reply_deadline'] ?? null;
    if (!$deadline) {
        return false;
    }
    $today = (new DateTime('today'))->format('Y-m-d');
    return $today > $deadline;
}
