<?php
require_login();
require_permission('manage_housekeeping');

$currentOffice = get_current_office_id();
$currentLicense = get_current_office_license();
$usageLogPath = YOJAKA_LOGS_PATH . DIRECTORY_SEPARATOR . ($config['usage_log_file'] ?? 'usage.log');
$messages = [];
$errors = [];

function file_size_value(string $path): int
{
    return file_exists($path) ? (int) filesize($path) : 0;
}

function approximate_file_size_from_bytes(int $size): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function collect_storage_overview(): array
{
    $overview = [];
    $overview['rti'] = ['count' => count(load_rti_cases()), 'size' => file_size_value(rti_cases_path())];
    $overview['dak'] = ['count' => count(load_dak_entries()), 'size' => file_size_value(dak_entries_path())];
    $overview['inspection'] = ['count' => count(load_inspection_reports()), 'size' => file_size_value(inspection_reports_path())];
    $overview['documents'] = [
        'count' => count(load_document_records('meeting_minutes')) + count(load_document_records('work_order')) + count(load_document_records('guc')),
        'size' => file_size_value(document_records_path('meeting_minutes')) + file_size_value(document_records_path('work_order')) + file_size_value(document_records_path('guc')),
    ];
    $overview['bills'] = ['count' => count(load_bills()), 'size' => file_size_value(bills_file_path())];
    $overview['attachments'] = ['count' => count(load_attachments_meta()), 'size' => file_size_value(attachments_meta_path())];
    return $overview;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'rotate_logs') {
            $archiveDir = YOJAKA_LOGS_PATH . DIRECTORY_SEPARATOR . 'archive';
            if (!is_dir($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }
            if (file_exists($usageLogPath)) {
                $timestamp = date('Ymd_His');
                $dest = $archiveDir . DIRECTORY_SEPARATOR . 'usage_' . $timestamp . '.log';
                @rename($usageLogPath, $dest);
                @file_put_contents($usageLogPath, '');
                $messages[] = 'usage.log rotated and truncated.';
                log_event('log_rotated', current_user()['username'] ?? null, ['file' => 'usage.log']);
            } else {
                $errors[] = 'usage.log not found.';
            }
        } elseif ($action === 'auto_archive') {
            $module = $_POST['module'] ?? '';
            $count = 0;
            switch ($module) {
                case 'rti':
                    $records = load_rti_cases();
                    $count = auto_archive_entities('rti', $records);
                    save_rti_cases($records);
                    break;
                case 'dak':
                    $records = load_dak_entries();
                    $count = auto_archive_entities('dak', $records);
                    save_dak_entries($records);
                    break;
                case 'inspection':
                    $records = load_inspection_reports();
                    $count = auto_archive_entities('inspection', $records);
                    save_inspection_reports($records);
                    break;
                case 'documents':
                    $mm = load_document_records('meeting_minutes');
                    $wo = load_document_records('work_order');
                    $guc = load_document_records('guc');
                    $count += auto_archive_entities('documents', $mm);
                    $count += auto_archive_entities('documents', $wo);
                    $count += auto_archive_entities('documents', $guc);
                    save_document_records('meeting_minutes', $mm);
                    save_document_records('work_order', $wo);
                    save_document_records('guc', $guc);
                    break;
                case 'bills':
                    $records = load_bills();
                    $count = auto_archive_entities('bills', $records);
                    save_bills($records);
                    break;
                default:
                    $errors[] = 'Invalid module for auto archive.';
            }
            if ($count > 0) {
                $messages[] = "Archived {$count} record(s) for {$module}.";
                log_event('auto_archive_run', current_user()['username'] ?? null, ['module' => $module, 'count' => $count]);
            } elseif ($action === 'auto_archive' && empty($errors)) {
                $messages[] = 'No records eligible for archive right now.';
            }
        } elseif ($action === 'cleanup_attachments') {
            $thresholdDays = $config['retention']['attachments']['delete_archived_after_days'] ?? null;
            if ($thresholdDays === null) {
                $errors[] = 'Attachment cleanup not configured.';
            } else {
                $meta = load_attachments_meta();
                $cutoff = time() - ((int) $thresholdDays * 86400);
                $kept = [];
                $deleted = 0;
                foreach ($meta as $att) {
                    $module = $att['module'] ?? '';
                    $entityId = $att['entity_id'] ?? '';
                    $uploadedAt = strtotime($att['uploaded_at'] ?? '') ?: 0;
                    $archivedAt = null;
                    if ($module && $entityId) {
                        $archivedAt = get_archived_at_for_entity($module, $entityId);
                    }
                    $referenceTime = $archivedAt ? strtotime($archivedAt) : $uploadedAt;
                    if ($referenceTime && $referenceTime < $cutoff && $archivedAt) {
                        $path = attachment_file_path($att['path'] ?? '');
                        if ($path && file_exists($path)) {
                            @unlink($path);
                        }
                        $deleted++;
                        continue;
                    }
                    $kept[] = $att;
                }
                save_attachments_meta($kept);
                $messages[] = "Deleted {$deleted} archived attachment(s).";
                log_event('attachments_cleaned', current_user()['username'] ?? null, ['deleted' => $deleted]);
            }
        } elseif ($action === 'rebuild_index') {
            $module = $_POST['module'] ?? '';
            build_full_index_for_module($module);
            $messages[] = 'Index rebuilt for ' . htmlspecialchars($module);
        } elseif ($action === 'rebuild_index_v2') {
            rebuild_all_index_v2();
            $messages[] = 'Index v2 rebuilt for all modules';
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

function get_archived_at_for_entity(string $module, string $id): ?string
{
    switch ($module) {
        case 'rti':
            $items = load_rti_cases();
            $found = find_rti_by_id($items, $id);
            return $found['archived_at'] ?? null;
        case 'dak':
            foreach (load_dak_entries() as $entry) {
                if (($entry['id'] ?? '') === $id) {
                    return $entry['archived_at'] ?? null;
                }
            }
            return null;
        case 'inspection':
            foreach (load_inspection_reports() as $report) {
                if (($report['id'] ?? '') === $id) {
                    return $report['archived_at'] ?? null;
                }
            }
            return null;
        case 'documents':
            foreach (array_merge(load_document_records('meeting_minutes'), load_document_records('work_order'), load_document_records('guc')) as $doc) {
                if (($doc['id'] ?? '') === $id) {
                    return $doc['archived_at'] ?? null;
                }
            }
            return null;
        case 'bills':
            $bills = load_bills();
            $found = find_bill_by_id($bills, $id);
            return $found['archived_at'] ?? null;
        default:
            return null;
    }
}

$storage = collect_storage_overview();
$attachmentsRetention = $config['retention']['attachments']['delete_archived_after_days'] ?? null;
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if (!empty($messages)): ?>
    <div class="alert alert-success">
        <ul><?php foreach ($messages as $msg): ?><li><?= htmlspecialchars($msg); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<h3>Storage Overview (Office: <?= htmlspecialchars($currentOffice); ?>)</h3>
<table class="table">
    <thead><tr><th>Module</th><th>Count</th><th>Approx Size</th></tr></thead>
    <tbody>
    <?php foreach ($storage as $module => $stats): ?>
        <tr><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $module))); ?></td><td><?= (int) ($stats['count'] ?? 0); ?></td><td><?= approximate_file_size_from_bytes((int) ($stats['size'] ?? 0)); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h3>Log Rotation</h3>
<p>usage.log size: <?= approximate_file_size_from_bytes(file_size_value($usageLogPath)); ?></p>
<form method="post">
    <input type="hidden" name="action" value="rotate_logs">
    <button class="btn" type="submit">Rotate &amp; Truncate usage.log</button>
</form>

<h3>Auto Archive</h3>
<p>Use the buttons below to archive records older than configured active days.</p>
<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
    <?php foreach (['rti','dak','inspection','documents','bills'] as $module): ?>
        <div class="card">
            <strong><?= strtoupper($module); ?></strong>
            <div>Active days: <?= (int) ($config['retention'][$module]['active_days'] ?? 0); ?></div>
            <form method="post" style="margin-top:0.5rem;">
                <input type="hidden" name="action" value="auto_archive">
                <input type="hidden" name="module" value="<?= htmlspecialchars($module); ?>">
                <button class="btn" type="submit">Archive Eligible Now</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<h3>Attachment Cleanup</h3>
<?php if ($attachmentsRetention === null): ?>
    <p class="muted">No attachment cleanup configured.</p>
<?php else: ?>
    <p>Attachments for archived items older than <?= (int) $attachmentsRetention; ?> days will be deleted.</p>
    <form method="post">
        <input type="hidden" name="action" value="cleanup_attachments">
        <button class="btn danger" type="submit">Delete Old Attachments</button>
    </form>
<?php endif; ?>

<h3>Indexes</h3>
<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.5rem;">
    <?php foreach (['rti','dak','inspection','documents','bills'] as $module): ?>
        <form method="post" class="card">
            <input type="hidden" name="action" value="rebuild_index">
            <input type="hidden" name="module" value="<?= htmlspecialchars($module); ?>">
            <div><strong><?= strtoupper($module); ?></strong></div>
            <button class="btn" type="submit">Rebuild Index</button>
        </form>
    <?php endforeach; ?>
</div>
<form method="post" style="margin-top:1rem;">
    <input type="hidden" name="action" value="rebuild_index_v2">
    <button class="btn primary" type="submit">Rebuild All Index v2</button>
</form>
