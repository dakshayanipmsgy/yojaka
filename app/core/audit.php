<?php
// Audit logging helpers for Yojaka.
// Provides per-department append-only audit logs and basic query helpers.

/**
 * Return the audit log file path for a department, ensuring directories exist.
 */
function yojaka_audit_file_path(string $deptSlug): string
{
    $baseAuditPath = yojaka_config('paths.data_path') . '/audit';
    if (!is_dir($baseAuditPath)) {
        mkdir($baseAuditPath, 0777, true);
    }

    $deptPath = $baseAuditPath . '/' . $deptSlug;
    if (!is_dir($deptPath)) {
        mkdir($deptPath, 0777, true);
    }

    return $deptPath . '/audit.jsonl';
}

/**
 * Append an audit entry for the department.
 */
function yojaka_audit_log(array $entry): void
{
    try {
        if (empty($entry['department_slug'])) {
            $current = function_exists('yojaka_current_user') ? yojaka_current_user() : null;
            if ($current) {
                $entry['department_slug'] = $current['department_slug'] ?? null;
            }
        }

        if (empty($entry['department_slug'])) {
            error_log('Audit log skipped: missing department_slug');
            return;
        }

        if (empty($entry['timestamp'])) {
            $entry['timestamp'] = date('c');
        }

        $filePath = yojaka_audit_file_path($entry['department_slug']);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            error_log('Audit log encoding failed');
            return;
        }

        $result = file_put_contents($filePath, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($result === false) {
            error_log('Audit log write failed for ' . $filePath);
        }
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * Convenience wrapper to record an action entry.
 */
function yojaka_audit_log_action(string $deptSlug, string $module, ?string $recordId, string $actionType, string $actionLabel, array $details = []): void
{
    $current = function_exists('yojaka_current_user') ? yojaka_current_user() : null;
    $actor = $current['login_identity'] ?? ($current['username'] ?? null);

    $entry = [
        'timestamp' => null,
        'department_slug' => $deptSlug,
        'actor_username' => $actor,
        'module' => $module,
        'record_id' => $recordId,
        'action_type' => $actionType,
        'action_label' => $actionLabel,
        'details' => $details,
    ];

    yojaka_audit_log($entry);
}

/**
 * Load recent audit entries for a department (most recent first).
 */
function yojaka_audit_load_recent(string $deptSlug, int $limit = 100): array
{
    $filePath = yojaka_audit_file_path($deptSlug);
    if (!file_exists($filePath)) {
        return [];
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $sliced = array_slice($lines, -$limit);
    $entries = [];
    foreach ($sliced as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $entries[] = $decoded;
        }
    }

    return array_reverse($entries);
}

/**
 * Load audit entries for a specific record within a module.
 */
function yojaka_audit_load_for_record(string $deptSlug, string $module, string $recordId, int $limit = 100): array
{
    $filePath = yojaka_audit_file_path($deptSlug);
    if (!file_exists($filePath)) {
        return [];
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $matches = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        if (($decoded['module'] ?? '') === $module && ($decoded['record_id'] ?? '') === $recordId) {
            $matches[] = $decoded;
        }
    }

    usort($matches, function ($a, $b) {
        $ta = $a['timestamp'] ?? '';
        $tb = $b['timestamp'] ?? '';
        if ($ta === $tb) {
            return 0;
        }
        return $ta < $tb ? -1 : 1;
    });

    return array_slice($matches, -$limit);
}

/**
 * Build a combined timeline using workflow history and audit entries.
 */
function yojaka_record_timeline(array $record, array $auditEntries): array
{
    $timeline = [];
    $history = $record['workflow']['history'] ?? [];

    foreach ($history as $item) {
        $timeline[] = [
            'timestamp' => $item['timestamp'] ?? '',
            'source' => 'workflow',
            'type' => $item['action'] ?? '',
            'label' => ucfirst($item['action'] ?? 'workflow'),
            'from_step' => $item['from_step'] ?? null,
            'to_step' => $item['to_step'] ?? null,
            'actor' => $item['actor_user'] ?? null,
            'to_user' => $item['to_user'] ?? ($item['external_actor'] ?? null),
            'comment' => $item['comment'] ?? '',
            'raw' => $item,
        ];
    }

    foreach ($auditEntries as $entry) {
        $details = $entry['details'] ?? [];
        $timeline[] = [
            'timestamp' => $entry['timestamp'] ?? '',
            'source' => 'audit',
            'type' => $entry['action_type'] ?? '',
            'label' => $entry['action_label'] ?? ($entry['action_type'] ?? 'audit'),
            'from_step' => $details['workflow_step_from'] ?? ($details['from_step'] ?? null),
            'to_step' => $details['workflow_step_to'] ?? ($details['to_step'] ?? null),
            'actor' => $entry['actor_username'] ?? null,
            'to_user' => $details['to_user'] ?? null,
            'comment' => $details['comment'] ?? ($details['status'] ?? ''),
            'raw' => $entry,
        ];
    }

    usort($timeline, function ($a, $b) {
        $ta = $a['timestamp'] ?? '';
        $tb = $b['timestamp'] ?? '';
        if ($ta === $tb) {
            return 0;
        }
        return $ta < $tb ? -1 : 1;
    });

    return $timeline;
}
