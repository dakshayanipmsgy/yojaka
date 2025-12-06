<section class="page-intro">
    <h1>Department Admin Dashboard</h1>
    <p>Manage roles and configuration for your department.</p>
</section>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo yojaka_escape($message); ?></div>
<?php endif; ?>

<section class="panel">
    <h2>Department Overview</h2>
    <?php if (!empty($department)): ?>
        <ul class="meta-list">
            <li><strong>Name:</strong> <?php echo yojaka_escape($department['name'] ?? ''); ?></li>
            <li><strong>Slug:</strong> <?php echo yojaka_escape($department['slug'] ?? ''); ?></li>
            <li><strong>Status:</strong> <?php echo yojaka_escape($department['status'] ?? ''); ?></li>
        </ul>
    <?php else: ?>
        <p>Department information not available.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Roles</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/roles/create'); ?>">Create New Role</a>
    </div>

    <?php if (empty($roles)): ?>
        <p>No roles created yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Role ID</th>
                        <th>Label</th>
                        <th>Permissions</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?php echo yojaka_escape($role['role_id'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($role['label'] ?? ''); ?></td>
                            <td><?php echo count($role['permissions'] ?? []); ?> permissions</td>
                            <td><?php echo yojaka_escape($role['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Users</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/users'); ?>">Manage Users</a>
    </div>
    <p>Use the Users area to onboard officers, assign roles, and manage their login identities.</p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Dak</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Dak (department records)</a>
    </div>
    <p>View and manage dak entries that your account can access.</p>
</section>
