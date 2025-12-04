<?php
// Logging utilities for Yojaka v0.2

function usage_log_path(): string
{
    global $config;
    $logFile = $config['usage_log_file'] ?? 'usage.log';
    return rtrim(YOJAKA_LOGS_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $logFile;
}

function ensure_logs_directory(): void
{
    if (!is_dir(YOJAKA_LOGS_PATH)) {
        @mkdir(YOJAKA_LOGS_PATH, 0755, true);
    }
}

function get_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function get_user_agent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

function log_event(string $event, ?string $username = null, array $details = []): bool
{
    try {
        ensure_logs_directory();
        $path = usage_log_path();
        $entry = [
            'timestamp' => gmdate('c'),
            'event' => $event,
            'username' => $username,
            'ip' => get_client_ip(),
            'user_agent' => get_user_agent(),
            'details' => !empty($details) ? $details : new stdClass(),
        ];

        $jsonLine = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        $handle = @fopen($path, 'a');
        if (!$handle) {
            return false;
        }

        if (flock($handle, LOCK_EX)) {
            fwrite($handle, $jsonLine);
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function read_usage_logs(int $maxEntries = 1000): array
{
    $path = usage_log_path();
    if (!file_exists($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $lines = array_slice($lines, -$maxEntries);
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $entries[] = $decoded;
        }
    }

    return $entries;
}

function count_events(array $entries, string $event, ?string $username = null): int
{
    $count = 0;
    foreach ($entries as $entry) {
        if (($entry['event'] ?? '') === $event) {
            if ($username === null || ($entry['username'] ?? null) === $username) {
                $count++;
            }
        }
    }
    return $count;
}
