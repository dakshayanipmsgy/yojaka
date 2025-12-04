<?php
require_role('superadmin');
$registry = load_offices_registry();
$messages = [];
$errors = [];

function superadmin_dir_size(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $size = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $size += $file->getSize();
    }
    return $size;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_office') {
        $officeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_POST['office_id'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $short = trim($_POST['short_name'] ?? '');
        if ($officeId === '' || $name === '') {
            $errors[] = 'Office ID and Name are required.';
        } else {
            foreach ($registry as $entry) {
                if (($entry['id'] ?? '') === $officeId) {
                    $errors[] = 'Office ID already exists.';
                }
            }
            if (empty($errors)) {
                $configFile = $officeId . '.json';
                $licenseFile = 'license_' . $officeId . '.json';
                $registry[] = [
                    'id' => $officeId,
                    'name' => $name,
                    'short_name' => $short ?: $officeId,
                    'district' => trim($_POST['district'] ?? ''),
                    'block' => trim($_POST['block'] ?? ''),
                    'department' => trim($_POST['department'] ?? ''),
                    'license_type' => trim($_POST['license_type'] ?? 'trial'),
                    'license_expiry' => trim($_POST['license_expiry'] ?? ''),
                    'version' => '2.0',
                    'config_file' => $configFile,
                    'license_file' => $licenseFile,
                    'active' => true,
                    'created_at' => gmdate('c'),
                ];
                save_offices_registry($registry);
                $baseConfig = default_office_config();
                $baseConfig['office_name'] = $name;
                $baseConfig['office_short_name'] = $short ?: $officeId;
                $baseConfig['multi_office_routing'] = false;
                $baseConfig['office_type'] = 'block';
                $baseConfig['schema_version'] = 200;
                $configPath = office_config_path_by_file($configFile);
                @file_put_contents($configPath, json_encode($baseConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $officeDataDir = YOJAKA_DATA_PATH . '/' . $officeId;
                $officeDirs = ['dak', 'rti', 'bills', 'inspection', 'routes', 'users', 'logs', 'attachments', 'archive'];
                foreach ($officeDirs as $dir) {
                    @mkdir($officeDataDir . '/' . $dir, 0755, true);
                }
                $messages[] = 'Office created and seeded.';
            }
        }
    } elseif ($action === 'toggle') {
        $target = $_POST['office_id'] ?? '';
        foreach ($registry as &$entry) {
            if (($entry['id'] ?? '') === $target) {
                $entry['active'] = !empty($entry['active']) ? false : true;
                $messages[] = 'Office state updated.';
            }
        }
        unset($entry);
        save_offices_registry($registry);
    }
    $registry = load_offices_registry();
}
?>
<?php foreach ($messages as $msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
<?php endforeach; ?>
<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
    <section>
        <h3>Create New Office</h3>
        <form method="post" class="form-stacked">
            <input type="hidden" name="action" value="create_office" />
            <label>Office ID</label>
            <input type="text" name="office_id" required />
            <label>Name</label>
            <input type="text" name="name" required />
            <label>Short name</label>
            <input type="text" name="short_name" />
            <label>District</label>
            <input type="text" name="district" />
            <label>Block</label>
            <input type="text" name="block" />
            <label>Department</label>
            <input type="text" name="department" />
            <label>License type</label>
            <input type="text" name="license_type" value="trial" />
            <label>License expiry</label>
            <input type="text" name="license_expiry" placeholder="YYYY-MM-DD" />
            <button class="btn primary" type="submit">Create Office</button>
        </form>
    </section>
    <section>
        <h3>Registry</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>District/Block/Dept</th><th>License</th><th>Version</th><th>Storage</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registry as $entry): ?>
                    <?php $officeDir = YOJAKA_DATA_PATH . '/' . ($entry['id'] ?? ''); ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars(($entry['name'] ?? '') . ' (' . ($entry['short_name'] ?? '') . ')'); ?></td>
                        <td><?= htmlspecialchars(($entry['district'] ?? '-') . ' / ' . ($entry['block'] ?? '-') . ' / ' . ($entry['department'] ?? '-')); ?></td>
                        <td><?= htmlspecialchars(($entry['license_type'] ?? 'trial') . ' ' . ($entry['license_expiry'] ?? '')); ?></td>
                        <td><?= htmlspecialchars($entry['version'] ?? '1.x'); ?></td>
                        <td><?= number_format(superadmin_dir_size($officeDir) / 1024, 2); ?> KB</td>
                        <td><?= !empty($entry['active']) ? 'Active' : 'Suspended'; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="toggle" />
                                <input type="hidden" name="office_id" value="<?= htmlspecialchars($entry['id'] ?? ''); ?>" />
                                <button class="btn" type="submit"><?= !empty($entry['active']) ? 'Suspend' : 'Activate'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
