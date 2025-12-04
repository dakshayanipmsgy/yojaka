<?php
require_login();
require_permission('manage_office_config');

$currentOfficeId = get_current_office_id();
$officeConfig = get_current_office_config();
$license = load_office_license($currentOfficeId);
$csrfToken = $_SESSION['admin_license_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['admin_license_csrf'] = $csrfToken;
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!$submitted || !hash_equals($csrfToken, $submitted)) {
        $errors[] = 'Security token mismatch. Please retry.';
    } else {
        $newKey = trim($_POST['license_key'] ?? '');
        if ($newKey === '') {
            $errors[] = 'License key cannot be empty.';
        } else {
            $license['license_key'] = $newKey;
            if (stripos($newKey, 'YOJAKA-FULL-') === 0) {
                $license['type'] = 'full';
                $license['expiry_date'] = date('Y-m-d', strtotime('+10 years'));
            } elseif (stripos($newKey, 'YOJAKA-TRIAL-') === 0) {
                $license['type'] = 'trial';
            }
            save_office_license($license, $currentOfficeId);
            $message = 'License key updated.';
        }
    }
    $license = load_office_license($currentOfficeId);
}

$daysRemaining = '';
if (!empty($license['expiry_date'])) {
    $daysRemaining = (int) floor((strtotime($license['expiry_date']) - time()) / 86400);
}
?>
<div class="info">
    <p>License and trial status for <strong><?= htmlspecialchars($officeConfig['office_name'] ?? $currentOfficeId); ?></strong>.</p>
    <p>Data is stored in <code>/data/offices</code> per office to discourage copying without valid license files.</p>
</div>
<?php if ($message): ?>
    <div class="alert info"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<div class="card">
    <h3>Office Profile</h3>
    <p><strong>Office ID:</strong> <?= htmlspecialchars($currentOfficeId); ?></p>
    <p><strong>Name:</strong> <?= htmlspecialchars($officeConfig['office_name'] ?? ''); ?></p>
    <p><strong>Short Name:</strong> <?= htmlspecialchars($officeConfig['office_short_name'] ?? ''); ?></p>
</div>
<div class="card">
    <h3>License Details</h3>
    <p><strong>Licensed To:</strong> <?= htmlspecialchars($license['licensed_to'] ?? 'Unknown'); ?></p>
    <p><strong>License Key:</strong> <?= htmlspecialchars(mask_license_key($license['license_key'] ?? 'Not set')); ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($license['type'] ?? 'unknown'); ?></p>
    <p><strong>Issue Date:</strong> <?= htmlspecialchars($license['issue_date'] ?? ''); ?></p>
    <p><strong>Expiry Date:</strong> <?= htmlspecialchars($license['expiry_date'] ?? ''); ?> <?= is_license_expired($license) ? '(Expired)' : ''; ?></p>
    <?php if ($daysRemaining !== ''): ?>
        <p><strong>Days Remaining:</strong> <?= $daysRemaining; ?></p>
    <?php endif; ?>
    <p><strong>Features:</strong></p>
    <ul>
        <?php foreach (($license['features'] ?? []) as $key => $enabled): ?>
            <li><?= htmlspecialchars($key); ?>: <?= $enabled ? 'Enabled' : 'Disabled'; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<div class="card">
    <h3>Update License Key</h3>
    <form method="post" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=admin_license" class="form-stacked">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
        <div class="form-field">
            <label for="license_key">License Key</label>
            <input type="text" id="license_key" name="license_key" value="<?= htmlspecialchars($license['license_key'] ?? ''); ?>" autocomplete="off">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Save License Key</button>
        </div>
    </form>
</div>
