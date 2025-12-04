<?php
/** Basic unified print view placeholder */
?>
<div class="print-container">
    <?php if (!empty($office['letterhead']['header_html'])): ?>
        <div class="letterhead-header"><?php echo $office['letterhead']['header_html']; ?></div>
    <?php endif; ?>
    <div class="content">
        <?php echo $content ?? ''; ?>
    </div>
    <?php if (!empty($office['letterhead']['footer_html'])): ?>
        <div class="letterhead-footer"><?php echo $office['letterhead']['footer_html']; ?></div>
    <?php endif; ?>
</div>
