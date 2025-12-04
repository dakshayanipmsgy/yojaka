<?php
// License and trial control helpers

function license_directory(): string
{
    global $config;
    $dir = $config['offices_data_path'] ?? (YOJAKA_DATA_PATH . '/offices');
    if (!$dir) {
        $dir = YOJAKA_DATA_PATH . '/offices';
    }
    return rtrim($dir, DIRECTORY_SEPARATOR);
}

function license_file_path(string $officeId): string
{
    $registry = load_offices_registry();
    foreach ($registry as $office) {
        if (($office['id'] ?? '') === $officeId) {
            $licenseFile = $office['license_file'] ?? ('license_' . $officeId . '.json');
            return license_directory() . DIRECTORY_SEPARATOR . $licenseFile;
        }
    }
    return license_directory() . DIRECTORY_SEPARATOR . ('license_' . $officeId . '.json');
}

function calculate_license_checksum(array $license): string
{
    $key = $license['license_key'] ?? '';
    $officeId = $license['office_id'] ?? '';
    $issue = $license['issue_date'] ?? '';
    return md5($key . '|' . $officeId . '|' . $issue);
}

function default_trial_license(string $officeId, string $officeName): array
{
    global $config;
    $days = (int) ($config['license_default_trial_days'] ?? 30);
    $issue = date('Y-m-d');
    $expiry = (new DateTime($issue))->modify('+' . $days . ' days')->format('Y-m-d');
    $trial = [
        'license_key' => 'YOJAKA-TRIAL-' . strtoupper(substr(md5($officeId . $issue), 0, 6)),
        'licensed_to' => $officeName,
        'office_id' => $officeId,
        'issue_date' => $issue,
        'expiry_date' => $expiry,
        'type' => 'trial',
        'max_users' => 50,
        'max_storage_mb' => 1024,
        'features' => [
            'enable_backup' => true,
            'enable_mis' => true,
            'enable_attachments' => true,
            'enable_workflow' => true,
        ],
        'watermark_text' => 'TRIAL COPY - NOT FOR PRODUCTION',
        'notes' => 'Default offline trial license.',
    ];
    $trial['checksum'] = calculate_license_checksum($trial);
    return $trial;
}

function save_office_license(array $license, string $officeId): void
{
    ensure_office_storage();
    $path = license_file_path($officeId);
    $license['office_id'] = $officeId;
    $license['checksum'] = calculate_license_checksum($license);
    $handle = fopen($path, 'c+');
    if (!$handle) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function load_office_license(string $officeId): array
{
    ensure_office_storage();
    $path = license_file_path($officeId);
    if (!file_exists($path)) {
        $office = load_office_config_by_id($officeId);
        $license = default_trial_license($officeId, $office['office_name'] ?? $officeId);
        save_office_license($license, $officeId);
        return $license;
    }
    $data = json_decode((string) file_get_contents($path), true);
    $license = is_array($data) ? $data : default_trial_license($officeId, $officeId);
    $expected = calculate_license_checksum($license);
    if (($license['checksum'] ?? '') !== $expected) {
        $license['type'] = 'invalid';
    }
    return $license;
}

function is_license_expired(array $license): bool
{
    if (empty($license['expiry_date'])) {
        return false;
    }
    $today = (new DateTime('today'))->format('Y-m-d');
    return $today > $license['expiry_date'];
}

function is_license_trial(array $license): bool
{
    return ($license['type'] ?? '') === 'trial';
}

function office_is_read_only(?array $license = null): bool
{
    $license = $license ?? get_current_office_license();
    if (!$license || ($license['type'] ?? '') === 'invalid') {
        return true;
    }
    if (is_license_expired($license)) {
        return true;
    }
    return false;
}

function license_feature_enabled(?array $license, string $featureKey, bool $default = true): bool
{
    if (!$license) {
        return $default;
    }
    if (is_license_expired($license)) {
        return false;
    }
    return (bool) ($license['features'][$featureKey] ?? $default);
}

function get_current_office_license(): ?array
{
    return $GLOBALS['current_office_license'] ?? null;
}

function mask_license_key(string $key): string
{
    if (strlen($key) <= 8) {
        return $key;
    }
    return substr($key, 0, 4) . '***' . substr($key, -4);
}

