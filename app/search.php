<?php
// Advanced search handlers for modules using index v2 when available

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/acl.php';
require_once __DIR__ . '/meeting_minutes.php';

function search_paginate(array $items, int $page = 1, int $limit = 25): array
{
    $offset = max(0, ($page - 1) * $limit);
    return array_slice($items, $offset, $limit);
}

function search_with_index_or_scan(string $module, array $criteria, callable $loader): array
{
    $page = isset($criteria['page']) ? (int)$criteria['page'] : 1;
    $limit = isset($criteria['limit']) ? (int)$criteria['limit'] : 25;
    $ids = search_index_v2($module, $criteria);
    if (!empty($ids)) {
        $all = $loader();
        $indexed = [];
        foreach ($all as $item) {
            if (in_array($item['id'] ?? null, $ids, true) && search_record_matches($item, $criteria)) {
                $indexed[] = $item;
            }
        }
        return search_paginate($indexed, $page, $limit);
    }
    $all = $loader();
    $filtered = [];
    foreach ($all as $item) {
        if (search_record_matches($item, $criteria)) {
            $filtered[] = $item;
        }
    }
    return search_paginate($filtered, $page, $limit);
}

function search_record_matches(array $record, array $criteria): bool
{
    $id = trim($criteria['id'] ?? '');
    if ($id !== '' && stripos((string)($record['id'] ?? ''), $id) === false) {
        return false;
    }
    $keyword = trim($criteria['keyword'] ?? '');
    if ($keyword !== '') {
        $haystack = strtolower(json_encode($record));
        if (strpos($haystack, strtolower($keyword)) === false) {
            return false;
        }
    }
    if (isset($criteria['status']) && $criteria['status'] !== '' && ($record['status'] ?? '') !== $criteria['status']) {
        return false;
    }
    if (isset($criteria['archived']) && $criteria['archived'] !== '') {
        $archivedFlag = filter_var($criteria['archived'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($archivedFlag !== null && (bool)($record['archived'] ?? false) !== $archivedFlag) {
            return false;
        }
    }
    if (!empty($criteria['office_id']) && ($record['office_id'] ?? '') !== $criteria['office_id']) {
        return false;
    }
    $from = $criteria['date_from'] ?? '';
    $to = $criteria['date_to'] ?? '';
    $createdAt = $record['created_at'] ?? ($record['date_received'] ?? ($record['date_of_inspection'] ?? ''));
    if ($from !== '' && $createdAt !== '' && strtotime($createdAt) < strtotime($from)) {
        return false;
    }
    if ($to !== '' && $createdAt !== '' && strtotime($createdAt) > strtotime($to)) {
        return false;
    }
    return true;
}

function search_rti(array $criteria): array
{
    return search_with_index_or_scan('rti', $criteria, 'load_rti_cases');
}

function search_dak(array $criteria): array
{
    return search_with_index_or_scan('dak', $criteria, 'load_dak_entries');
}

function search_bills(array $criteria): array
{
    return search_with_index_or_scan('bills', $criteria, 'load_bills');
}

function search_inspection(array $criteria): array
{
    $results = search_with_index_or_scan('inspection', $criteria, 'load_inspection_reports');
    $currentUser = yojaka_current_user();
    $filtered = [];
    foreach ($results as $record) {
        $record = acl_normalize($record);
        if (acl_can_view($currentUser, $record)) {
            $filtered[] = $record;
        }
    }

    return $filtered;
}

function search_documents(array $criteria): array
{
    $loader = function () {
        return array_merge(load_meeting_minutes(), load_work_orders(), load_guc_documents());
    };
    $results = search_with_index_or_scan('documents', $criteria, $loader);

    $currentUser = yojaka_current_user();
    $filtered = [];

    foreach ($results as $record) {
        if (($record['category'] ?? '') === 'meeting_minutes') {
            $record = meeting_minutes_normalize_record($record);
            if (!acl_can_view($currentUser, $record)) {
                continue;
            }
        }
        $filtered[] = $record;
    }

    return $filtered;
}
