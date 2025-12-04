<?php
require_once __DIR__ . '/print_layout.php';

$downloadData = $_SESSION['last_letter_download'] ?? null;
if (!$downloadData) {
    http_response_code(404);
    echo 'No generated letter available for printing.';
    exit;
}

$officeId = get_current_office_id();
$letterTitle = $downloadData['template_name'] ?? 'Letter';

ob_start();
?>
<div class="document-title"><?= htmlspecialchars($letterTitle); ?></div>

<table class="meta-table">
    <tr>
        <td><strong>Generated At:</strong></td>
        <td><?= htmlspecialchars($downloadData['generated_at'] ?? ''); ?></td>
    </tr>
</table>

<div class="document-body-content">
    <?= $downloadData['content'] ?? ''; ?>
</div>
<?php
$bodyHtml = ob_get_clean();
render_print_page($letterTitle, $bodyHtml, $officeId, null);
