<section class="page-intro">
    <h1>Department Reports Dashboard</h1>
    <p>Summary of dak and letter activity for your department.</p>
</section>

<section class="panel">
    <h2>Dak Statistics</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Dak</td>
                <td><?php echo yojaka_escape($dakStatusCounts['total'] ?? 0); ?></td>
            </tr>
            <tr>
                <td>Open / In Progress</td>
                <td><?php echo yojaka_escape($dakStatusCounts['open'] ?? 0); ?></td>
            </tr>
            <tr>
                <td>Closed</td>
                <td><?php echo yojaka_escape($dakStatusCounts['closed'] ?? 0); ?></td>
            </tr>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Letter Statistics</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Letters</td>
                <td><?php echo yojaka_escape($letterStatusCounts['total'] ?? 0); ?></td>
            </tr>
            <tr>
                <td>Draft</td>
                <td><?php echo yojaka_escape($letterStatusCounts['draft'] ?? 0); ?></td>
            </tr>
            <tr>
                <td>Finalized</td>
                <td><?php echo yojaka_escape($letterStatusCounts['finalized'] ?? 0); ?></td>
            </tr>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Workload by User</h2>
    <?php if (empty($workload)): ?>
        <p>No users found for this department.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Dak Assigned</th>
                        <th>Letters Assigned</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workload as $row): ?>
                        <tr>
                            <td><?php echo yojaka_escape($row['label'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($row['dak'] ?? 0); ?></td>
                            <td><?php echo yojaka_escape($row['letters'] ?? 0); ?></td>
                            <td><?php echo yojaka_escape($row['total'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Dak by Workflow Step</h2>
    <?php if (empty($workflowSteps)): ?>
        <p>No workflow data available.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Step</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workflowSteps as $step): ?>
                        <tr>
                            <td><?php echo yojaka_escape($step['label'] ?? ($step['id'] ?? '')); ?></td>
                            <td><?php echo yojaka_escape($step['count'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Time-Based Activity</h2>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Dak Created</th>
                    <th>Letters Created</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Today</td>
                    <td><?php echo yojaka_escape($timeMetrics['dak']['today'] ?? 0); ?></td>
                    <td><?php echo yojaka_escape($timeMetrics['letters']['today'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td>This Week</td>
                    <td><?php echo yojaka_escape($timeMetrics['dak']['week'] ?? 0); ?></td>
                    <td><?php echo yojaka_escape($timeMetrics['letters']['week'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td>This Month</td>
                    <td><?php echo yojaka_escape($timeMetrics['dak']['month'] ?? 0); ?></td>
                    <td><?php echo yojaka_escape($timeMetrics['letters']['month'] ?? 0); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
