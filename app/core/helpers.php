<?php
// General helper functions for Yojaka.

function yojaka_url(string $path = ''): string
{
    $base = rtrim(yojaka_config('base_url', ''), '/');
    $trimmedPath = ltrim($path, '/');

    if ($trimmedPath === '') {
        return ($base === '' ? '' : $base) . '/';
    }

    if ($base === '') {
        return '/' . $trimmedPath;
    }

    return $base . '/' . $trimmedPath;
}

function yojaka_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
