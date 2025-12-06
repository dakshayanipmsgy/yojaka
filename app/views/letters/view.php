<section class="page-intro">
    <div class="panel-header">
        <h1>Letter Details</h1>
        <div>
            <?php if (!empty($canEdit)): ?>
                <a class="button" href="<?php echo yojaka_url('index.php?r=letters/edit&id=' . urlencode($record['id'] ?? '')); ?>">Edit</a>
            <?php endif; ?>
            <a class="button secondary" href="<?php echo yojaka_url('index.php?r=letters/print&id=' . urlencode($record['id'] ?? '')); ?>" target="_blank">Print</a>
        </div>
    </div>
    <p>Template: <?php echo yojaka_escape($template['name'] ?? ($record['template_id'] ?? 'Unknown')); ?></p>
</section>

<section class="panel">
    <h2>Metadata</h2>
    <ul class="meta-list">
        <li><strong>ID:</strong> <?php echo yojaka_escape($record['id'] ?? ''); ?></li>
        <li><strong>Status:</strong> <?php echo yojaka_escape($record['status'] ?? ''); ?></li>
        <li><strong>Subject:</strong> <?php echo yojaka_escape($record['fields']['subject'] ?? ''); ?></li>
        <li><strong>Created:</strong> <?php echo yojaka_escape($record['created_at'] ?? ''); ?></li>
        <li><strong>Owner:</strong> <?php echo yojaka_escape($record['owner_username'] ?? ''); ?></li>
    </ul>
</section>

<section class="panel">
    <h2>Attachments</h2>
    <?php if (!empty($canEdit)): ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo yojaka_url('index.php?r=attachments/upload'); ?>">
            <input type="hidden" name="module" value="letters">
            <input type="hidden" name="id" value="<?php echo yojaka_escape($record['id'] ?? ''); ?>">
            <input type="file" name="attachment" required>
            <button type="submit">Upload File</button>
        </form>
    <?php endif; ?>

    <?php if (empty($record['attachments'])): ?>
        <p>No attachments uploaded.</p>
    <?php else: ?>
        <?php foreach ($record['attachments'] as $file): ?>
            <div>
                <a href="<?php echo yojaka_url('index.php?r=attachments/download&module=letters&id=' . urlencode($record['id']) . '&file=' . urlencode($file)); ?>">
                    <?php echo htmlspecialchars($file); ?>
                </a>
                <?php if (!empty($canEdit)): ?>
                    <form method="post" action="<?php echo yojaka_url('index.php?r=attachments/delete'); ?>" style="display:inline">
                        <input type="hidden" name="module" value="letters">
                        <input type="hidden" name="id" value="<?php echo yojaka_escape($record['id'] ?? ''); ?>">
                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
                        <button onclick="return confirm('Delete this file?')">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Final Letter</h2>
    <div class="letter-preview">
        <?php if ($renderedHtml): ?>
            <?php echo $renderedHtml; ?>
        <?php else: ?>
            <p>Template not available.</p>
        <?php endif; ?>
    </div>
</section>
