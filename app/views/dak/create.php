<section class="page-intro">
    <div class="panel-header">
        <h1>Create Dak</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/list'); ?>">Back to List</a>
    </div>
    <p>Register a new incoming dak record.</p>
</section>

<section class="panel">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo yojaka_escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="form-grid">
            <label>Title*<br>
                <input type="text" name="title" value="<?php echo yojaka_escape($values['title'] ?? ''); ?>" required>
            </label>

            <label>Reference No.<br>
                <input type="text" name="reference_no" value="<?php echo yojaka_escape($values['reference_no'] ?? ''); ?>">
            </label>

            <label>Received Date<br>
                <input type="date" name="received_date" value="<?php echo yojaka_escape($values['received_date'] ?? ''); ?>">
            </label>

            <label>Received Via<br>
                <select name="received_via">
                    <?php $via = $values['received_via'] ?? 'post'; ?>
                    <option value="post" <?php echo $via === 'post' ? 'selected' : ''; ?>>Post</option>
                    <option value="email" <?php echo $via === 'email' ? 'selected' : ''; ?>>Email</option>
                    <option value="by_hand" <?php echo $via === 'by_hand' ? 'selected' : ''; ?>>By hand</option>
                    <option value="other" <?php echo $via === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </label>

            <label>From (Name)<br>
                <input type="text" name="from_name" value="<?php echo yojaka_escape($values['from_name'] ?? ''); ?>">
            </label>

            <label>From (Address)<br>
                <textarea name="from_address" rows="3"><?php echo yojaka_escape($values['from_address'] ?? ''); ?></textarea>
            </label>

            <label>Subject<br>
                <input type="text" name="subject" value="<?php echo yojaka_escape($values['subject'] ?? ''); ?>">
            </label>

            <label>Remarks<br>
                <textarea name="remarks" rows="3"><?php echo yojaka_escape($values['remarks'] ?? ''); ?></textarea>
            </label>
        </div>

        <p class="form-actions">
            <button type="submit" class="button">Create Dak</button>
        </p>
    </form>
</section>
