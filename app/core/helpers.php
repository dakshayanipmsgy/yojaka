<?php
// General helper functions for Yojaka.

function yojaka_url(string $path = ''): string
{
    $normalizedBase = BASE_URL;
    $trimmedPath = ltrim($path, '/');

    if ($normalizedBase === '') {
        return '/' . $trimmedPath;
    }

    return rtrim($normalizedBase, '/') . '/' . $trimmedPath;
}

function yojaka_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
