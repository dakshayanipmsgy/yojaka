<?php
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $id = $_POST['mark_read'];
        mark_notification_as_read($id, $user['username']);
    }
    if (isset($_POST['mark_all'])) {
        mark_all_notifications_as_read($user['username']);
    }
    header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=notifications');
    exit;
}

$notifications = get_notifications_for_user($user['username']);
?>
<div class="form-actions" style="justify-content: flex-end;">
    <form method="post" style="margin:0;">
        <button class="btn" type="submit" name="mark_all" value="1">Mark all as read</button>
    </form>
</div>
<div class="table-responsive">
    <table class="table">
        <thead><tr><th>Title</th><th>Message</th><th>Module</th><th>Created</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($notifications)): ?>
            <tr><td colspan="6">No notifications yet.</td></tr>
        <?php else: ?>
            <?php foreach ($notifications as $note): ?>
                <tr class="<?= empty($note['read_at']) ? 'highlight' : ''; ?>">
                    <td><?= htmlspecialchars($note['title'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($note['message'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($note['module'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($note['created_at'] ?? ''); ?></td>
                    <td><?= empty($note['read_at']) ? 'Unread' : 'Read'; ?></td>
                    <td style="display:flex; gap:0.5rem;">
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="mark_read" value="<?= htmlspecialchars($note['id'] ?? ''); ?>">
                            <button class="btn" type="submit">Mark read</button>
                        </form>
                        <?php if (!empty($note['entity_id']) && !empty($note['module'])): ?>
                            <?php
                            $pageMap = [
                                'rti' => 'rti',
                                'dak' => 'dak',
                                'bills' => 'bills',
                            ];
                            $targetPage = $pageMap[$note['module']] ?? null;
                            ?>
                            <?php if ($targetPage): ?>
                                <a class="btn" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=<?= urlencode($targetPage); ?>&mode=view&id=<?= urlencode($note['entity_id']); ?>">Open</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
