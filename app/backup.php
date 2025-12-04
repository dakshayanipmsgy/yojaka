<?php
// Backup utilities for Yojaka v0.8

function ensure_backup_directory_exists(): void
{
    global $config;
    $backupPath = $config['backup_path'] ?? (YOJAKA_ROOT . '/backup');
    if (!is_dir($backupPath)) {
        @mkdir($backupPath, 0755, true);
    }
}

function create_yogaka_backup_zip(string $label = ''): ?string
{
    global $config;

    if (!class_exists('ZipArchive')) {
        return null;
    }

    $backupPath = $config['backup_path'] ?? (YOJAKA_ROOT . '/backup');
    ensure_backup_directory_exists();

    $timestamp = gmdate('Ymd_His');
    $sanitizedLabel = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $label));
    $filename = 'yojaka_backup_' . $timestamp;
    if ($sanitizedLabel !== '') {
        $filename .= '_' . $sanitizedLabel;
    }
    $filename .= '.zip';

    $zipFullPath = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    $zip = new ZipArchive();
    if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }

    // Include data directory recursively
    if (!empty($config['backup_include_data'])) {
        $dataPath = rtrim(YOJAKA_DATA_PATH, DIRECTORY_SEPARATOR);
        add_directory_to_zip($zip, $dataPath, 'data');
    }

    // Include configuration file if allowed
    if (!empty($config['backup_include_config'])) {
        $configFile = __DIR__ . '/../config/config.php';
        if (is_readable($configFile)) {
            $zip->addFile($configFile, 'config/config.php');
        }
    }

    $zip->close();
    return $zipFullPath;
}

function add_directory_to_zip(ZipArchive $zip, string $directory, string $relativePath = ''): void
{
    $dirIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($dirIterator as $fileInfo) {
        $fullPath = $fileInfo->getPathname();
        $relative = ltrim(str_replace($directory, '', $fullPath), DIRECTORY_SEPARATOR);
        $zipPath = $relativePath !== '' ? $relativePath . '/' . $relative : $relative;

        if ($fileInfo->isDir()) {
            $zip->addEmptyDir($zipPath);
        } else {
            $zip->addFile($fullPath, $zipPath);
        }
    }
}
