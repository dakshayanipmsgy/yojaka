<?php
// Office / instance configuration helpers

function office_directory(): string
{
    global $config;
    $dir = $config['office_data_path'] ?? (YOJAKA_DATA_PATH . '/office');
    return rtrim($dir, DIRECTORY_SEPARATOR);
}

function office_config_path(): string
{
    global $config;
    $file = $config['office_config_file'] ?? 'office.json';
    return office_directory() . DIRECTORY_SEPARATOR . $file;
}

function default_office_config(): array
{
    return [
        'office_name' => 'Yojaka Office',
        'office_short_name' => 'YOJAKA',
        'base_url' => YOJAKA_BASE_URL,
        'date_format_php' => 'd-m-Y',
        'timezone' => 'Asia/Kolkata',
        'id_prefixes' => [
            'rti' => 'RTI',
            'dak' => 'DAK',
            'inspection' => 'INSP',
            'bill' => 'BILL',
            'meeting_minutes' => 'MM',
            'work_order' => 'WO',
            'guc' => 'GUC',
        ],
        'theme' => [
            'primary_color' => '#0f5aa5',
            'secondary_color' => '#f5f7fb',
            'logo_path' => '',
        ],
        'modules' => [
            'enable_rti' => true,
            'enable_dak' => true,
            'enable_inspection' => true,
            'enable_bills' => true,
            'enable_meeting_minutes' => true,
            'enable_work_orders' => true,
            'enable_guc' => true,
        ],
    ];
}

function ensure_office_storage(): void
{
    $dir = office_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }
    $path = office_config_path();
    if (!file_exists($path)) {
        save_office_config(default_office_config());
    }
}

function load_office_config(): array
{
    ensure_office_storage();
    $path = office_config_path();
    if (!file_exists($path)) {
        return default_office_config();
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return default_office_config();
    }
    return array_replace_recursive(default_office_config(), $data);
}

function save_office_config(array $office): bool
{
    ensure_office_storage();
    $path = office_config_path();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($office, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    return true;
}

function get_id_prefix(string $type, string $fallback = ''): string
{
    $office = load_office_config();
    return $office['id_prefixes'][$type] ?? ($fallback !== '' ? $fallback : strtoupper($type));
}

function format_date_for_display(?string $dateString): string
{
    if (!$dateString) {
        return '';
    }
    $office = load_office_config();
    $format = $office['date_format_php'] ?? 'd-m-Y';
    $timezone = $office['timezone'] ?? date_default_timezone_get();
    try {
        $date = new DateTime($dateString, new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Throwable $e) {
        return $dateString;
    }
}

function is_module_enabled(string $moduleKey): bool
{
    $office = load_office_config();
    $flagKey = 'enable_' . $moduleKey;
    return !empty($office['modules'][$flagKey]);
}

function require_module_enabled(string $moduleKey): void
{
    if (!is_module_enabled($moduleKey)) {
        echo '<div class="alert alert-danger">This module is disabled for this office instance.</div>';
        exit;
    }
}

?>
