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
        <h2>Workflows</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/workflows'); ?>">View Workflows</a>
    </div>
    <p>Review the current dak routing template for your department. A richer editor will be added soon.</p>
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
        <h2>Branding / Letterhead</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/branding/letterhead'); ?>">Configure Branding</a>
    </div>
    <p>Manage department name, address, logo, and header/footer blocks used by letters, print, and PDFs.</p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Dak</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Dak (department records)</a>
    </div>
    <p>View and manage dak entries that your account can access.</p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Letters &amp; Notices</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=letters/list'); ?>">Letters</a>
    </div>
    <p>Use templates to draft official letters for your department.</p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Reports Dashboard</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/reports'); ?>">View Reports</a>
    </div>
    <p>Review dak and letter statistics, workload summaries, and activity trends.</p>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Audit Log</h2>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/audit'); ?>">View Audit Log</a>
    </div>
    <p>Review recent administrative and dak actions recorded for this department.</p>
</section>
