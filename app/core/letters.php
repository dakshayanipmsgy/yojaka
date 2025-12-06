<?php
// Letters module repository helpers.

function yojaka_letters_get_base_path(string $deptSlug): string
{
    return yojaka_config('paths.data_path') . '/departments/' . $deptSlug . '/modules/letters';
}

function yojaka_letters_ensure_storage(string $deptSlug): void
{
    $basePath = yojaka_letters_get_base_path($deptSlug);
    $recordsPath = $basePath . '/records';

    if (!is_dir($recordsPath)) {
        mkdir($recordsPath, 0777, true);
    }

    $indexPath = $basePath . '/index.json';
    if (!file_exists($indexPath)) {
        file_put_contents($indexPath, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    }

    $templatesDir = yojaka_config('paths.data_path') . '/departments/' . $deptSlug . '/templates';
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0777, true);
    }

    $deptLettersTemplate = $templatesDir . '/letters.json';
    if (!file_exists($deptLettersTemplate)) {
        file_put_contents($deptLettersTemplate, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    }
}

function yojaka_letters_load_index(string $deptSlug): array
{
    $indexPath = yojaka_letters_get_base_path($deptSlug) . '/index.json';

    if (!file_exists($indexPath)) {
        return [];
    }

    $content = file_get_contents($indexPath);
    if ($content === false || $content === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function yojaka_letters_save_index(string $deptSlug, array $index): void
{
    $indexPath = yojaka_letters_get_base_path($deptSlug) . '/index.json';
    file_put_contents($indexPath, json_encode(array_values($index), JSON_PRETTY_PRINT), LOCK_EX);
}

function yojaka_letters_generate_id(string $deptSlug): string
{
    yojaka_letters_ensure_storage($deptSlug);

    $basePath = yojaka_letters_get_base_path($deptSlug);
    $counterPath = $basePath . '/counter.json';

    $current = 0;
    if (file_exists($counterPath)) {
        $raw = file_get_contents($counterPath);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['last'])) {
            $current = (int) $decoded['last'];
        }
    }

    $next = $current + 1;
    $id = 'letter_' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

    file_put_contents($counterPath, json_encode(['last' => $next], JSON_PRETTY_PRINT), LOCK_EX);

    return $id;
}

function yojaka_letters_load_record(string $deptSlug, string $id): ?array
{
    $recordPath = yojaka_letters_get_base_path($deptSlug) . '/records/' . $id . '.json';

    if (!file_exists($recordPath)) {
        return null;
    }

    $content = file_get_contents($recordPath);
    if ($content === false || $content === '') {
        return null;
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : null;
}

function yojaka_letters_index_entry_from_record(array $record): array
{
    return [
        'id' => $record['id'] ?? '',
        'template_id' => $record['template_id'] ?? '',
        'subject' => $record['fields']['subject'] ?? '',
        'status' => $record['status'] ?? '',
        'created_at' => $record['created_at'] ?? '',
        'updated_at' => $record['updated_at'] ?? '',
        'owner_username' => $record['owner_username'] ?? '',
        'assignee_username' => $record['assignee_username'] ?? '',
    ];
}

function yojaka_letters_save_record(string $deptSlug, array $record): void
{
    yojaka_letters_ensure_storage($deptSlug);

    $recordPath = yojaka_letters_get_base_path($deptSlug) . '/records/' . ($record['id'] ?? '');
    $recordPath = rtrim($recordPath, '/') . '.json';

    file_put_contents($recordPath, json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);

    $index = yojaka_letters_load_index($deptSlug);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') === ($record['id'] ?? '')) {
            $entry = yojaka_letters_index_entry_from_record($record);
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $index[] = yojaka_letters_index_entry_from_record($record);
    }

    yojaka_letters_save_index($deptSlug, $index);
}

function yojaka_letters_list_records_for_user(string $deptSlug, array $user): array
{
    yojaka_letters_ensure_storage($deptSlug);

    $index = yojaka_letters_load_index($deptSlug);
    $records = [];

    foreach ($index as $entry) {
        $id = $entry['id'] ?? null;
        if (!$id) {
            continue;
        }

        $record = yojaka_letters_load_record($deptSlug, $id);
        if (!$record) {
            continue;
        }

        if (yojaka_acl_can_view_record($user, $record)) {
            $records[] = $record;
        }
    }

    return $records;
}
