<section class="page-intro">
    <h1>Edit Letter</h1>
    <p>Update fields for this letter while it remains in draft.</p>
</section>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo yojaka_escape($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="panel">
    <form method="post" action="<?php echo yojaka_url('index.php?r=letters/edit&id=' . urlencode($record['id'] ?? '')); ?>">
        <div class="form-grid">
            <?php foreach (($template['placeholders'] ?? []) as $placeholder): ?>
                <?php $key = $placeholder['key'] ?? ''; ?>
                <?php if ($key === '') { continue; } ?>
                <div class="form-group">
                    <label for="<?php echo yojaka_escape($key); ?>"><?php echo yojaka_escape($placeholder['label'] ?? $key); ?></label>
                    <?php if ($key === 'body' || $key === 'to_address'): ?>
                        <textarea id="<?php echo yojaka_escape($key); ?>" name="<?php echo yojaka_escape($key); ?>" rows="4"><?php echo yojaka_escape($_POST[$key] ?? ($record['fields'][$key] ?? '')); ?></textarea>
                    <?php elseif ($key === 'letter_date'): ?>
                        <input type="date" id="<?php echo yojaka_escape($key); ?>" name="<?php echo yojaka_escape($key); ?>" value="<?php echo yojaka_escape($_POST[$key] ?? ($record['fields'][$key] ?? date('Y-m-d'))); ?>">
                    <?php else: ?>
                        <input type="text" id="<?php echo yojaka_escape($key); ?>" name="<?php echo yojaka_escape($key); ?>" value="<?php echo yojaka_escape($_POST[$key] ?? ($record['fields'][$key] ?? '')); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="button">Update Letter</button>
        <a class="button secondary" href="<?php echo yojaka_url('index.php?r=letters/view&id=' . urlencode($record['id'] ?? '')); ?>">Cancel</a>
    </form>
</section>
