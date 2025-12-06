<section class="page-intro">
    <h1>Create Letter</h1>
    <p>Select a template and fill in the required details to draft a new letter.</p>
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

<?php if (empty($templates)): ?>
    <p>No templates available. Please contact your administrator.</p>
<?php elseif (!$templateId): ?>
    <section class="panel">
        <form method="get" action="<?php echo yojaka_url('index.php'); ?>">
            <input type="hidden" name="r" value="letters/create">
            <div class="form-group">
                <label for="template_id">Choose a Template</label>
                <select id="template_id" name="template_id">
                    <?php foreach ($templates as $tpl): ?>
                        <option value="<?php echo yojaka_escape($tpl['id'] ?? ''); ?>"><?php echo yojaka_escape($tpl['name'] ?? $tpl['id'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button">Use Template</button>
        </form>
    </section>
<?php else: ?>
    <section class="panel">
        <form method="post" action="<?php echo yojaka_url('index.php?r=letters/create&template_id=' . urlencode($templateId)); ?>">
            <input type="hidden" name="template_id" value="<?php echo yojaka_escape($templateId); ?>">

            <div class="form-grid">
                <?php foreach (($template['placeholders'] ?? []) as $placeholder): ?>
                    <?php $key = $placeholder['key'] ?? ''; ?>
                    <?php if ($key === '') { continue; } ?>
                    <div class="form-group">
                        <label for="<?php echo yojaka_escape($key); ?>"><?php echo yojaka_escape($placeholder['label'] ?? $key); ?></label>
                        <?php if ($key === 'body' || $key === 'to_address'): ?>
                            <textarea id="<?php echo yojaka_escape($key); ?>" name="<?php echo yojaka_escape($key); ?>" rows="4"><?php echo yojaka_escape($_POST[$key] ?? ''); ?></textarea>
                        <?php elseif ($key === 'letter_date'): ?>
                            <input type="date" id="<?php echo yojaka_escape($key); ?>" name="<?php echo yojaka_escape($key); ?>" value="<?php echo yojaka_escape($_POST[$key] ?? date('Y-m-d')); ?>">
                        <?php else: ?>
                            <input type="text" id="<?php echo yojaka_escape($key); ?>" name="<?php echo yojaka_escape($key); ?>" value="<?php echo yojaka_escape($_POST[$key] ?? ''); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="button">Save Letter</button>
            <a class="button secondary" href="<?php echo yojaka_url('index.php?r=letters/create'); ?>">Choose different template</a>
        </form>
    </section>
<?php endif; ?>
