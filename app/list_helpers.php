<?php
// Helper functions for listing, filtering and pagination (v0.8)

/**
 * Paginate an array of items.
 */
function paginate_array(array $items, int $page, int $per_page): array
{
    $total = count($items);
    $per_page = max(1, $per_page);
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    $pagedItems = array_slice($items, $offset, $per_page);

    return [
        'items' => $pagedItems,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
    ];
}

/**
 * Read a page number from GET query parameters.
 */
function get_page_param(string $param_name = 'page'): int
{
    $value = isset($_GET[$param_name]) ? (int) $_GET[$param_name] : 1;
    return $value > 0 ? $value : 1;
}

/**
 * Simple case-insensitive substring search across selected fields.
 */
function filter_items_search(array $items, string $search_term, array $fields): array
{
    $search_term = trim($search_term);
    if ($search_term === '') {
        return $items;
    }

    $search_term = mb_strtolower($search_term);
    $filtered = [];
    foreach ($items as $item) {
        foreach ($fields as $field) {
            $value = $item[$field] ?? '';
            if (!is_scalar($value)) {
                continue;
            }
            if (mb_stripos((string) $value, $search_term) !== false) {
                $filtered[] = $item;
                break;
            }
        }
    }

    return $filtered;
}
