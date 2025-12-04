<?php
require_login();
require_permission('manage_people');

$currentOfficeId = get_current_office_id();
$staffList = load_staff($currentOfficeId);
$users = load_users();
$positions = load_positions($currentOfficeId);
$positionHistory = load_position_history($currentOfficeId);
$errors = [];
$success = '';

function parse_uploaded_csv(string $field): array
{
    if (empty($_FILES[$field]['tmp_name'])) {
        return [];
    }
    $rows = [];
    if (($handle = fopen($_FILES[$field]['tmp_name'], 'r')) !== false) {
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[trim((string) $header)] = $data[$index] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'import_staff') {
        $csvRows = parse_uploaded_csv('staff_csv');
        if (empty($csvRows)) {
            $errors[] = 'No data found in staff CSV.';
        } else {
            foreach ($csvRows as $row) {
                $staffId = trim($row['staff_id'] ?? '');
                if ($staffId === '') {
                    $errors[] = 'Missing staff_id in one of the rows.';
                    break;
                }
                $existing = find_staff_by_id($currentOfficeId, $staffId);
                if ($existing) {
                    foreach ($staffList as &$staff) {
                        if ($staff['staff_id'] === $staffId) {
                            $staff = array_merge($staff, $row);
                        }
                    }
                    unset($staff);
                } else {
                    $staffList[] = $row;
                }
            }
            if (empty($errors) && save_staff($currentOfficeId, $staffList)) {
                $success = 'Staff imported successfully.';
            }
        }
    }
    if ($action === 'assign_position') {
        $positionId = trim($_POST['position_id'] ?? '');
        $staffId = trim($_POST['staff_id'] ?? '') ?: null;
        $username = trim($_POST['username'] ?? '') ?: null;
        $from = $_POST['from'] ?? gmdate('c');
        if ($positionId === '') {
            $errors[] = 'Position is required for assignment.';
        } else {
            if (assign_staff_to_position($currentOfficeId, $positionId, $staffId, $username, $from)) {
                $success = 'Assignment updated.';
                $positionHistory = load_position_history($currentOfficeId);
            } else {
                $errors[] = 'Failed to save assignment.';
            }
        }
    }
    if ($action === 'import_users') {
        $csvRows = parse_uploaded_csv('users_csv');
        if (empty($csvRows)) {
            $errors[] = 'No data found in users CSV.';
        } else {
            foreach ($csvRows as $row) {
                $username = trim($row['username'] ?? '');
                $role = trim($row['role'] ?? 'user');
                $staffId = trim($row['staff_id'] ?? '');
                if ($username === '') {
                    $errors[] = 'Missing username in one of the rows.';
                    break;
                }
                if ($staffId !== '' && !find_staff_by_id($currentOfficeId, $staffId)) {
                    $newStaff = [
                        'staff_id' => $staffId,
                        'full_name' => $row['full_name'] ?? $username,
                        'designation' => $row['designation'] ?? ($row['role'] ?? ''),
                        'department_id' => $row['department_id'] ?? null,
                        'active' => true,
                    ];
                    $staffList[] = $newStaff;
                }
                save_user([
                    'username' => $username,
                    'full_name' => $row['full_name'] ?? $username,
                    'role' => $role,
                    'office_id' => $row['office_id'] ?? $currentOfficeId,
                    'department_id' => $row['department_id'] ?? null,
                    'staff_id' => $staffId !== '' ? $staffId : null,
                    'password' => $row['password'] ?? bin2hex(random_bytes(4)),
                    'active' => ($row['active'] ?? 'true') !== 'false',
                ]);
            }
            save_staff($currentOfficeId, $staffList);
            if (empty($errors)) {
                $success = 'Users imported successfully.';
            }
        }
    }
    if ($action === 'import_positions') {
        $csvRows = parse_uploaded_csv('positions_csv');
        if (empty($csvRows)) {
            $errors[] = 'No data found in positions CSV.';
        } else {
            foreach ($csvRows as $row) {
                $positionId = trim($row['position_id'] ?? '');
                if ($positionId === '') {
                    $errors[] = 'Missing position_id in one of the rows.';
                    break;
                }
                $positions[] = [
                    'position_id' => $positionId,
                    'office_id' => $row['office_id'] ?? $currentOfficeId,
                    'department_id' => $row['department_id'] ?? null,
                    'post_id' => $row['post_id'] ?? null,
                    'title' => $row['title'] ?? $positionId,
                    'reports_to' => $row['reports_to'] ?? null,
                    'active' => true,
                ];
            }
            if (empty($errors) && save_positions($currentOfficeId, $positions)) {
                $success = 'Positions imported successfully.';
            }
        }
    }
}
?>
<div class="card">
    <div class="flex" style="justify-content: space-between; align-items:center;">
        <h2>People & Users</h2>
        <a class="btn" target="_blank" href="<?= YOJAKA_BASE_URL ?>/app.php?page=help_user_roles">Help</a>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="alert error"><?= htmlspecialchars(implode(' ', $errors)); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <div class="tabs">
        <details open>
            <summary><strong>Staff</strong></summary>
            <p>Manage staff profiles (people). Link them to users and positions.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_staff">
                <label>Import Staff CSV
                    <input type="file" name="staff_csv" accept=".csv">
                </label>
                <button type="submit" class="btn">Upload Staff CSV</button>
                <a class="btn ghost" href="<?= YOJAKA_BASE_URL ?>/samples/staff_sample.csv" target="_blank">Download Sample Staff CSV</a>
            </form>
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Designation</th><th>Department</th><th>Active</th></tr></thead>
                <tbody>
                <?php foreach ($staffList as $staff): ?>
                    <tr>
                        <td><?= htmlspecialchars($staff['staff_id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($staff['full_name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($staff['designation'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($staff['department_id'] ?? ''); ?></td>
                        <td><?= !empty($staff['active']) ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <details>
            <summary><strong>Users</strong></summary>
            <p>Create logins linked to staff members. When staff_id is provided the linkage is stored with the user.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_users">
                <label>Import Users CSV
                    <input type="file" name="users_csv" accept=".csv">
                </label>
                <button type="submit" class="btn">Upload Users CSV</button>
                <a class="btn ghost" href="<?= YOJAKA_BASE_URL ?>/samples/users_sample.csv" target="_blank">Download Sample Users CSV</a>
            </form>
            <table class="table">
                <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Office</th><th>Department</th><th>Staff</th><th>Active</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['full_name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['role'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['office_id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['department_id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['staff_id'] ?? ''); ?></td>
                        <td><?= !empty($user['active']) ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <details>
            <summary><strong>Position Assignments</strong></summary>
            <p>Assign staff to seats. History is recorded in <code>position_history.json</code>.</p>
            <form method="post">
                <input type="hidden" name="action" value="assign_position">
                <div class="form-grid">
                    <label>Position
                        <select name="position_id">
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= htmlspecialchars($pos['position_id'] ?? $pos['id'] ?? ''); ?>">
                                    <?= htmlspecialchars(($pos['title'] ?? $pos['position_id'] ?? '') . ' [' . ($pos['department_id'] ?? '') . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Staff
                        <select name="staff_id">
                            <option value="">(Vacant)</option>
                            <?php foreach ($staffList as $staff): ?>
                                <option value="<?= htmlspecialchars($staff['staff_id'] ?? ''); ?>"><?= htmlspecialchars($staff['full_name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Username (optional)
                        <input type="text" name="username" placeholder="Link user login">
                    </label>
                    <label>Effective From
                        <input type="datetime-local" name="from" value="<?= htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                    </label>
                    <button type="submit" class="btn primary">Assign / Transfer</button>
                </div>
            </form>
            <table class="table">
                <thead><tr><th>Position</th><th>Current Holder</th><th>From</th><th>To</th></tr></thead>
                <tbody>
                <?php foreach ($positionHistory as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['position_id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($entry['staff_id'] ?? '(vacant)'); ?></td>
                        <td><?= htmlspecialchars($entry['from'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($entry['to'] ?? 'Present'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="muted">History is stored with from/to timestamps to keep old movements accurate.</div>
            <div class="actions">
                <a class="btn ghost" href="<?= YOJAKA_BASE_URL ?>/samples/positions_sample.csv" target="_blank">Download Sample Positions CSV</a>
                <form method="post" enctype="multipart/form-data" style="display:inline-block">
                    <input type="hidden" name="action" value="import_positions">
                    <input type="file" name="positions_csv" accept=".csv">
                    <button type="submit" class="btn">Import Positions CSV</button>
                </form>
            </div>
        </details>
    </div>
</div>
