<?php
require_login();
require_permission('manage_office_config');

$errors = [];
$notices = [];
$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf;

$officeId = get_current_office_id();

$contractors = load_contractors();
$staff = load_staff($officeId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        $errors[] = 'Security token mismatch.';
    } else {
        if (!empty($_FILES['contractors_csv']['tmp_name'])) {
            $tmp = $_FILES['contractors_csv']['tmp_name'];
            $imported = import_contractors_from_csv($tmp);
            $notices[] = $imported . ' contractor records imported.';
            $contractors = load_contractors();
        }
        if (!empty($_FILES['staff_csv']['tmp_name'])) {
            $tmp = $_FILES['staff_csv']['tmp_name'];
            $imported = import_staff_from_csv($officeId, $tmp);
            $notices[] = $imported . ' staff records imported.';
            $staff = load_staff($officeId);
        }
    }
}
?>
<div class="grid">
    <div class="card">
        <h3>Contractors</h3>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
        <?php foreach ($notices as $msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" class="form-inline" style="gap:0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <label>Import Contractors CSV
                <input type="file" name="contractors_csv" accept="text/csv">
            </label>
            <button type="submit" class="btn primary">Upload</button>
        </form>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Active</th></tr></thead>
                <tbody>
                    <?php if (empty($contractors)): ?>
                        <tr><td colspan="4">No contractors imported yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contractors as $ctr): ?>
                            <tr>
                                <td><?= htmlspecialchars($ctr['id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($ctr['name'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($ctr['category'] ?? ''); ?></td>
                                <td><?= !empty($ctr['active']) ? 'Yes' : 'No'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>Staff (Officers / Engineers / Clerks)</h3>
        <form method="post" enctype="multipart/form-data" class="form-inline" style="gap:0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <label>Import Staff CSV
                <input type="file" name="staff_csv" accept="text/csv">
            </label>
            <button type="submit" class="btn primary">Upload</button>
        </form>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Designation</th><th>Role</th><th>Department</th></tr></thead>
                <tbody>
                    <?php if (empty($staff)): ?>
                        <tr><td colspan="5">No staff imported yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($staff as $person): ?>
                            <tr>
                                <td><?= htmlspecialchars($person['staff_id'] ?? ($person['id'] ?? '')); ?></td>
                                <td><?= htmlspecialchars($person['full_name'] ?? ($person['name'] ?? '')); ?></td>
                                <td><?= htmlspecialchars($person['designation'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($person['role'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($person['department_id'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
