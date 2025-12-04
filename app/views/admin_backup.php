<?php
require_permission('admin_backup');
$license = get_current_office_license();
$officeReadOnly = office_is_read_only($license);

$csrfToken = $_SESSION['admin_backup_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['admin_backup_csrf'] = $csrfToken;
$message = '';
$error = '';

if (!license_feature_enabled($license, 'enable_backup')) {
    echo '<div class="alert alert-danger">Backup and export are disabled by the current license.</div>';
    return;
}
if ($officeReadOnly) {
    echo '<div class="alert alert-danger">Trial expired. Backups are blocked while in read-only mode.</div>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($csrfToken, $submittedToken)) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $label = trim($_POST['label'] ?? '');
        $zipPath = create_yogaka_backup_zip($label);
        if ($zipPath === null || !file_exists($zipPath)) {
            $error = 'Backup could not be created. ZipArchive may be unavailable on this server.';
        } else {
            $fileName = basename($zipPath);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            @unlink($zipPath);
            exit;
        }
    }
}
?>
<div class="info">
    <p>This tool generates an on-demand ZIP containing data files (JSON and related) and the main configuration file. The backup focuses on data to assist migration or auditing and does not include application code.</p>
    <p>Access is restricted to administrators with the <strong>admin_backup</strong> permission.</p>
</div>
<?php if ($message): ?>
    <div class="alert info"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>
<form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_backup" class="form-stacked">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
    <div class="form-field">
        <label for="label">Optional label for this backup (letters, numbers, dashes only)</label>
        <input type="text" id="label" name="label" maxlength="40" value="<?= htmlspecialchars($_POST['label'] ?? ''); ?>">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn-primary">Generate Backup ZIP</button>
    </div>
</form>
