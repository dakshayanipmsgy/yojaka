<section class="page-intro">
    <div class="panel-header">
        <h1>Close RTI</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id'] ?? '')); ?>">Back to case</a>
        </div>
    </div>
</section>

<section class="panel">
    <p>Closing this RTI will mark it as complete. You can add an optional comment below.</p>
    <form method="post">
        <label>Comment
            <textarea name="comment" rows="4"><?php echo yojaka_escape($comment ?? ''); ?></textarea>
        </label>
        <button type="submit">Confirm Close</button>
    </form>
</section>
