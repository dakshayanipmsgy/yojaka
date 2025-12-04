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
    return array_values(array_filter($posts, static function ($post) use ($officeId) {
        return empty($post['office_id']) || ($post['office_id'] === $officeId);
    }));
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
        $filtered[] = $post;
    }
    return org_save_json(org_posts_file(), $filtered);
}

function load_positions(string $officeId): array
{
    $positions = org_load_json(org_positions_file());
    return array_values(array_filter($positions, static function ($pos) use ($officeId) {
        return ($pos['office_id'] ?? null) === $officeId;
    }));
}

function save_positions(string $officeId, array $positions): bool
{
    $existing = org_load_json(org_positions_file());
    $filtered = array_values(array_filter($existing, static function ($pos) use ($officeId) {
        return ($pos['office_id'] ?? null) !== $officeId;
    }));
    foreach ($positions as $pos) {
        $pos['office_id'] = $officeId;
        $filtered[] = $pos;
    }
    return org_save_json(org_positions_file(), $filtered);
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
    foreach (load_positions($officeId) as $pos) {
        if (($pos['id'] ?? '') === $positionId) {
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
