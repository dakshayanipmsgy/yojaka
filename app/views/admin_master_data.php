<?php
require_login();
require_permission('manage_office_config');
require_once __DIR__ . '/../master_data.php';
require_once __DIR__ . '/../staff.php';
require_once __DIR__ . '/../office.php';

$errors = [];
$notices = [];
$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf;

// Department is the larger unit (legacy office folder). Office is the smaller unit within it.
$departmentId = get_current_department_id();
$officeId = get_current_office_id();

$contractors = load_contractors($departmentId, $officeId);
$staff = load_staff($departmentId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        $errors[] = 'Security token mismatch.';
    } else {
        // Contractors import
        if (isset($_POST['import_contractors_csv']) && isset($_FILES['contractors_csv'])) {
            $fileTmp = $_FILES['contractors_csv']['tmp_name'] ?? '';
            if (is_uploaded_file($fileTmp)) {
                $rows = [];
                if (($handle = fopen($fileTmp, 'r')) !== false) {
                    $header = fgetcsv($handle) ?: [];
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($header) === count($data)) {
                            $rows[] = array_combine($header, $data);
                        }
                    }
                    fclose($handle);
                }

                foreach ($rows as $r) {
                    if (empty($r['contractor_id']) || empty($r['name'])) {
                        continue;
                    }

                    $contractor = [
                        'contractor_id' => trim($r['contractor_id']),
                        'name' => trim($r['name']),
                        'category' => trim($r['category'] ?? ''),
                        'department_id' => trim($r['department_id'] ?? $departmentId),
                        'office_id' => trim($r['office_id'] ?? $officeId),
                        'address' => trim($r['address'] ?? ''),
                        'phone' => trim($r['phone'] ?? ''),
                        'email' => trim($r['email'] ?? ''),
                        'active' => strtolower(trim($r['active'] ?? 'true')) !== 'false',
                    ];

                    $contractors = upsert_contractor($contractors, $contractor);
                }

                save_contractors($departmentId, $contractors);
                $notices[] = 'Contractor CSV import completed.';
            }
        }

        // Staff import
        if (isset($_POST['import_staff_csv']) && isset($_FILES['staff_csv'])) {
            $fileTmp = $_FILES['staff_csv']['tmp_name'] ?? '';
            if (is_uploaded_file($fileTmp)) {
                $rows = [];
                if (($handle = fopen($fileTmp, 'r')) !== false) {
                    $header = fgetcsv($handle) ?: [];
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($header) === count($data)) {
                            $rows[] = array_combine($header, $data);
                        }
                    }
                    fclose($handle);
                }

                foreach ($rows as $r) {
                    if (empty($r['staff_id']) || empty($r['full_name'])) {
                        continue;
                    }

                    $staffRow = [
                        'staff_id' => trim($r['staff_id']),
                        'full_name' => trim($r['full_name']),
                        'designation' => trim($r['designation'] ?? ''),
                        'department_id' => trim($r['department_id'] ?? $departmentId),
                        'office_id' => trim($r['office_id'] ?? $officeId),
                        'phone' => trim($r['phone'] ?? ''),
                        'email' => trim($r['email'] ?? ''),
                        'active' => strtolower(trim($r['active'] ?? 'true')) !== 'false',
                    ];

                    $staff = upsert_staff($staff, $staffRow);
                }

                save_staff($departmentId, $staff);
                $notices[] = 'Staff CSV import completed.';
            }
        }
    }
}
?>
<div class="grid">
    <div class="card" style="grid-column:1 / -1;">
        <div class="alert alert-info">
            <strong>Hierarchy update:</strong> Department is now the larger organizational unit (legacy office folder). An Office is a smaller unit inside that department. CSVs use <code>department_id</code> for the department folder and <code>office_id</code> for the unit within it.
        </div>
    </div>
    <div class="card">
        <h3>Contractors</h3>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
        <?php foreach ($notices as $msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
        <div class="actions" style="margin-bottom:0.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= YOJAKA_BASE_URL; ?>/samples/contractors_sample.csv" class="btn btn-sm btn-outline-secondary" target="_blank">Download Sample Contractors CSV</a>
        </div>
        <form method="post" enctype="multipart/form-data" class="form-inline" style="gap:0.5rem; margin-bottom:0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <input type="file" name="contractors_csv" accept=".csv">
            <button type="submit" name="import_contractors_csv" class="btn primary btn-sm">Import Contractors CSV</button>
        </form>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Department</th><th>Office</th><th>Active</th></tr></thead>
                <tbody>
                    <?php if (empty($contractors)): ?>
                        <tr><td colspan="6">No contractors imported yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contractors as $ctr): ?>
                            <tr>
                                <td><?= htmlspecialchars($ctr['contractor_id'] ?? $ctr['id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($ctr['name'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($ctr['category'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($ctr['department_id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($ctr['office_id'] ?? ''); ?></td>
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
        <div class="actions" style="margin-bottom:0.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= YOJAKA_BASE_URL; ?>/samples/staff_sample.csv" class="btn btn-sm btn-outline-secondary" target="_blank">Download Sample Staff CSV</a>
        </div>
        <form method="post" enctype="multipart/form-data" class="form-inline" style="gap:0.5rem; margin-bottom:0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
            <input type="file" name="staff_csv" accept=".csv">
            <button type="submit" name="import_staff_csv" class="btn primary btn-sm">Import Staff CSV</button>
        </form>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Designation</th><th>Department</th><th>Office</th><th>Active</th></tr></thead>
                <tbody>
                    <?php if (empty($staff)): ?>
                        <tr><td colspan="6">No staff imported yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($staff as $person): ?>
                            <tr>
                                <td><?= htmlspecialchars($person['staff_id'] ?? $person['id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($person['full_name'] ?? $person['name'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($person['designation'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($person['department_id'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($person['office_id'] ?? ''); ?></td>
                                <td><?= !empty($person['active']) ? 'Yes' : 'No'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
