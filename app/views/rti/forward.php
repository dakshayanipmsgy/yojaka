<section class="page-intro">
    <div class="panel-header">
        <h1>Forward RTI</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id'] ?? '')); ?>">Back to case</a>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo yojaka_escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <form method="post">
        <label>Forward to step
            <select name="next_step" required>
                <option value="">Choose</option>
                <?php foreach ($allowedSteps as $step): ?>
                    <option value="<?php echo yojaka_escape($step['id'] ?? ''); ?>" <?php echo (($selection['next_step'] ?? '') === ($step['id'] ?? '')) ? 'selected' : ''; ?>><?php echo yojaka_escape($step['label'] ?? ($step['id'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Assign to user
            <input type="text" name="assignee_username" value="<?php echo yojaka_escape($selection['assignee_username'] ?? ''); ?>" placeholder="login identity">
        </label>

        <label>Comment
            <textarea name="comment" rows="3"><?php echo yojaka_escape($selection['comment'] ?? ''); ?></textarea>
        </label>

        <button type="submit">Forward</button>
    </form>
</section>
