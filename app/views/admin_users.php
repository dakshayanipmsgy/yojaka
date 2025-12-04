<?php
$users = load_users();
?>
<div class="info">
    <p>This is a read-only snapshot of registered users. User management features will be introduced in upcoming versions.</p>
</div>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Active</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="6">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['username'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($user['full_name'] ?? ''); ?></td>
                        <td><span class="badge"><?= htmlspecialchars($user['role'] ?? ''); ?></span></td>
                        <td><?= !empty($user['active']) ? 'Yes' : 'No'; ?></td>
                        <td><?= htmlspecialchars($user['created_at'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
