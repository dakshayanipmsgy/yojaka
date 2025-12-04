<?php
// Predictive routing helpers

function route_stats_path(): string
{
    return YOJAKA_DATA_PATH . '/analytics/route_stats.json';
}

function load_route_stats(): array
{
    $path = route_stats_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_route_stats(array $stats): bool
{
    $path = route_stats_path();
    bootstrap_ensure_directory(dirname($path));
    return (bool) file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function update_route_stats_from_file(array $file): void
{
    $stats = load_route_stats();
    $officeId = $file['office_id'] ?? 'default';
    $fileType = $file['category'] ?? ($file['type'] ?? 'general');
    $history = $file['route']['history'] ?? [];
    if (!isset($stats[$officeId][$fileType])) {
        $stats[$officeId][$fileType] = [
            'position_counts' => [],
            'position_transitions' => [],
            'avg_durations_hours' => [],
        ];
    }
    foreach ($history as $idx => $row) {
        $to = $row['to_position_id'] ?? null;
        $from = $row['from_position_id'] ?? null;
        if ($to) {
            $stats[$officeId][$fileType]['position_counts'][$to] = ($stats[$officeId][$fileType]['position_counts'][$to] ?? 0) + 1;
        }
        if ($from && $to) {
            $key = $from . '=>' . $to;
            $stats[$officeId][$fileType]['position_transitions'][$key] = ($stats[$officeId][$fileType]['position_transitions'][$key] ?? 0) + 1;
        }
        if ($to && !empty($row['duration_hours'])) {
            $prev = $stats[$officeId][$fileType]['avg_durations_hours'][$to] ?? $row['duration_hours'];
            $stats[$officeId][$fileType]['avg_durations_hours'][$to] = ($prev + $row['duration_hours']) / 2;
        }
    }
    save_route_stats($stats);
}

function predict_next_positions(array $file, int $topN = 3): array
{
    $stats = load_route_stats();
    $officeId = $file['office_id'] ?? 'default';
    $fileType = $file['category'] ?? ($file['type'] ?? 'general');
    $currentPosition = $file['route']['nodes'][$file['route']['current_node_index'] ?? 0]['position_id'] ?? null;
    $candidates = [];
    $transitions = $stats[$officeId][$fileType]['position_transitions'] ?? [];
    foreach ($transitions as $key => $count) {
        [$from, $to] = explode('=>', $key) + [null, null];
        if ($from === $currentPosition && $to) {
            $candidates[$to] = ($candidates[$to] ?? 0) + $count;
        }
    }
    arsort($candidates);
    $results = [];
    foreach (array_slice($candidates, 0, $topN, true) as $posId => $score) {
        $results[] = [
            'position_id' => $posId,
            'score' => $score,
            'label' => $score > 5 ? 'Highly likely' : 'Likely',
        ];
    }
    if (empty($results) && !empty($file['route']['nodes'])) {
        foreach ($file['route']['nodes'] as $idx => $node) {
            if ($idx > ($file['route']['current_node_index'] ?? 0)) {
                $results[] = ['position_id' => $node['position_id'] ?? '', 'score' => 1, 'label' => 'From template'];
            }
        }
    }
    if (ai_is_enabled()) {
        $context = [
            'module' => 'dak',
            'office_name' => $file['office_name'] ?? '',
            'subject' => $file['subject'] ?? '',
            'current_position' => $currentPosition,
            'candidate_positions' => $results,
        ];
        $prompt = 'Suggest next positions for file movement: ' . ($file['subject'] ?? '');
        $aiResponse = ai_generate_text('suggest_route', $prompt, $context);
        if ($aiResponse) {
            $results[] = ['position_id' => $aiResponse, 'score' => 0.5, 'label' => 'AI hint'];
        }
    }
    return $results;
}
