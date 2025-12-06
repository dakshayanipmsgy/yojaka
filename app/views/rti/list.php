<section class="page-intro">
    <div class="panel-header">
        <h1>RTI Cases</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=rti/create'); ?>">Register New RTI</a>
        </div>
    </div>
</section>

<section class="panel">
    <h2>Filters</h2>
    <form method="get" action="<?php echo yojaka_url('index.php'); ?>">
        <input type="hidden" name="r" value="rti/list">
        <div class="form-grid">
            <label>Search
                <input type="text" name="q" value="<?php echo yojaka_escape($filters['q'] ?? ''); ?>" placeholder="Applicant, subject, RTI number">
            </label>
            <label>Status
                <input type="text" name="status" value="<?php echo yojaka_escape($filters['status'] ?? ''); ?>" placeholder="Status">
            </label>
            <label>Received from
                <input type="date" name="received_from" value="<?php echo yojaka_escape($filters['received_from'] ?? ''); ?>">
            </label>
            <label>Received to
                <input type="date" name="received_to" value="<?php echo yojaka_escape($filters['received_to'] ?? ''); ?>">
            </label>
            <label>Due from
                <input type="date" name="due_from" value="<?php echo yojaka_escape($filters['due_from'] ?? ''); ?>">
            </label>
            <label>Due to
                <input type="date" name="due_to" value="<?php echo yojaka_escape($filters['due_to'] ?? ''); ?>">
            </label>
        </div>
        <button type="submit">Apply</button>
    </form>
</section>

<section class="panel">
    <h2>Cases</h2>
    <?php if (empty($records)): ?>
        <p>No RTI cases found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>RTI Number</th>
                        <th>Applicant</th>
                        <th>Subject</th>
                        <th>Received</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Current Step</th>
                        <th>Assignee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <?php
                            $dueDate = $record['dates']['due_date'] ?? '';
                            $dueClass = '';
                            if ($dueDate !== '') {
                                $today = date('Y-m-d');
                                if ($today > $dueDate && ($record['status'] ?? '') !== 'closed') {
                                    $dueClass = 'text-danger';
                                } elseif ($today >= date('Y-m-d', strtotime($dueDate . ' -5 days'))) {
                                    $dueClass = 'text-warning';
                                }
                            }
                        ?>
                        <tr>
                            <td><a href="<?php echo yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id'] ?? '')); ?>"><?php echo yojaka_escape($record['id'] ?? ''); ?></a></td>
                            <td><?php echo yojaka_escape($record['basic']['rti_number'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['basic']['applicant_name'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['basic']['subject'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['basic']['received_date'] ?? ''); ?></td>
                            <td class="<?php echo $dueClass; ?>"><?php echo yojaka_escape($dueDate); ?></td>
                            <td><?php echo yojaka_escape($record['status'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['workflow']['current_step'] ?? ''); ?></td>
                            <td><?php echo yojaka_escape($record['assignee_username'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
