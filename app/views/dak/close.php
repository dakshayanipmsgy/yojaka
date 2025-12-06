<section class="page-intro">
    <div class="panel-header">
        <h1>Close Dak</h1>
        <a class="button" href="<?php echo yojaka_url('index.php?r=dak/view&id=' . urlencode($record['id'] ?? '')); ?>">Back to Dak</a>
    </div>
    <p>Confirm closing this dak at the terminal step <strong><?php echo yojaka_escape($currentStep['label'] ?? $currentStep['id'] ?? ''); ?></strong>.</p>
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

    <form method="post">
        <div class="form-group">
            <label for="comment">Closing Comment</label>
            <textarea id="comment" name="comment" rows="4" placeholder="Optional closing remarks."><?php echo yojaka_escape($_POST['comment'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button">Confirm Close</button>
    </form>
</section>
