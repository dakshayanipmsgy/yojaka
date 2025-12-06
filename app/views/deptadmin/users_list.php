<section class="page-intro">
    <h1>Department Users</h1>
    <p>Manage officer identities and their role-based login accounts.</p>
    <div class="panel-actions">
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/users/create'); ?>">Create User</a>
    </div>
</section>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo yojaka_escape($message); ?></div>
<?php endif; ?>

<section class="panel">
    <h2>Users</h2>
    <?php if (empty($users)): ?>
        <p>No users added yet.</p>
    <?php else: ?>
        <?php
        $roleLabels = [];
        foreach ($roles as $role) {
            $roleId = $role['role_id'] ?? '';
            $label = $role['label'] ?? $roleId;
            if ($roleId !== '') {
                $roleLabels[$roleId] = $label;
            }
        }
        ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username Base</th>
                        <th>Display Name</th>
                        <th>Roles</th>
                        <th>Login Identities</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo yojaka_escape($user['username_base'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($user['display_name'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($user['role_ids'])): ?>
                                    <ul class="meta-list">
                                        <?php foreach ($user['role_ids'] as $rid): ?>
                                            <li><?php echo yojaka_escape($rid); ?> <?php echo isset($roleLabels[$rid]) ? '(' . yojaka_escape($roleLabels[$rid]) . ')' : ''; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($user['login_identities'])): ?>
                                    <ul class="meta-list">
                                        <?php foreach ($user['login_identities'] as $identity): ?>
                                            <li><?php echo yojaka_escape($identity); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($user['status'] ?? '') === 'active'): ?>
                                    <span class="status status-active">Active</span>
                                <?php else: ?>
                                    <span class="status status-disabled">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a class="button button-small" href="<?php echo yojaka_url('index.php?r=deptadmin/users/edit&id=' . urlencode($user['id'] ?? '')); ?>">Edit</a>
                                    <a class="button button-small" href="<?php echo yojaka_url('index.php?r=deptadmin/users/password&id=' . urlencode($user['id'] ?? '')); ?>">Password</a>
                                    <form method="post" action="<?php echo yojaka_url('index.php?r=deptadmin/users'); ?>" class="inline-form">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo yojaka_escape($user['id'] ?? ''); ?>">
                                        <button type="submit" class="button button-small button-secondary">
                                            <?php echo (($user['status'] ?? '') === 'active') ? 'Disable' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
