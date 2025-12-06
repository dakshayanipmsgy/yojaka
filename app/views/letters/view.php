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
    <h2>Final Letter</h2>
    <div class="letter-preview">
        <?php if ($renderedHtml): ?>
            <?php echo $renderedHtml; ?>
        <?php else: ?>
            <p>Template not available.</p>
        <?php endif; ?>
    </div>
</section>
