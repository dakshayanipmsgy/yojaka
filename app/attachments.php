<?php
// Attachment helper functions for Yojaka v1.2

function attachments_base_path(): string
{
    global $config;
    $base = $config['attachments_data_path'] ?? (YOJAKA_DATA_PATH . '/attachments');
    return rtrim($base, DIRECTORY_SEPARATOR);
}

function attachments_meta_path(): string
{
    global $config;
    $file = $config['attachments_meta_file'] ?? 'meta.json';
    return attachments_base_path() . DIRECTORY_SEPARATOR . $file;
}

function ensure_attachments_storage(): void
{
    $base = attachments_base_path();
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }

    $htaccess = $base . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n<IfModule mod_autoindex.c>\n  Options -Indexes\n</IfModule>\n");
    }

    $modules = ['rti', 'dak', 'inspection', 'documents', 'bills', 'misc'];
    foreach ($modules as $module) {
        $dir = $base . DIRECTORY_SEPARATOR . $module;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    $metaPath = attachments_meta_path();
    if (!file_exists($metaPath)) {
        file_put_contents($metaPath, json_encode([]));
    }
}

function load_attachments_meta(): array
{
    $path = attachments_meta_path();
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_attachments_meta(array $attachments): void
{
    $path = attachments_meta_path();
    $handle = fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to write attachments metadata.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock attachments metadata.');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($attachments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function generate_next_attachment_id(): string
{
    $meta = load_attachments_meta();
    $max = 0;
    foreach ($meta as $item) {
        if (!empty($item['id']) && preg_match('/ATT-(\d+)/', $item['id'], $m)) {
            $num = (int) $m[1];
            $max = max($max, $num);
        }
    }
    $next = $max + 1;
    return 'ATT-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function sanitize_filename_for_display(string $name): string
{
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return $safe ?: 'file';
}

function store_uploaded_attachment(array $file, string $module, ?string $entity_id, string $description, array $tags)
{
    global $config;
    ensure_attachments_storage();

    $allowedModules = ['rti', 'dak', 'inspection', 'documents', 'bills', 'misc'];
    if (!in_array($module, $allowedModules, true)) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $originalName = basename($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = $config['attachments_allowed_extensions'] ?? [];
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $sizeBytes = (int) ($file['size'] ?? 0);
    $maxSize = (int) ($config['attachments_max_size_bytes'] ?? (5 * 1024 * 1024));
    if ($sizeBytes <= 0 || $sizeBytes > $maxSize) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? 'application/octet-stream');
    if ($finfo) {
        finfo_close($finfo);
    }
    $mime = $mime ?: 'application/octet-stream';

    $randomName = bin2hex(random_bytes(8)) . '_' . uniqid('', true);
    $storedName = $randomName . '.' . $extension;

    $targetDir = attachments_base_path() . DIRECTORY_SEPARATOR . $module;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    $now = gmdate('c');
    $currentUser = current_user();
    $record = [
        'id' => generate_next_attachment_id(),
        'module' => $module,
        'entity_id' => $entity_id,
        'original_name' => sanitize_filename_for_display($originalName),
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'size_bytes' => $sizeBytes,
        'uploaded_by' => $currentUser['username'] ?? null,
        'uploaded_at' => $now,
        'description' => trim($description),
        'tags' => array_values(array_filter(array_map('trim', $tags), function ($t) {
            return $t !== '';
        })),
        'department_id' => $currentUser['department_id'] ?? null,
        'office_id' => get_current_office_id(),
    ];

    $meta = load_attachments_meta();
    $meta[] = $record;
    save_attachments_meta($meta);

    return $record;
}

function find_attachments_for_entity(string $module, string $entity_id): array
{
    $meta = load_attachments_meta();
    $officeId = get_current_office_id();
    return array_values(array_filter($meta, function ($item) use ($module, $entity_id, $officeId) {
        return ($item['module'] ?? '') === $module && ($item['entity_id'] ?? '') === $entity_id && (($item['office_id'] ?? $officeId) === $officeId);
    }));
}

function get_attachment_by_id(string $attachment_id): ?array
{
    $meta = load_attachments_meta();
    foreach ($meta as $item) {
        if (($item['id'] ?? '') === $attachment_id) {
            return $item;
        }
    }
    return null;
}

function format_attachment_size(int $bytes): string
{
    if ($bytes > 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes > 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

function handle_attachment_upload(string $module, string $entityId, string $csrfSessionKey, bool $canUpload): array
{
    $errors = [];
    $notice = '';
    $csrfToken = $_SESSION[$csrfSessionKey] ?? bin2hex(random_bytes(16));
    $_SESSION[$csrfSessionKey] = $csrfToken;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attachment_upload'])) {
        if (!$canUpload) {
            $errors[] = 'You do not have permission to upload attachments.';
        } else {
            $submitted = $_POST['csrf_token'] ?? '';
            if (!$submitted || !hash_equals($csrfToken, $submitted)) {
                $errors[] = 'Security token mismatch for attachment upload.';
            }
        }

        if (empty($errors)) {
            $description = trim($_POST['attachment_description'] ?? '');
            $tags = array_filter(array_map('trim', explode(',', $_POST['attachment_tags'] ?? '')));
            $file = $_FILES['attachment_file'] ?? null;
            $record = $file ? store_uploaded_attachment($file, $module, $entityId, $description, $tags) : null;
            if ($record) {
                log_event('attachment_uploaded', current_user()['username'] ?? null, [
                    'attachment_id' => $record['id'],
                    'module' => $module,
                    'entity_id' => $entityId,
                ]);
                if (function_exists('write_audit_log')) {
                    write_audit_log($module, $entityId, 'attachment_upload', ['attachment_id' => $record['id']]);
                }
                $notice = 'Attachment uploaded successfully.';
            } else {
                $errors[] = 'Unable to save attachment. Please check file type and size.';
            }
        }
    }

    return [$errors, $notice, $_SESSION[$csrfSessionKey]];
}
