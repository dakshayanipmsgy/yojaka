<?php
require_once __DIR__ . '/../lib/qr/phpqrcode.php';

function qr_storage_dir(): string
{
    return YOJAKA_DATA_PATH . '/qr';
}

function ensure_qr_storage(): void
{
    $dir = qr_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

function qr_generate_id(string $id): string
{
    ensure_qr_storage();
    $filename = qr_storage_dir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $id) . '.png';
    SimpleQR::png($id, $filename, 6);
    return $filename;
}
