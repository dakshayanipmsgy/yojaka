<?php
$permissions = load_permissions_config();
?>
<div class="card">
    <h2>Roles &amp; Permissions</h2>
    <p>System roles and any custom roles defined for this office are shown below. Edit <code>data/org/permissions.json</code> directly to extend permissions.</p>
    <div class="grid">
        <div>
            <h3>System Roles</h3>
            <ul>
                <?php foreach (($permissions['roles'] ?? []) as $role => $perms): ?>
                    <li><strong><?= htmlspecialchars($role); ?></strong>: <?= htmlspecialchars(implode(', ', $perms)); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h3>Custom Roles</h3>
            <ul>
                <?php foreach (($permissions['custom_roles'] ?? []) as $role => $perms): ?>
                    <li><strong><?= htmlspecialchars($role); ?></strong>: <?= htmlspecialchars(implode(', ', $perms)); ?></li>
                <?php endforeach; ?>
                <?php if (empty($permissions['custom_roles'])): ?>
                    <li>No custom roles defined yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
