<?php
// Archiving and retention helpers for Yojaka v1.5

function ensure_archival_defaults(array $entity): array
{
    if (!array_key_exists('archived', $entity)) {
        $entity['archived'] = false;
    }
    if (!array_key_exists('archived_at', $entity)) {
        $entity['archived_at'] = null;
    }
    if (!array_key_exists('archive_reason', $entity)) {
        $entity['archive_reason'] = '';
    }
    return $entity;
}

function can_archive_entity(string $module, array $entity): bool
{
    global $config;
    $retention = $config['retention'][$module]['active_days'] ?? null;
    if (($entity['archived'] ?? false) || $retention === null) {
        return false;
    }
    $created = $entity['created_at'] ?? ($entity['date_of_receipt'] ?? ($entity['date_received'] ?? null));
    if (!$created) {
        return false;
    }
    $createdTs = strtotime($created);
    if ($createdTs === false) {
        return false;
    }
    $ageDays = floor((time() - $createdTs) / 86400);
    return $ageDays >= (int) $retention;
}

function archive_entity(array &$entity, string $reason = ''): void
{
    $entity['archived'] = true;
    $entity['archived_at'] = gmdate('c');
    if ($reason !== '') {
        $entity['archive_reason'] = $reason;
    }
}

function auto_archive_entities(string $module, array &$entities): int
{
    $count = 0;
    foreach ($entities as &$entity) {
        $entity = ensure_archival_defaults($entity);
        if (can_archive_entity($module, $entity)) {
            archive_entity($entity, 'Auto-archived by retention policy');
            $count++;
        }
    }
    return $count;
}

function with_archival_defaults(array $items): array
{
    return array_map('ensure_archival_defaults', $items);
}
