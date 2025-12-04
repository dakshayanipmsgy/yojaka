<?php
// Office / instance configuration helpers

function offices_data_directory(): string
{
    global $config;
    $dir = $config['offices_data_path'] ?? (YOJAKA_DATA_PATH . '/offices');
    if (!$dir) {
        $dir = YOJAKA_DATA_PATH . '/offices';
    }
    return rtrim($dir, DIRECTORY_SEPARATOR);
}

function legacy_office_directory(): string
{
    global $config;
    $dir = $config['office_data_path'] ?? (YOJAKA_DATA_PATH . '/office');
    return rtrim($dir, DIRECTORY_SEPARATOR);
}

function legacy_office_config_path(): string
{
    global $config;
    $file = $config['office_config_file'] ?? 'office.json';
    return legacy_office_directory() . DIRECTORY_SEPARATOR . $file;
}

function offices_registry_path(): string
{
    return offices_data_directory() . DIRECTORY_SEPARATOR . 'offices.json';
}

function office_config_path_by_file(string $file): string
{
    return offices_data_directory() . DIRECTORY_SEPARATOR . $file;
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
    $dir = offices_data_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }
    $registryPath = offices_registry_path();
    if (!file_exists($registryPath)) {
        $legacyPath = legacy_office_config_path();
        $legacyConfig = file_exists($legacyPath) ? json_decode((string) file_get_contents($legacyPath), true) : [];
        $baseConfig = is_array($legacyConfig) ? array_replace_recursive(default_office_config(), $legacyConfig) : default_office_config();
        $officeId = 'office_001';
        $officeFile = $officeId . '.json';
        save_office_config_by_id($officeId, $officeFile, $baseConfig);
        $registry = [[
            'id' => $officeId,
            'name' => $baseConfig['office_name'] ?? 'Default Office',
            'short_name' => $baseConfig['office_short_name'] ?? 'YOJAKA',
            'active' => true,
            'config_file' => $officeFile,
            'license_file' => 'license_' . $officeId . '.json',
            'created_at' => gmdate('c'),
        ]];
        save_offices_registry($registry);
        $license = default_trial_license($officeId, $baseConfig['office_name'] ?? $officeId);
        save_office_license($license, $officeId);
    } else {
        $registry = load_offices_registry();
        foreach ($registry as $entry) {
            $configPath = office_config_path_by_file($entry['config_file'] ?? (($entry['id'] ?? 'office_001') . '.json'));
            if (!file_exists($configPath)) {
                save_office_config_by_id($entry['id'] ?? 'office_001', $entry['config_file'] ?? (($entry['id'] ?? 'office_001') . '.json'), default_office_config());
            }
            $licensePath = license_file_path($entry['id'] ?? 'office_001');
            if (!file_exists($licensePath)) {
                $seed = default_trial_license($entry['id'] ?? 'office_001', $entry['name'] ?? ($entry['id'] ?? 'Office'));
                save_office_license($seed, $entry['id'] ?? 'office_001');
            }
        }
    }
}

function load_offices_registry(): array
{
    ensure_office_storage();
    $registryPath = offices_registry_path();
    if (!file_exists($registryPath)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($registryPath), true);
    return is_array($data) ? $data : [];
}

function save_offices_registry(array $offices): void
{
    $path = offices_registry_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($offices), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function save_office_config_by_id(string $officeId, string $configFile, array $office): bool
{
    ensure_office_storage();
    $path = office_config_path_by_file($configFile);
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

function load_office_config_by_id(string $officeId): array
{
    $registry = load_offices_registry();
    foreach ($registry as $office) {
        if (($office['id'] ?? '') === $officeId) {
            $path = office_config_path_by_file($office['config_file'] ?? ($officeId . '.json'));
            if (!file_exists($path)) {
                save_office_config_by_id($officeId, $office['config_file'] ?? ($officeId . '.json'), default_office_config());
            }
            $data = json_decode((string) file_get_contents($path), true);
            return is_array($data) ? array_replace_recursive(default_office_config(), $data) : default_office_config();
        }
    }
    return default_office_config();
}

function load_office_config(): array
{
    return load_office_config_by_id(get_current_office_id());
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

function get_default_office_id(): string
{
    $registry = load_offices_registry();
    if (!empty($registry)) {
        return $registry[0]['id'] ?? 'office_001';
    }
    return 'office_001';
}

function get_current_office_id(): string
{
    $user = current_user();
    if ($user && !empty($user['office_id'])) {
        return $user['office_id'];
    }
    return get_default_office_id();
}

function filter_records_by_office(array $records, string $officeId): array
{
    return array_values(array_filter($records, function ($item) use ($officeId) {
        return ($item['office_id'] ?? $officeId) === $officeId;
    }));
}

function ensure_record_office(array $record, string $officeId): array
{
    if (empty($record['office_id'])) {
        $record['office_id'] = $officeId;
    }
    return $record;
}

function get_current_office_config(): array
{
    return $GLOBALS['current_office_config'] ?? load_office_config_by_id(get_current_office_id());
}


?>
