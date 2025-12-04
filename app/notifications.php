<?php
// In-app notification helper functions

function notifications_path(): string
{
    global $config;
    $dir = YOJAKA_DATA_PATH . '/notifications';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/notifications.json';
}

function ensure_notifications_storage(): void
{
    $path = notifications_path();
    if (!file_exists($path)) {
        $handle = fopen($path, 'c+');
        if ($handle) {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, json_encode([]));
                fflush($handle);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
}

function load_notifications(): array
{
    $path = notifications_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function save_notifications(array $list): void
{
    $path = notifications_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($list), JSON_PRETTY_PRINT));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function generate_next_notification_id(?array $existing = null): string
{
    if ($existing === null) {
        $existing = load_notifications();
    }
    $max = 0;
    foreach ($existing as $notification) {
        if (!empty($notification['id']) && preg_match('/NTF-(\d{6})/', $notification['id'], $matches)) {
            $num = (int) $matches[1];
            if ($num > $max) {
                $max = $num;
            }
        }
    }
    $next = $max + 1;
    return sprintf('NTF-%06d', $next);
}

function create_notification(string $user, string $module, string $entity_id, string $type, string $title, string $message): void
{
    ensure_notifications_storage();
    $notifications = load_notifications();
    $newId = generate_next_notification_id($notifications);
    $entry = [
        'id' => $newId,
        'user' => $user,
        'module' => $module,
        'entity_id' => $entity_id,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'created_at' => gmdate('c'),
        'read_at' => null,
    ];
    $notifications[] = $entry;
    save_notifications($notifications);
    log_event('notification_created', $user, [
        'notification_id' => $newId,
        'type' => $type,
        'module' => $module,
        'entity_id' => $entity_id,
    ]);

    trigger_notification_email_if_enabled($user, $title, $message);
}

function trigger_notification_email_if_enabled(string $username, string $subject, string $message): void
{
    global $config;
    if (empty($config['email_notifications_enabled'])) {
        return;
    }
    $users = load_users();
    $emailTo = null;
    foreach ($users as $user) {
        if (($user['username'] ?? '') === $username) {
            $emailTo = $user['email'] ?? null;
            break;
        }
    }
    if (!$emailTo) {
        return;
    }
    $headers = 'From: ' . ($config['email_from_address'] ?? 'no-reply@example.com');
    @mail($emailTo, $subject, $message, $headers);
}

function get_unread_notifications_for_user(string $username): array
{
    $all = load_notifications();
    return array_values(array_filter($all, function ($row) use ($username) {
        return ($row['user'] ?? '') === $username && empty($row['read_at']);
    }));
}

function get_notifications_for_user(string $username): array
{
    $all = load_notifications();
    $filtered = array_values(array_filter($all, function ($row) use ($username) {
        return ($row['user'] ?? '') === $username;
    }));
    usort($filtered, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    return $filtered;
}

function mark_notification_as_read(string $id, string $username): void
{
    $notifications = load_notifications();
    $updated = false;
    foreach ($notifications as &$notification) {
        if (($notification['id'] ?? '') === $id && ($notification['user'] ?? '') === $username) {
            $notification['read_at'] = gmdate('c');
            $updated = true;
            break;
        }
    }
    unset($notification);
    if ($updated) {
        save_notifications($notifications);
    }
}

function mark_all_notifications_as_read(string $username): void
{
    $notifications = load_notifications();
    $updated = false;
    foreach ($notifications as &$notification) {
        if (($notification['user'] ?? '') === $username && empty($notification['read_at'])) {
            $notification['read_at'] = gmdate('c');
            $updated = true;
        }
    }
    unset($notification);
    if ($updated) {
        save_notifications($notifications);
    }
}
