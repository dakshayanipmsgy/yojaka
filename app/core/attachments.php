<?php
// Attachment utilities for secure file handling.

function yojaka_attachment_base_path(string $deptSlug, string $module, string $recordId): string
{
    $safeModule = in_array($module, ['dak', 'letters'], true) ? $module : 'unknown';
    $safeRecord = preg_replace('/[^A-Za-z0-9_\-]/', '', $recordId);

    return rtrim(yojaka_config('paths.data_path'), '/')
        . '/departments/' . $deptSlug
        . '/attachments/' . $safeModule
        . '/' . $safeRecord;
}

function yojaka_attachment_ensure_folder(string $deptSlug, string $module, string $recordId): void
{
    $path = yojaka_attachment_base_path($deptSlug, $module, $recordId);
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function yojaka_attachment_sanitize_name(string $filename): string
{
    $name = strtolower($filename);
    $name = str_replace(' ', '_', $name);
    $name = preg_replace('/[^a-z0-9_.\-]/i', '', $name);
    $name = preg_replace('/_{2,}/', '_', $name);
    $name = ltrim($name, '.');

    return $name ?? '';
}

function yojaka_attachment_is_allowed(string $filename, string $mime, int $size): bool
{
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeAllowed = false;

    if (strpos($mime, 'image/') === 0) {
        $mimeAllowed = true;
    } elseif (in_array($mime, $allowedMimes, true)) {
        $mimeAllowed = true;
    }

    $sizeAllowed = $size > 0 && $size <= (10 * 1024 * 1024);

    return in_array($extension, $allowedExtensions, true) && $mimeAllowed && $sizeAllowed;
}

function yojaka_attachment_save_uploaded(string $deptSlug, string $module, string $recordId, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $originalName = $file['name'] ?? '';
    $tmpName = $file['tmp_name'] ?? '';
    $size = (int) ($file['size'] ?? 0);

    if ($originalName === '' || !is_uploaded_file($tmpName)) {
        return null;
    }

    $sanitized = yojaka_attachment_sanitize_name($originalName);
    if ($sanitized === '') {
        return null;
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $detectedMime = $finfo ? finfo_file($finfo, $tmpName) : ($file['type'] ?? '');
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!yojaka_attachment_is_allowed($sanitized, (string) $detectedMime, $size)) {
        return null;
    }

    yojaka_attachment_ensure_folder($deptSlug, $module, $recordId);

    $timestamp = date('Ymd_His');
    $uniqueName = $timestamp . '_' . $sanitized;
    $targetPath = yojaka_attachment_base_path($deptSlug, $module, $recordId) . '/' . $uniqueName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return null;
    }

    return $uniqueName;
}

function yojaka_attachment_delete(string $deptSlug, string $module, string $recordId, string $filename): bool
{
    $path = yojaka_attachment_get_path($deptSlug, $module, $recordId, $filename);
    if ($path === null) {
        return false;
    }

    return file_exists($path) ? unlink($path) : false;
}

function yojaka_attachment_get_path(string $deptSlug, string $module, string $recordId, string $filename): ?string
{
    if ($filename === '' || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return null;
    }

    $sanitized = yojaka_attachment_sanitize_name($filename);
    if ($sanitized === '' || $sanitized !== $filename) {
        return null;
    }

    $base = yojaka_attachment_base_path($deptSlug, $module, $recordId);
    $path = $base . '/' . $sanitized;

    return file_exists($path) ? $path : null;
}
