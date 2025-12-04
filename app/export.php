<?php
// Case bundle export helper

function export_case_bundle(string $module, string $entityId): void
{
    require_permission('admin_backup');
    $currentLicense = get_current_office_license();
    if (office_is_read_only($currentLicense)) {
        throw new RuntimeException('Read-only mode; export not allowed.');
    }

    switch ($module) {
        case 'rti':
            $items = load_rti_cases();
            $entity = find_rti_by_id($items, $entityId);
            break;
        case 'dak':
            $items = load_dak_entries();
            $entity = null;
            foreach ($items as $entry) {
                if (($entry['id'] ?? '') === $entityId) {
                    $entity = $entry;
                    break;
                }
            }
            break;
        case 'bills':
            $items = load_bills();
            $entity = find_bill_by_id($items, $entityId);
            break;
        default:
            throw new RuntimeException('Unsupported module for export');
    }

    if (!$entity) {
        throw new RuntimeException('Record not found');
    }

    $tmpDir = rtrim(YOJAKA_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'tmp_case_' . $module . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $entityId) . '_' . time();
    @mkdir($tmpDir, 0755, true);
    $casePath = $tmpDir . DIRECTORY_SEPARATOR . 'case.json';
    file_put_contents($casePath, json_encode($entity, JSON_PRETTY_PRINT));

    $attachmentsDir = $tmpDir . DIRECTORY_SEPARATOR . 'attachments';
    @mkdir($attachmentsDir, 0755, true);
    $attachments = find_attachments_for_entity($module, $entityId);
    foreach ($attachments as $att) {
        $source = attachment_file_path($att['path'] ?? '');
        if ($source && file_exists($source)) {
            copy($source, $attachmentsDir . DIRECTORY_SEPARATOR . basename($source));
        }
    }

    $readme = "Yojaka Case Bundle Export\nModule: {$module}\nID: {$entityId}\nExported at: " . gmdate('c') . "\n";
    file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'readme.txt', $readme);

    $zipName = 'yojaka_' . $module . '_' . $entityId . '_case_bundle_' . date('Ymd_His') . '.zip';
    $zipPath = $tmpDir . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Unable to create ZIP');
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $localName = substr($filePath, strlen($tmpDir) + 1);
        $zip->addFile($filePath, $localName);
    }
    $zip->close();

    log_event('case_bundle_exported', current_user()['username'] ?? null, ['module' => $module, 'id' => $entityId]);
    write_audit_log($module, $entityId, 'export_case_bundle');

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);

    rrmdir($tmpDir);
    @unlink($zipPath);
    exit;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getRealPath());
        } else {
            @unlink($item->getRealPath());
        }
    }
    @rmdir($dir);
}
