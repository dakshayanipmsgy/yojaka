<?php
// Position and assignment helpers

function positions_file_path(): string
{
    return YOJAKA_DATA_PATH . '/org/positions.json';
}

function position_assignments_file_path(): string
{
    return YOJAKA_DATA_PATH . '/org/position_assignments.json';
}

function load_positions(string $officeId): array
{
    $path = positions_file_path();
    if (!file_exists($path)) {
        // fallback to legacy hierarchy file
        $legacy = YOJAKA_DATA_PATH . '/org/hierarchy.json';
        if (file_exists($legacy)) {
            $data = json_decode((string) file_get_contents($legacy), true);
        } else {
            $data = [];
        }
    } else {
        $data = json_decode((string) file_get_contents($path), true);
    }
    if (!is_array($data)) {
        return [];
    }
    return array_values(array_filter($data, static function ($row) use ($officeId) {
        return ($row['office_id'] ?? null) === $officeId;
    }));
}

function save_positions(string $officeId, array $positions): bool
{
    $path = positions_file_path();
    $existing = [];
    if (file_exists($path)) {
        $existing = json_decode((string) file_get_contents($path), true) ?: [];
    }
    $filtered = array_values(array_filter($existing, static function ($row) use ($officeId) {
        return ($row['office_id'] ?? null) !== $officeId;
    }));

    $normalized = [];
    $now = date('c');
    foreach ($positions as $pos) {
        if (empty($pos['position_id']) && !empty($pos['id'])) {
            $pos['position_id'] = $pos['id'];
            unset($pos['id']);
        }
        if (empty($pos['position_id'])) {
            $pos['position_id'] = generate_position_identifier($filtered, $normalized);
        }
        $pos['office_id'] = $pos['office_id'] ?? $officeId;
        $pos['created_at'] = $pos['created_at'] ?? $now;
        $pos['updated_at'] = $now;
        $pos['active'] = array_key_exists('active', $pos) ? (bool) $pos['active'] : true;
        $normalized[] = $pos;
    }

    $payload = array_merge($filtered, $normalized);
    bootstrap_ensure_directory(dirname($path));
    return false !== @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function generate_position_identifier(array $existing, array $pending): string
{
    $max = 0;
    $all = array_merge($existing, $pending);
    foreach ($all as $pos) {
        $id = $pos['position_id'] ?? ($pos['id'] ?? '');
        if (preg_match('/POS-(\d+)/', (string) $id, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('POS-%03d', $max + 1);
}

function load_position_assignments(string $officeId): array
{
    $path = position_assignments_file_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    return array_values(array_filter($data, static function ($row) use ($officeId) {
        return ($row['office_id'] ?? null) === $officeId;
    }));
}

function save_position_assignments(string $officeId, array $assignments): bool
{
    $path = position_assignments_file_path();
    $existing = [];
    if (file_exists($path)) {
        $existing = json_decode((string) file_get_contents($path), true) ?: [];
    }
    $filtered = array_values(array_filter($existing, static function ($row) use ($officeId) {
        return ($row['office_id'] ?? null) !== $officeId;
    }));

    $normalized = [];
    foreach ($assignments as $entry) {
        if (empty($entry['assignment_id'])) {
            $entry['assignment_id'] = generate_assignment_identifier($filtered, $normalized);
        }
        $entry['office_id'] = $entry['office_id'] ?? $officeId;
        $entry['effective_from'] = $entry['effective_from'] ?? date('c');
        $entry['remarks'] = $entry['remarks'] ?? '';
        $normalized[] = $entry;
    }

    $payload = array_merge($filtered, $normalized);
    bootstrap_ensure_directory(dirname($path));
    return false !== @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function generate_assignment_identifier(array $existing, array $pending): string
{
    $max = 0;
    $all = array_merge($existing, $pending);
    foreach ($all as $entry) {
        $id = $entry['assignment_id'] ?? '';
        if (preg_match('/PA-(\d+)/', (string) $id, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('PA-%04d', $max + 1);
}

function get_current_assignment(string $officeId, string $positionId): ?array
{
    $assignments = load_position_assignments($officeId);
    foreach ($assignments as $assignment) {
        $pid = $assignment['position_id'] ?? '';
        $to = $assignment['effective_to'] ?? null;
        if ($pid === $positionId && $to === null) {
            return $assignment;
        }
    }
    return null;
}

function get_assignment_history(string $officeId, string $positionId): array
{
    $assignments = array_values(array_filter(load_position_assignments($officeId), static function ($a) use ($positionId) {
        return ($a['position_id'] ?? '') === $positionId;
    }));
    usort($assignments, static function ($a, $b) {
        return strcmp($a['effective_from'] ?? '', $b['effective_from'] ?? '');
    });
    return $assignments;
}

function assign_staff_to_position(string $officeId, string $positionId, string $staffId, string $assignedBy, string $remarks = ''): bool
{
    $positions = load_positions($officeId);
    $assignments = load_position_assignments($officeId);
    $now = date('c');

    foreach ($assignments as &$assignment) {
        if (($assignment['position_id'] ?? '') === $positionId && ($assignment['effective_to'] ?? null) === null) {
            $assignment['effective_to'] = $now;
        }
    }
    unset($assignment);

    $assignments[] = [
        'assignment_id' => generate_assignment_identifier($assignments, []),
        'position_id' => $positionId,
        'staff_id' => $staffId,
        'office_id' => $officeId,
        'assigned_by' => $assignedBy,
        'assigned_at' => $now,
        'effective_from' => $now,
        'effective_to' => null,
        'remarks' => $remarks,
    ];

    foreach ($positions as &$pos) {
        if (($pos['position_id'] ?? ($pos['id'] ?? '')) === $positionId) {
            $pos['position_id'] = $positionId;
            $pos['current_staff_id'] = $staffId;
            $pos['updated_at'] = $now;
        }
    }
    unset($pos);

    $posSaved = save_positions($officeId, $positions);
    $assignSaved = save_position_assignments($officeId, $assignments);
    return $posSaved && $assignSaved;
}
