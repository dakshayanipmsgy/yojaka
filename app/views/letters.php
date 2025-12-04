<?php
require_login();
$templates = load_letter_templates();
$activeTemplates = array_filter($templates, function ($tpl) {
    return !empty($tpl['active']);
});

if (isset($_GET['download']) && $_GET['download'] === '1') {
    $downloadData = $_SESSION['last_letter_download'] ?? null;
    if ($downloadData) {
        $filename = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($downloadData['template_name'])) ?: 'letter';
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        echo "<html><head><meta charset=\"UTF-8\"><title>" . htmlspecialchars($downloadData['template_name']) . "</title></head><body>";
        echo '<h2>' . htmlspecialchars($downloadData['template_name']) . '</h2>';
        echo '<div><small>Generated at: ' . htmlspecialchars($downloadData['generated_at']) . '</small></div>';
        echo '<div style="margin-top:10px;white-space:pre-line;font-family:serif;">' . nl2br($downloadData['content']) . '</div>';
        echo '</body></html>';
        exit;
    }
    echo '<p>No letter available for download.</p>';
    exit;
}

$selectedTemplateId = $_POST['template_id'] ?? $_GET['template_id'] ?? '';
$selectedTemplate = $selectedTemplateId ? find_letter_template_by_id($templates, $selectedTemplateId) : null;
$errors = [];
$mergedContent = '';
$generatedAt = '';

$csrfToken = $_SESSION['letters_csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['letters_csrf_token'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['letters_csrf_token'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    if (!$selectedTemplate || empty($selectedTemplate['active'])) {
        $errors[] = 'Selected template is not available.';
    }

    $inputValues = [];
    if ($selectedTemplate) {
        foreach ($selectedTemplate['variables'] ?? [] as $var) {
            $name = $var['name'] ?? '';
            $value = trim($_POST['var'][$name] ?? '');
            if (!empty($var['required']) && $value === '') {
                $errors[] = ($var['label'] ?? $name) . ' is required.';
            }
            $inputValues[$name] = $value;
        }
    }

    if (empty($errors) && $selectedTemplate) {
        $mergedContent = render_template_body($selectedTemplate['body'], $inputValues);
        $generatedAt = gmdate('c');
        log_event('letter_generated', $_SESSION['username'] ?? null, [
            'template_id' => $selectedTemplate['id'] ?? '',
            'template_name' => $selectedTemplate['name'] ?? '',
        ]);
        append_generated_letter_record($_SESSION['username'] ?? 'unknown', $selectedTemplate['id'] ?? '', $selectedTemplate['name'] ?? '', $mergedContent);
        $_SESSION['last_letter_download'] = [
            'template_name' => $selectedTemplate['name'] ?? 'Letter',
            'generated_at' => $generatedAt,
            'content' => $mergedContent,
        ];
    }
}
?>
<div class="form-grid">
    <div class="card">
        <h3>Select Template</h3>
        <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="page" value="letters">
            <label for="template_id">Available Templates</label>
            <select name="template_id" id="template_id" required>
                <option value="">-- Choose a template --</option>
                <?php foreach ($activeTemplates as $tpl): ?>
                    <option value="<?= htmlspecialchars($tpl['id']); ?>" <?= ($tpl['id'] === $selectedTemplateId) ? 'selected' : ''; ?>><?= htmlspecialchars($tpl['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Proceed</button>
        </form>
        <?php if ($selectedTemplate && !empty($selectedTemplate['active'])): ?>
            <div class="template-summary">
                <h4><?= htmlspecialchars($selectedTemplate['name']); ?></h4>
                <p><?= nl2br(htmlspecialchars($selectedTemplate['description'] ?? '')); ?></p>
                <?php if (!empty($selectedTemplate['category'])): ?>
                    <p><strong>Category:</strong> <?= htmlspecialchars($selectedTemplate['category']); ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($selectedTemplateId && (!$selectedTemplate || empty($selectedTemplate['active']))): ?>
            <p class="error">Selected template is unavailable.</p>
        <?php endif; ?>
    </div>

    <?php if ($selectedTemplate && empty($selectedTemplate['active']) === false): ?>
    <div class="card">
        <h3>Fill Details</h3>
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=letters">
            <input type="hidden" name="template_id" value="<?= htmlspecialchars($selectedTemplate['id']); ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
            <?php foreach ($selectedTemplate['variables'] as $var): ?>
                <?php
                    $name = $var['name'];
                    $label = $var['label'] ?? $name;
                    $type = $var['type'] ?? 'text';
                    $required = !empty($var['required']);
                    $value = $_POST['var'][$name] ?? '';
                ?>
                <div class="form-field">
                    <label for="var_<?= htmlspecialchars($name); ?>"><?= htmlspecialchars($label); ?><?= $required ? ' *' : ''; ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <textarea id="var_<?= htmlspecialchars($name); ?>" name="var[<?= htmlspecialchars($name); ?>]" <?= $required ? 'required' : ''; ?>><?= htmlspecialchars($value); ?></textarea>
                    <?php else: ?>
                        <input id="var_<?= htmlspecialchars($name); ?>" type="<?= htmlspecialchars($type); ?>" name="var[<?= htmlspecialchars($name); ?>]" value="<?= htmlspecialchars($value); ?>" <?= $required ? 'required' : ''; ?> />
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit">Generate Letter</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if ($mergedContent && $selectedTemplate): ?>
<div class="card">
    <h3>Letter Preview</h3>
    <div><strong>Template:</strong> <?= htmlspecialchars($selectedTemplate['name']); ?></div>
    <div><strong>Generated at:</strong> <?= htmlspecialchars($generatedAt); ?></div>
    <div class="letter-preview" style="margin-top:10px; padding:10px; border:1px solid #ddd; background:#fafafa;">
        <?= nl2br($mergedContent); ?>
    </div>
    <div class="actions" style="margin-top:10px; display:flex; gap:10px;">
        <button type="button" onclick="window.print();">Print</button>
        <a class="button" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=letters&download=1">Download HTML</a>
    </div>
</div>
<?php endif; ?>
