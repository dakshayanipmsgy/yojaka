<?php
// Global Indexing Engine v2

function index_v2_dir(): string
{
    return YOJAKA_DATA_PATH . '/index_v2';
}

function ensure_index_v2_dir(): void
{
    $dir = index_v2_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

function index_v2_path(string $module): string
{
    return index_v2_dir() . '/' . $module . '.idx.json';
}

function index_v2_exists(string $module): bool
{
    $path = index_v2_path($module);
    return file_exists($path) && filesize($path) > 5;
}

function build_index_v2(string $module): array
{
    ensure_index_v2_dir();
    $records = [];
    switch ($module) {
        case 'rti':
            $records = load_rti_cases();
            break;
        case 'dak':
            $records = load_dak_entries();
            break;
        case 'bills':
            $records = load_bills();
            break;
        case 'inspection':
            $records = load_inspection_reports();
            break;
        case 'documents':
            $records = array_merge(load_meeting_minutes(), load_work_orders(), load_guc_documents());
            break;
        default:
            $records = [];
    }

    $index = [];
    foreach ($records as $record) {
        $index[] = index_v2_normalize_entry($record);
    }

    $path = index_v2_path($module);
    file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $index;
}

function index_v2_normalize_entry(array $entity): array
{
    $customFields = [];
    if (!empty($entity['custom_fields']) && is_array($entity['custom_fields'])) {
        foreach ($entity['custom_fields'] as $key => $value) {
            $customFields[$key] = is_scalar($value) ? (string)$value : json_encode($value);
        }
    }
    return [
        'id' => $entity['id'] ?? '',
        'office_id' => $entity['office_id'] ?? '',
        'status' => $entity['status'] ?? ($entity['workflow_state'] ?? ''),
        'created_at' => $entity['created_at'] ?? ($entity['date_received'] ?? ($entity['date_of_inspection'] ?? '')),
        'subject' => $entity['subject'] ?? ($entity['title'] ?? ($entity['work_name'] ?? '')),
        'summary' => $entity['summary'] ?? ($entity['description'] ?? ''),
        'archived' => !empty($entity['archived']),
        'custom_fields' => $customFields,
    ];
}

function update_index_v2_entry(string $module, array $entity): void
{
    $path = index_v2_path($module);
    ensure_index_v2_dir();
    $index = [];
    if (file_exists($path)) {
        $index = json_decode(file_get_contents($path), true) ?: [];
    }
    $id = $entity['id'] ?? null;
    if (!$id) {
        return;
    }
    $updated = false;
    foreach ($index as &$row) {
        if (($row['id'] ?? '') === $id) {
            $row = index_v2_normalize_entry($entity);
            $updated = true;
            break;
        }
    }
    unset($row);
    if (!$updated) {
        $index[] = index_v2_normalize_entry($entity);
    }
    file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function search_index_v2(string $module, array $criteria): array
{
    if (!index_v2_exists($module)) {
        $index = build_index_v2($module);
    } else {
        $index = json_decode(file_get_contents(index_v2_path($module)), true) ?: [];
    }

    $results = [];
    foreach ($index as $row) {
        if (!index_v2_matches($row, $criteria)) {
            continue;
        }
        $results[] = $row['id'];
    }
    return $results;
}

function index_v2_matches(array $row, array $criteria): bool
{
    $id = trim($criteria['id'] ?? '');
    if ($id !== '' && stripos((string)$row['id'], $id) === false) {
        return false;
    }
    $keyword = trim($criteria['keyword'] ?? '');
    if ($keyword !== '') {
        $haystack = strtolower(($row['subject'] ?? '') . ' ' . ($row['summary'] ?? '') . ' ' . json_encode($row['custom_fields'] ?? []));
        if (strpos($haystack, strtolower($keyword)) === false) {
            return false;
        }
    }
    if (isset($criteria['status']) && $criteria['status'] !== '' && ($row['status'] ?? '') !== $criteria['status']) {
        return false;
    }
    if (!empty($criteria['office_id']) && ($row['office_id'] ?? '') !== $criteria['office_id']) {
        return false;
    }
    if (isset($criteria['archived']) && $criteria['archived'] !== '') {
        $archivedFlag = filter_var($criteria['archived'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($archivedFlag !== null && (bool)$row['archived'] !== $archivedFlag) {
            return false;
        }
    }
    $from = $criteria['date_from'] ?? '';
    $to = $criteria['date_to'] ?? '';
    $createdAt = $row['created_at'] ?? '';
    if ($from !== '' && $createdAt !== '' && strtotime($createdAt) < strtotime($from)) {
        return false;
    }
    if ($to !== '' && $createdAt !== '' && strtotime($createdAt) > strtotime($to)) {
        return false;
    }
    return true;
}

function rebuild_all_index_v2(): array
{
    $modules = ['rti', 'dak', 'bills', 'inspection', 'documents'];
    $results = [];
    foreach ($modules as $module) {
        $results[$module] = build_index_v2($module);
    }
    return $results;
}
