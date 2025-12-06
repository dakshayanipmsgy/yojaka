<section class="page-intro">
    <div class="panel-header">
        <h1>Department Workflows</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=deptadmin/dashboard'); ?>">Back to Dashboard</a>
    </div>
    <p>Workflows define how dak files move through your department. Editing tools will arrive in a later release.</p>
</section>

<section class="panel">
    <?php if (empty($workflows)): ?>
        <p>No workflows configured yet. A default dak route will be created automatically for new departments.</p>
    <?php else: ?>
        <?php foreach ($workflows as $workflow): ?>
            <div class="workflow-card">
                <h2><?php echo yojaka_escape($workflow['name'] ?? ''); ?> <small>(<?php echo yojaka_escape($workflow['id'] ?? ''); ?>)</small></h2>
                <p class="muted">Module: <?php echo yojaka_escape($workflow['module'] ?? ''); ?></p>
                <?php if (!empty($workflow['description'])): ?>
                    <p><?php echo yojaka_escape($workflow['description']); ?></p>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Step ID</th>
                                <th>Label</th>
                                <th>Allowed Roles</th>
                                <th>Forward To</th>
                                <th>Return To</th>
                                <th>Terminal?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workflow['steps'] ?? [] as $step): ?>
                                <tr>
                                    <td><?php echo yojaka_escape($step['id'] ?? ''); ?></td>
                                    <td><?php echo yojaka_escape($step['label'] ?? ''); ?></td>
                                    <td><?php echo !empty($step['allowed_roles']) ? yojaka_escape(implode(', ', $step['allowed_roles'])) : '—'; ?></td>
                                    <td><?php echo !empty($step['allow_forward_to']) ? yojaka_escape(implode(', ', $step['allow_forward_to'])) : '—'; ?></td>
                                    <td><?php echo !empty($step['allow_return_to']) ? yojaka_escape(implode(', ', $step['allow_return_to'])) : '—'; ?></td>
                                    <td><?php echo !empty($step['is_terminal']) ? 'Yes' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
