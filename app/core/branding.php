<?php
// Branding helpers for department letterhead configuration.

function yojaka_branding_base_path(string $deptSlug): string
{
    $base = rtrim(yojaka_config('paths.data_path'), '/') . '/departments/' . $deptSlug . '/branding';
    if (!is_dir($base)) {
        mkdir($base, 0777, true);
    }

    return $base;
}

function yojaka_branding_letterhead_path(string $deptSlug): string
{
    return yojaka_branding_base_path($deptSlug) . '/letterhead.json';
}

function yojaka_branding_assets_dir(string $deptSlug): string
{
    $dir = yojaka_branding_base_path($deptSlug) . '/assets';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function yojaka_branding_logo_path(string $deptSlug, string $filename): ?string
{
    if ($filename === '' || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return null;
    }

    $sanitized = yojaka_attachment_sanitize_name($filename);
    if ($sanitized === '' || $sanitized !== $filename) {
        return null;
    }

    $path = yojaka_branding_assets_dir($deptSlug) . '/' . $sanitized;
    return file_exists($path) ? $path : null;
}

function yojaka_branding_logo_data_uri(string $deptSlug, ?string $filename): ?string
{
    if ($filename === null || $filename === '') {
        return null;
    }

    $path = yojaka_branding_logo_path($deptSlug, $filename);
    if ($path === null) {
        return null;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return null;
    }

    $mime = 'image/png';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $path);
            if ($detected) {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function yojaka_branding_letterhead_defaults(): array
{
    return [
        'department_name' => '',
        'department_address' => '',
        'logo_file' => null,
        'header_html' => '',
        'footer_html' => '',
    ];
}

function yojaka_branding_load_letterhead(string $deptSlug): array
{
    $path = yojaka_branding_letterhead_path($deptSlug);
    if (!file_exists($path)) {
        return yojaka_branding_letterhead_defaults();
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return yojaka_branding_letterhead_defaults();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return yojaka_branding_letterhead_defaults();
    }

    return array_merge(yojaka_branding_letterhead_defaults(), $decoded);
}

function yojaka_branding_save_letterhead(string $deptSlug, array $config): void
{
    $path = yojaka_branding_letterhead_path($deptSlug);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $defaults = yojaka_branding_letterhead_defaults();
    $config = array_merge($defaults, $config);

    file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);
}
