<?php
require_once __DIR__ . '/document_templates.php';
require_once __DIR__ . '/acl.php';
require_once __DIR__ . '/auth.php';

function work_order_normalize_record(array $record): array
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

function load_work_orders(): array
{
    $records = load_document_records('work_order');
    return array_map('work_order_normalize_record', $records);
}

function save_work_orders(array $records): bool
{
    return save_document_records('work_order', $records);
}

function find_work_order_record(string $id): ?array
{
    foreach (load_work_orders() as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}
