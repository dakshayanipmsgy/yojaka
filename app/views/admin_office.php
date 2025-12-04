<?php
require_login();
require_permission('manage_office_config');

$office = load_office_config();
$currentOfficeId = get_current_office_id();
$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        echo '<div class="alert alert-danger">Security token mismatch.</div>';
    } else {
        $office['office_name'] = trim($_POST['office_name'] ?? $office['office_name']);
        $office['office_short_name'] = trim($_POST['office_short_name'] ?? $office['office_short_name']);
        $office['base_url'] = trim($_POST['base_url'] ?? $office['base_url']);
        $office['date_format_php'] = trim($_POST['date_format_php'] ?? $office['date_format_php']);
        $office['timezone'] = trim($_POST['timezone'] ?? $office['timezone']);
        $office['theme']['primary_color'] = trim($_POST['primary_color'] ?? $office['theme']['primary_color']);
        $office['theme']['secondary_color'] = trim($_POST['secondary_color'] ?? $office['theme']['secondary_color']);
        $office['theme']['logo_path'] = trim($_POST['logo_path'] ?? $office['theme']['logo_path']);

        $modules = ['rti', 'dak', 'inspection', 'bills', 'meeting_minutes', 'work_orders', 'guc'];
        foreach ($modules as $module) {
            $office['modules']['enable_' . $module] = isset($_POST['modules']['enable_' . $module]);
        }

        $prefixes = $_POST['id_prefixes'] ?? [];
        foreach ($office['id_prefixes'] as $key => $value) {
            if (!empty($prefixes[$key])) {
                $office['id_prefixes'][$key] = trim($prefixes[$key]);
            }
        }

        $office['portal']['enabled'] = isset($_POST['portal']['enabled']);
        $office['portal']['features']['rti_status'] = isset($_POST['portal']['features']['rti_status']);
        $office['portal']['features']['dak_status'] = isset($_POST['portal']['features']['dak_status']);
        $office['portal']['features']['request'] = isset($_POST['portal']['features']['request']);
        $office['portal']['kiosk']['enabled'] = isset($_POST['portal']['kiosk']['enabled']);
        $office['portal']['kiosk']['default_action'] = trim($_POST['portal']['kiosk']['default_action'] ?? ($office['portal']['kiosk']['default_action'] ?? ''));
        $idleTimeout = (int) ($_POST['portal']['kiosk']['idle_timeout_seconds'] ?? ($office['portal']['kiosk']['idle_timeout_seconds'] ?? 300));
        $office['portal']['kiosk']['idle_timeout_seconds'] = $idleTimeout > 0 ? $idleTimeout : 300;

        if (save_office_config($currentOfficeId, $office)) {
            echo '<div class="info">Office configuration updated successfully.</div>';
        } else {
            echo '<div class="alert alert-danger">Unable to save configuration.</div>';
        }
    }
}
?>
<form method="post" class="form-stacked">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
    <div class="grid">
        <div class="form-field">
            <label>Office Name</label>
            <input type="text" name="office_name" required value="<?= htmlspecialchars($office['office_name'] ?? ''); ?>">
        </div>
        <div class="form-field">
            <label>Office Short Name</label>
            <input type="text" name="office_short_name" required value="<?= htmlspecialchars($office['office_short_name'] ?? ''); ?>">
        </div>
        <div class="form-field">
            <label>Base URL</label>
            <input type="text" name="base_url" required value="<?= htmlspecialchars($office['base_url'] ?? ''); ?>">
        </div>
        <div class="form-field">
            <label>Date Format (PHP)</label>
            <input type="text" name="date_format_php" value="<?= htmlspecialchars($office['date_format_php'] ?? 'd-m-Y'); ?>">
        </div>
        <div class="form-field">
            <label>Timezone</label>
            <input type="text" name="timezone" value="<?= htmlspecialchars($office['timezone'] ?? 'Asia/Kolkata'); ?>">
        </div>
    </div>

    <h3>Theme</h3>
    <div class="grid">
        <div class="form-field">
            <label>Primary Color</label>
            <input type="text" name="primary_color" value="<?= htmlspecialchars($office['theme']['primary_color'] ?? '#0f5aa5'); ?>">
        </div>
        <div class="form-field">
            <label>Secondary Color</label>
            <input type="text" name="secondary_color" value="<?= htmlspecialchars($office['theme']['secondary_color'] ?? '#f5f7fb'); ?>">
        </div>
        <div class="form-field">
            <label>Logo Path</label>
            <input type="text" name="logo_path" value="<?= htmlspecialchars($office['theme']['logo_path'] ?? ''); ?>">
        </div>
    </div>

    <h3>ID Prefixes</h3>
    <div class="grid">
        <?php foreach ($office['id_prefixes'] as $key => $value): ?>
            <div class="form-field">
                <label><?= htmlspecialchars(strtoupper($key)); ?> Prefix</label>
                <input type="text" name="id_prefixes[<?= htmlspecialchars($key); ?>]" value="<?= htmlspecialchars($value); ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Modules</h3>
    <div class="grid">
        <?php foreach (['rti' => 'RTI', 'dak' => 'Dak & File', 'inspection' => 'Inspection', 'bills' => 'Contractor Bills', 'meeting_minutes' => 'Meeting Minutes', 'work_orders' => 'Work Orders', 'guc' => 'GUC'] as $key => $label): ?>
            <label><input type="checkbox" name="modules[enable_<?= htmlspecialchars($key); ?>]" <?= !empty($office['modules']['enable_' . $key]) ? 'checked' : ''; ?>> Enable <?= htmlspecialchars($label); ?></label>
        <?php endforeach; ?>
    </div>

    <h3>Public Portal</h3>
    <div class="grid">
        <label><input type="checkbox" name="portal[enabled]" <?= !empty($office['portal']['enabled']) ? 'checked' : ''; ?>> Enable public portal for this office</label>
        <label><input type="checkbox" name="portal[features][rti_status]" <?= !empty($office['portal']['features']['rti_status']) ? 'checked' : ''; ?>> Allow RTI status checks</label>
        <label><input type="checkbox" name="portal[features][dak_status]" <?= !empty($office['portal']['features']['dak_status']) ? 'checked' : ''; ?>> Allow Dak/File status checks</label>
        <label><input type="checkbox" name="portal[features][request]" <?= !empty($office['portal']['features']['request']) ? 'checked' : ''; ?>> Allow public requests / grievances</label>
    </div>
    <p><strong>Public portal URL:</strong> <?= htmlspecialchars(YOJAKA_BASE_URL . '/portal.php?office=' . urlencode($currentOfficeId)); ?></p>

    <h4>Kiosk Mode</h4>
    <div class="grid">
        <label><input type="checkbox" name="portal[kiosk][enabled]" <?= !empty($office['portal']['kiosk']['enabled']) ? 'checked' : ''; ?>> Enable kiosk layout</label>
        <div class="form-field">
            <label>Default kiosk action</label>
            <select name="portal[kiosk][default_action]">
                <option value="" <?= empty($office['portal']['kiosk']['default_action']) ? 'selected' : ''; ?>>Landing page</option>
                <option value="rti_status" <?= ($office['portal']['kiosk']['default_action'] ?? '') === 'rti_status' ? 'selected' : ''; ?>>RTI Status</option>
                <option value="dak_status" <?= ($office['portal']['kiosk']['default_action'] ?? '') === 'dak_status' ? 'selected' : ''; ?>>Dak / File Status</option>
                <option value="request" <?= ($office['portal']['kiosk']['default_action'] ?? '') === 'request' ? 'selected' : ''; ?>>Request / Grievance</option>
            </select>
        </div>
        <div class="form-field">
            <label>Idle timeout (seconds)</label>
            <input type="number" name="portal[kiosk][idle_timeout_seconds]" min="30" value="<?= htmlspecialchars($office['portal']['kiosk']['idle_timeout_seconds'] ?? 300); ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Save Settings</button>
    </div>
</form>
