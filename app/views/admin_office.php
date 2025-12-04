<?php
require_login();
require_permission('manage_office_config');

$office = load_office_config();
$currentOfficeId = get_current_office_id();
$printConfig = office_print_config($currentOfficeId);
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

        $officePrint = $office['print'] ?? default_print_config($office);
        $officePrint['page_size'] = strtoupper(trim($_POST['print']['page_size'] ?? ($officePrint['page_size'] ?? 'A4')));
        $officePrint['show_header'] = isset($_POST['print']['show_header']);
        $officePrint['show_footer'] = isset($_POST['print']['show_footer']);
        $officePrint['header_html'] = trim($_POST['print']['header_html'] ?? ($officePrint['header_html'] ?? ''));
        $officePrint['footer_html'] = trim($_POST['print']['footer_html'] ?? ($officePrint['footer_html'] ?? ''));
        $officePrint['logo_path'] = trim($_POST['print']['logo_path'] ?? ($officePrint['logo_path'] ?? ''));
        $officePrint['watermark_text'] = trim($_POST['print']['watermark_text'] ?? ($officePrint['watermark_text'] ?? ''));
        $officePrint['watermark_enabled'] = isset($_POST['print']['watermark_enabled']);

        if (!empty($_FILES['print_logo']['tmp_name'])) {
            $uploadDir = YOJAKA_DATA_PATH . '/offices/logos';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $extension = pathinfo($_FILES['print_logo']['name'] ?? '', PATHINFO_EXTENSION);
            $safeExtension = $extension ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension) : '';
            $target = $uploadDir . '/logo_' . preg_replace('/[^a-zA-Z0-9]/', '_', $currentOfficeId) . $safeExtension;
            if (@move_uploaded_file($_FILES['print_logo']['tmp_name'], $target)) {
                $officePrint['logo_path'] = $target;
            }
        }

        $office['print'] = $officePrint;

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
$printConfig = $office['print'] ?? $printConfig;
?>
<form method="post" class="form-stacked" enctype="multipart/form-data">
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

    <h3>Print / Letterhead Settings</h3>
    <div class="grid">
        <div class="form-field">
            <label>Page Size</label>
            <select name="print[page_size]">
                <option value="A4" <?= ($printConfig['page_size'] ?? '') === 'A4' ? 'selected' : ''; ?>>A4</option>
                <option value="LETTER" <?= ($printConfig['page_size'] ?? '') === 'LETTER' ? 'selected' : ''; ?>>Letter</option>
            </select>
        </div>
        <label><input type="checkbox" name="print[show_header]" <?= !empty($printConfig['show_header']) ? 'checked' : ''; ?>> Show header</label>
        <label><input type="checkbox" name="print[show_footer]" <?= !empty($printConfig['show_footer']) ? 'checked' : ''; ?>> Show footer</label>
        <label><input type="checkbox" name="print[watermark_enabled]" <?= !empty($printConfig['watermark_enabled']) ? 'checked' : ''; ?>> Enable watermark</label>
    </div>
    <div class="grid">
        <div class="form-field">
            <label>Letterhead Logo</label>
            <input type="text" name="print[logo_path]" value="<?= htmlspecialchars($printConfig['logo_path'] ?? ''); ?>" placeholder="/data/offices/logos/office_logo.png">
            <input type="file" name="print_logo" accept="image/*">
        </div>
        <div class="form-field">
            <label>Watermark Text</label>
            <input type="text" name="print[watermark_text]" value="<?= htmlspecialchars($printConfig['watermark_text'] ?? ''); ?>" placeholder="TRIAL COPY - NOT FOR PRODUCTION">
        </div>
    </div>
    <div class="grid">
        <div class="form-field">
            <label>Header HTML</label>
            <textarea name="print[header_html]" rows="4"><?= htmlspecialchars($printConfig['header_html'] ?? ''); ?></textarea>
        </div>
        <div class="form-field">
            <label>Footer HTML</label>
            <textarea name="print[footer_html]" rows="3"><?= htmlspecialchars($printConfig['footer_html'] ?? ''); ?></textarea>
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
