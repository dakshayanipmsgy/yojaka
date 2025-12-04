<?php
require_login();
require_permission('manage_ai_settings');
$config = ai_load_config();
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = !empty($_POST['enabled']);
    $provider = $_POST['provider'] ?? 'stub';
    $config['enabled'] = $enabled;
    $config['provider'] = in_array($provider, ['stub', 'external_api'], true) ? $provider : 'stub';
    $config['endpoint_url'] = trim($_POST['endpoint_url'] ?? '');
    $config['api_key'] = trim($_POST['api_key'] ?? '');
    $config['max_tokens'] = (int) ($_POST['max_tokens'] ?? 800);
    $config['temperature'] = (float) ($_POST['temperature'] ?? 0.3);
    $config['mask_personal_data'] = !empty($_POST['mask_personal_data']);

    bootstrap_ensure_directory(dirname(ai_config_path()));
    if (file_put_contents(ai_config_path(), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
        $notice = 'AI configuration saved.';
    } else {
        $errors[] = 'Unable to save configuration.';
    }
}
?>
<div class="form-card">
    <h3>AI &amp; Assistance Settings</h3>
    <?php if (!empty($notice)): ?><div class="alert info"><?= htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert error"><?= htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>
    <form method="post">
        <label>
            <input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : ''; ?>>
            Enable AI Assistance
        </label>
        <label>Provider
            <select name="provider">
                <option value="stub" <?= ($config['provider'] ?? 'stub') === 'stub' ? 'selected' : ''; ?>>Stub (Offline)</option>
                <option value="external_api" <?= ($config['provider'] ?? '') === 'external_api' ? 'selected' : ''; ?>>External AI API</option>
            </select>
        </label>
        <label>Endpoint URL
            <input type="url" name="endpoint_url" value="<?= htmlspecialchars($config['endpoint_url'] ?? ''); ?>">
        </label>
        <label>API Key
            <input type="password" name="api_key" value="<?= htmlspecialchars($config['api_key'] ?? ''); ?>" autocomplete="off">
        </label>
        <label>Max Tokens
            <input type="number" name="max_tokens" value="<?= htmlspecialchars((string)($config['max_tokens'] ?? 800)); ?>">
        </label>
        <label>Temperature
            <input type="number" step="0.1" name="temperature" value="<?= htmlspecialchars((string)($config['temperature'] ?? 0.3)); ?>">
        </label>
        <label>
            <input type="checkbox" name="mask_personal_data" value="1" <?= !empty($config['mask_personal_data']) ? 'checked' : ''; ?>>
            Mask personal data before sending to external API
        </label>
        <div class="actions">
            <button class="btn primary" type="submit">Save Settings</button>
        </div>
    </form>
</div>
