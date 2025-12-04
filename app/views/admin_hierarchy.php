<?php
require_login();
require_permission('manage_office_config');
$currentOfficeId = get_current_office_id();
$errors = [];
$success = '';
$posts = load_posts($currentOfficeId);
$positions = load_positions($currentOfficeId);
$users = load_users();
$departments = load_departments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_post') {
        $postId = trim($_POST['post_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $short = trim($_POST['short_name'] ?? '');
        $level = (int)($_POST['level'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $errors[] = 'Post name is required.';
        }
        if (empty($errors)) {
            $found = false;
            foreach ($posts as &$p) {
                if (($p['id'] ?? '') === $postId) {
                    $p['name'] = $name;
                    $p['short_name'] = $short;
                    $p['level'] = $level;
                    $p['description'] = $desc;
                    $found = true;
                    break;
                }
            }
            unset($p);
            if (!$found) {
                $posts[] = [
                    'id' => $postId !== '' ? $postId : 'POST-' . strtoupper(bin2hex(random_bytes(2))),
                    'name' => $name,
                    'short_name' => $short,
                    'level' => $level,
                    'description' => $desc,
                    'office_id' => $currentOfficeId,
                ];
            }
            save_posts($currentOfficeId, $posts);
            log_event('hierarchy_changed', current_user()['username'] ?? null, ['type' => 'post', 'id' => $postId]);
            $success = 'Post saved successfully.';
        }
    }
    if ($action === 'save_position') {
        $title = trim($_POST['title'] ?? '');
        $postId = trim($_POST['post_id'] ?? '');
        $departmentId = trim($_POST['department_id'] ?? '');
        $reportsTo = trim($_POST['reports_to'] ?? '');
        $userUsername = trim($_POST['user_username'] ?? '');
        $posId = trim($_POST['position_id'] ?? '');
        if ($title === '' || $postId === '') {
            $errors[] = 'Title and post are required.';
        }
        if (empty($errors)) {
            $isUpdate = $posId !== '';
            if ($isUpdate) {
                foreach ($positions as &$p) {
                    if (($p['id'] ?? '') === $posId) {
                        $p['title'] = $title;
                        $p['post_id'] = $postId;
                        $p['department_id'] = $departmentId;
                        $p['reports_to'] = $reportsTo !== '' ? $reportsTo : null;
                        $p['user_username'] = $userUsername !== '' ? $userUsername : null;
                        break;
                    }
                }
                unset($p);
            } else {
                $positions[] = [
                    'id' => generate_position_id($positions),
                    'office_id' => $currentOfficeId,
                    'department_id' => $departmentId,
                    'post_id' => $postId,
                    'title' => $title,
                    'reports_to' => $reportsTo !== '' ? $reportsTo : null,
                    'user_username' => $userUsername !== '' ? $userUsername : null,
                    'staff_id' => null,
                    'active' => true,
                ];
            }
            save_positions($currentOfficeId, $positions);
            log_event('hierarchy_changed', current_user()['username'] ?? null, ['type' => 'position', 'id' => $posId]);
            $success = 'Position saved successfully.';
        }
    }
}

function render_position_tree(array $positions, ?string $parentId = null, int $depth = 0): void
{
    foreach ($positions as $pos) {
        if (($pos['reports_to'] ?? null) === $parentId) {
            echo '<div style="margin-left:' . (20 * $depth) . 'px">';
            echo htmlspecialchars($pos['title'] ?? $pos['id']);
            if (!empty($pos['user_username'])) {
                echo ' <span class="muted">(' . htmlspecialchars($pos['user_username']) . ')</span>';
            }
            echo '</div>';
            render_position_tree($positions, $pos['id'] ?? null, $depth + 1);
        }
    }
}
?>
<div class="grid">
    <div class="card">
        <h3>Posts / Ranks</h3>
        <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="alert error"><?= htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>
        <table class="table">
            <thead><tr><th>ID</th><th>Name</th><th>Level</th><th>Short</th></tr></thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><?= htmlspecialchars($post['id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($post['name'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($post['level'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($post['short_name'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post">
            <input type="hidden" name="action" value="save_post">
            <label>Post ID (leave blank for new)
                <input type="text" name="post_id">
            </label>
            <label>Name
                <input type="text" name="name" required>
            </label>
            <label>Short Name
                <input type="text" name="short_name">
            </label>
            <label>Level
                <input type="number" name="level" value="0">
            </label>
            <label>Description
                <textarea name="description" rows="2"></textarea>
            </label>
            <button type="submit" class="btn primary">Save Post</button>
        </form>
    </div>
    <div class="card">
        <h3>Positions / Hierarchy</h3>
        <div class="muted">Linked to departments and users with reporting lines.</div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_position">
            <label>Existing Position ID (for edit)
                <input type="text" name="position_id">
            </label>
            <label>Title
                <input type="text" name="title" required>
            </label>
            <label>Post
                <select name="post_id" required>
                    <option value="">Select</option>
                    <?php foreach ($posts as $post): ?>
                        <option value="<?= htmlspecialchars($post['id'] ?? ''); ?>"><?= htmlspecialchars($post['name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Department
                <select name="department_id">
                    <option value="">(None)</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['id'] ?? ''); ?>"><?= htmlspecialchars($dept['name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Reports To
                <select name="reports_to">
                    <option value="">Top level</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?= htmlspecialchars($pos['id'] ?? ''); ?>"><?= htmlspecialchars($pos['title'] ?? $pos['id']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Assign User
                <select name="user_username">
                    <option value="">(Unassigned)</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= htmlspecialchars($u['username'] ?? ''); ?>"><?= htmlspecialchars(($u['full_name'] ?? $u['username'] ?? '') . ' (' . ($u['username'] ?? '') . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn primary">Save Position</button>
        </form>
        <h4>Hierarchy Tree</h4>
        <div class="card muted">
            <?php render_position_tree($positions); ?>
        </div>
    </div>
</div>
