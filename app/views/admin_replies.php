<?php
require_login();
require_permission('manage_reply_templates');
$templates = load_reply_templates();
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = [
        'id' => trim($_POST['id'] ?? ''),
        'module' => trim($_POST['module'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'language' => trim($_POST['language'] ?? 'en'),
        'title' => trim($_POST['title'] ?? ''),
        'body_template' => trim($_POST['body_template'] ?? ''),
        'variables' => array_filter(array_map('trim', explode(',', $_POST['variables'] ?? ''))),
        'active' => !empty($_POST['active']),
    ];
    if ($new['id'] === '' || $new['module'] === '' || $new['category'] === '') {
        $errors[] = 'ID, module and category are required.';
    }
    if (empty($errors)) {
        $updated = false;
        foreach ($templates as $idx => $tpl) {
            if (($tpl['id'] ?? '') === $new['id']) {
                $templates[$idx] = $new;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $templates[] = $new;
        }
        bootstrap_ensure_directory(dirname(reply_templates_path()));
        file_put_contents(reply_templates_path(), json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $notice = $updated ? 'Template updated.' : 'Template added.';
    }
}
?>
<div class="form-card">
    <h3>Reply Templates</h3>
    <?php if ($notice): ?><div class="alert info"><?= htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert error"><?= htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>
    <form method="post" class="form-grid">
        <label>ID
            <input type="text" name="id" required>
        </label>
        <label>Module
            <input type="text" name="module" placeholder="rti">
        </label>
        <label>Category
            <input type="text" name="category" placeholder="general_information">
        </label>
        <label>Language
            <input type="text" name="language" value="en">
        </label>
        <label>Title
            <input type="text" name="title">
        </label>
        <label>Variables (comma separated)
            <input type="text" name="variables" placeholder="rti_date,subject,applicant_name">
        </label>
        <label>Body Template
            <textarea name="body_template" rows="4"></textarea>
        </label>
        <label>
            <input type="checkbox" name="active" value="1" checked> Active
        </label>
        <div class="actions">
            <button class="btn primary" type="submit">Save Template</button>
        </div>
    </form>
</div>
<div class="card">
    <h4>Existing Templates</h4>
    <table class="table">
        <thead><tr><th>ID</th><th>Module</th><th>Category</th><th>Language</th><th>Title</th><th>Active</th></tr></thead>
        <tbody>
            <?php foreach ($templates as $tpl): ?>
                <tr>
                    <td><?= htmlspecialchars($tpl['id'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($tpl['module'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($tpl['category'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($tpl['language'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($tpl['title'] ?? ''); ?></td>
                    <td><?= !empty($tpl['active']) ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
