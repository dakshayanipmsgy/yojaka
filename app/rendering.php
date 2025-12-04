<?php
// Rendering helpers for document presentation

function render_with_letterhead(string $innerHtml, array $department, bool $includeSignatory = false): string
{
    $header = $department['letterhead_header_html'] ?? '';
    $footer = $department['letterhead_footer_html'] ?? '';
    $signatory = $department['default_signatory_block'] ?? '';

    ob_start();
    ?>
    <div class="document-wrapper">
        <div class="letterhead-header">
            <?= $header; ?>
        </div>
        <div class="document-body">
            <?= $innerHtml; ?>
            <?php if ($includeSignatory && trim($signatory) !== ''): ?>
                <div class="document-signatory">
                    <?= $signatory; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="letterhead-footer">
            <?= $footer; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

?>
