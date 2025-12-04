<?php

function org_data_dir(): string
{
    return YOJAKA_DATA_PATH . '/org';
}

function org_posts_file(): string
{
    return org_data_dir() . '/posts.json';
}

function org_positions_file(): string
{
    return org_data_dir() . '/hierarchy.json';
}

function org_position_history_file(): string
{
    return org_data_dir() . '/position_history.json';
}

function org_routes_file(): string
{
    return org_data_dir() . '/routes.json';
}

function org_load_json(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content ?: '[]', true);
    return is_array($data) ? $data : [];
}

function org_save_json(string $path, array $data): bool
{
    bootstrap_ensure_directory(dirname($path));
    return false !== @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function load_posts(string $officeId): array
{
    $posts = org_load_json(org_posts_file());
    $posts = array_values(array_filter($posts, static function ($post) use ($officeId) {
        return empty($post['office_id']) || ($post['office_id'] === $officeId);
    }));
    return array_map(static function ($post) {
        if (!isset($post['post_id']) && isset($post['id'])) {
            $post['post_id'] = $post['id'];
        }
        return $post;
    }, $posts);
}

function save_posts(string $officeId, array $posts): bool
{
    $existing = org_load_json(org_posts_file());
    $filtered = array_values(array_filter($existing, static function ($post) use ($officeId) {
        return !empty($post['office_id']) && $post['office_id'] !== $officeId;
    }));
    foreach ($posts as $post) {
        if (empty($post['office_id'])) {
            $post['office_id'] = $officeId;
        }
        if (empty($post['post_id']) && !empty($post['id'])) {
            $post['post_id'] = $post['id'];
        }
        $post['id'] = $post['post_id'] ?? ($post['id'] ?? null);
        $filtered[] = $post;
    }
    return org_save_json(org_posts_file(), $filtered);
}

function load_positions(string $officeId): array
{
    $positions = org_load_json(org_positions_file());
    $positions = array_values(array_filter($positions, static function ($pos) use ($officeId) {
        return ($pos['office_id'] ?? null) === $officeId;
    }));
    return array_map('standardize_position_record', $positions);
}

function save_positions(string $officeId, array $positions): bool
{
    $existing = org_load_json(org_positions_file());
    $filtered = array_values(array_filter($existing, static function ($pos) use ($officeId) {
        return ($pos['office_id'] ?? null) !== $officeId;
    }));
    foreach ($positions as $pos) {
        $pos = standardize_position_record($pos);
        $pos['office_id'] = $officeId;
        $filtered[] = $pos;
    }
    return org_save_json(org_positions_file(), $filtered);
}

function load_position_history(string $officeId): array
{
    $history = org_load_json(org_position_history_file());
    $history = array_filter($history, static function ($entry) use ($officeId) {
        return ($entry['office_id'] ?? null) === $officeId || empty($entry['office_id']);
    });
    return array_values(array_map(static function ($entry) use ($officeId) {
        $entry['office_id'] = $entry['office_id'] ?? $officeId;
        return $entry;
    }, $history));
}

function save_position_history(string $officeId, array $history): bool
{
    $existing = org_load_json(org_position_history_file());
    $filtered = array_values(array_filter($existing, static function ($entry) use ($officeId) {
        return ($entry['office_id'] ?? null) !== $officeId;
    }));
    foreach ($history as $entry) {
        $entry['office_id'] = $officeId;
        $filtered[] = $entry;
    }
    return org_save_json(org_position_history_file(), $filtered);
}

function load_routes(string $officeId): array
{
    $routes = org_load_json(org_routes_file());
    return array_values(array_filter($routes, static function ($route) use ($officeId) {
        return ($route['office_id'] ?? null) === $officeId;
    }));
}

function save_routes(string $officeId, array $routes): bool
{
    $existing = org_load_json(org_routes_file());
    $filtered = array_values(array_filter($existing, static function ($route) use ($officeId) {
        return ($route['office_id'] ?? null) !== $officeId;
    }));
    foreach ($routes as $route) {
        $route['office_id'] = $officeId;
        $filtered[] = $route;
    }
    return org_save_json(org_routes_file(), $filtered);
}

function get_position_by_id(string $officeId, string $positionId)
{
    return find_position($officeId, $positionId);
}

function find_position(string $officeId, string $positionId): ?array
{
    foreach (load_positions($officeId) as $pos) {
        if (($pos['position_id'] ?? $pos['id'] ?? '') === $positionId) {
            return $pos;
        }
    }
    return null;
}

function get_route_by_id(string $officeId, string $routeId)
{
    foreach (load_routes($officeId) as $route) {
        if (($route['id'] ?? '') === $routeId) {
            return $route;
        }
    }
    return null;
}

function get_user_position(string $username, string $officeId): ?array
{
    foreach (load_positions($officeId) as $pos) {
        if (isset($pos['user_username']) && strtolower((string) $pos['user_username']) === strtolower($username)) {
            return $pos;
        }
    }
    return null;
}

function get_default_route_for_file_type(string $officeId, string $fileType, ?string $departmentId = null)
{
    $routes = load_routes($officeId);
    foreach ($routes as $route) {
        if (($route['file_type'] ?? '') === $fileType && (!empty($route['active']))) {
            if ($departmentId === null || ($route['department_id'] ?? null) === $departmentId) {
                return $route;
            }
        }
    }
    return null;
}

function generate_position_id(array $positions): string
{
    $max = 0;
    foreach ($positions as $pos) {
        if (isset($pos['id']) && preg_match('/POS-(\d+)/', (string) $pos['id'], $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('POS-%03d', $max + 1);
}

function standardize_position_record(array $pos): array
{
    if (!isset($pos['position_id']) && isset($pos['id'])) {
        $pos['position_id'] = $pos['id'];
    }
    $pos['id'] = $pos['position_id'] ?? ($pos['id'] ?? null);
    $pos['current_staff_id'] = $pos['current_staff_id'] ?? ($pos['staff_id'] ?? null);
    $pos['current_username'] = $pos['current_username'] ?? ($pos['user_username'] ?? null);
    $pos['active'] = array_key_exists('active', $pos) ? (bool) $pos['active'] : true;
    return $pos;
}

function assign_staff_to_position(string $officeId, string $positionId, ?string $staffId, ?string $username, string $fromDateTime): bool
{
    $positions = load_positions($officeId);
    $history = load_position_history($officeId);
    $positionFound = false;
    $now = $fromDateTime !== '' ? $fromDateTime : gmdate('c');

    foreach ($positions as &$pos) {
        if (($pos['position_id'] ?? $pos['id']) === $positionId) {
            $positionFound = true;
            $pos['current_staff_id'] = $staffId;
            $pos['current_username'] = $username;
            $pos['user_username'] = $pos['current_username'];
            break;
        }
    }
    unset($pos);

    if (!$positionFound) {
        return false;
    }

    foreach ($history as &$entry) {
        if (($entry['position_id'] ?? '') === $positionId && ($entry['to'] ?? null) === null) {
            $entry['to'] = $now;
        }
    }
    unset($entry);

    $history[] = [
        'position_id' => $positionId,
        'office_id' => $officeId,
        'staff_id' => $staffId,
        'username' => $username,
        'from' => $now,
        'to' => null,
    ];

    $successPositions = save_positions($officeId, $positions);
    $successHistory = save_position_history($officeId, $history);

    if (function_exists('log_event')) {
        log_event('position_assigned', current_user()['username'] ?? null, [
            'position_id' => $positionId,
            'staff_id' => $staffId,
            'username' => $username,
            'from' => $now,
        ]);
    }

    return $successPositions && $successHistory;
}

function generate_route_id(array $routes): string
{
    $max = 0;
    foreach ($routes as $route) {
        if (isset($route['id']) && preg_match('/ROUTE-(\d+)/', (string) $route['id'], $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('ROUTE-%03d', $max + 1);
}

function sort_route_nodes(array $nodes): array
{
    usort($nodes, static function ($a, $b) {
        return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    });
    return $nodes;
}
