<section class="page-intro">
    <div class="panel-header">
        <h1>Return Dak</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id'] ?? '')); ?>">Back to Dak</a>
    </div>
    <p>Send this dak back to an earlier workflow step.</p>
</section>

<section class="panel">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo yojaka_escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($allowedSteps)): ?>
        <p>No return options are available from the current step.</p>
    <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label for="to_step">Return To</label>
                <select id="to_step" name="to_step" required>
                    <option value="">-- Select step --</option>
                    <?php foreach ($allowedSteps as $step): ?>
                        <option value="<?php echo yojaka_escape($step['id'] ?? ''); ?>"><?php echo yojaka_escape($step['label'] ?? ($step['id'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="to_user">Assign to user (login identity)</label>
                <input type="text" id="to_user" name="to_user" placeholder="username.role.department" value="<?php echo yojaka_escape($_POST['to_user'] ?? ''); ?>">
                <p class="muted">Leave blank to keep yourself as the assignee.</p>
            </div>

            <div class="form-group">
                <label for="comment">Comment</label>
                <textarea id="comment" name="comment" rows="4" placeholder="Optional notes to include in history."><?php echo yojaka_escape($_POST['comment'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="button">Return</button>
        </form>
    <?php endif; ?>
</section>
