<?php
// Public portal rate limiting helpers

function portal_rate_limit_directory(): string
{
    return YOJAKA_DATA_PATH . '/portal';
}

function portal_rate_limit_file(string $ip): string
{
    $safeIp = preg_replace('/[^a-zA-Z0-9_\.:-]/', '_', $ip);
    return portal_rate_limit_directory() . '/rate_' . $safeIp . '.json';
}

function portal_rate_limit_check(string $ip, string $actionKey, int $limit = 30, int $windowSeconds = 600): bool
{
    $path = portal_rate_limit_file($ip);
    $now = time();
    if (!file_exists($path)) {
        return true;
    }

    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
        return true;
    }

    $timestamps = $data[$actionKey] ?? [];
    $recent = array_values(array_filter($timestamps, function ($ts) use ($now, $windowSeconds) {
        return ($now - (int) $ts) < $windowSeconds;
    }));

    return count($recent) < $limit;
}

function portal_rate_limit_record(string $ip, string $actionKey, int $windowSeconds = 600): void
{
    $dir = portal_rate_limit_directory();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $path = portal_rate_limit_file($ip);
    $now = time();
    $data = [];
    if (file_exists($path)) {
        $decoded = json_decode((string) @file_get_contents($path), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $timestamps = $data[$actionKey] ?? [];
    $timestamps[] = $now;
    $timestamps = array_values(array_filter($timestamps, function ($ts) use ($now, $windowSeconds) {
        return ($now - (int) $ts) < $windowSeconds;
    }));
    $data[$actionKey] = $timestamps;

    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return;
    }

    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_PRETTY_PRINT));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

