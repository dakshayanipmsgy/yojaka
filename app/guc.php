<?php
require_once __DIR__ . '/document_templates.php';
require_once __DIR__ . '/acl.php';
require_once __DIR__ . '/auth.php';

function guc_normalize_record(array $record): array
{
    $record = acl_normalize($record);

    if ($record['owner'] === null && !empty($record['created_by'])) {
        $record['owner'] = $record['created_by'];
        $record['allowed_users'][] = $record['created_by'];
    }

    if (!empty($record['assignee'])) {
        $record['allowed_users'][] = $record['assignee'];
    }

    $record['allowed_users'] = array_values(array_unique(array_filter($record['allowed_users'])));

    if ($record['department_slug'] === null && !empty($record['owner'])) {
        [, , $deptSlug] = acl_parse_username_parts($record['owner']);
        if ($deptSlug !== null) {
            $record['department_slug'] = $deptSlug;
        }
    }

    return $record;
}

function load_guc_records(): array
{
    $records = load_document_records('guc');
    return array_map('guc_normalize_record', $records);
}

function save_guc_records(array $records): bool
{
    return save_document_records('guc', $records);
}

function find_guc_record(string $id): ?array
{
    foreach (load_guc_records() as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}
