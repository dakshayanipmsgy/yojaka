<?php
// MIS helper functions for reporting and analytics.

function mis_get_value(array $item, string $field)
{
    if ($field === '') {
        return null;
    }
    if (strpos($field, '.') === false) {
        return $item[$field] ?? null;
    }
    $parts = explode('.', $field);
    $current = $item;
    foreach ($parts as $part) {
        if (!is_array($current) || !array_key_exists($part, $current)) {
            return null;
        }
        $current = $current[$part];
    }
    return $current;
}

function mis_parse_date_value($value): ?DateTime
{
    if (empty($value)) {
        return null;
    }
    try {
        return new DateTime((string) $value);
    } catch (Exception $e) {
        return null;
    }
}

function mis_filter_by_date(array $items, array $dateFields, ?string $fromDate, ?string $toDate): array
{
    $from = $fromDate ? mis_parse_date_value($fromDate) : null;
    $to = $toDate ? mis_parse_date_value($toDate) : null;

    return array_values(array_filter($items, function ($item) use ($dateFields, $from, $to) {
        $dateValue = null;
        foreach ($dateFields as $field) {
            $candidate = mis_get_value($item, $field);
            if ($candidate) {
                $dateValue = mis_parse_date_value($candidate);
            }
            if ($dateValue) {
                break;
            }
        }

        if (!$dateValue) {
            return false;
        }

        if ($from && $dateValue < $from) {
            return false;
        }
        if ($to && $dateValue > $to) {
            return false;
        }
        return true;
    }));
}

function mis_filter_by_department(array $items, ?string $departmentId): array
{
    if (!$departmentId) {
        return $items;
    }
    return array_values(array_filter($items, function ($item) use ($departmentId) {
        return ($item['department_id'] ?? null) === $departmentId;
    }));
}

function mis_filter_by_user(array $items, ?string $username, array $fieldCandidates): array
{
    if (!$username) {
        return $items;
    }
    return array_values(array_filter($items, function ($item) use ($username, $fieldCandidates) {
        foreach ($fieldCandidates as $field) {
            $value = mis_get_value($item, $field);
            if ($value && strcasecmp((string) $value, (string) $username) === 0) {
                return true;
            }
        }
        return false;
    }));
}

function mis_count_by_status(array $items, string $statusField): array
{
    $counts = [];
    foreach ($items as $item) {
        $status = (string) ($item[$statusField] ?? 'Unknown');
        if (!isset($counts[$status])) {
            $counts[$status] = 0;
        }
        $counts[$status]++;
    }
    ksort($counts);
    return $counts;
}

function mis_sum_field(array $items, string $field): float
{
    $sum = 0.0;
    foreach ($items as $item) {
        $sum += (float) ($item[$field] ?? 0);
    }
    return $sum;
}

function mis_group_count(array $items, string $field): array
{
    $counts = [];
    foreach ($items as $item) {
        $value = (string) ($item[$field] ?? 'Unknown');
        if (!isset($counts[$value])) {
            $counts[$value] = 0;
        }
        $counts[$value]++;
    }
    arsort($counts);
    return $counts;
}

function mis_format_date_for_display(?string $value, string $format = 'Y-m-d'): string
{
    if (!$value) {
        return '';
    }
    $parsed = mis_parse_date_value($value);
    return $parsed ? $parsed->format($format) : (string) $value;
}
