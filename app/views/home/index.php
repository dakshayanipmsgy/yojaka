<section>
    <h2>Welcome to <?php echo htmlspecialchars($appName); ?></h2>
    <p class="tagline"><?php echo htmlspecialchars($tagline); ?></p>

    <?php if (!empty($notice)): ?>
        <div style="background:#e8f4ff;border:1px solid #b6d7ff;padding:10px;border-radius:4px;margin-bottom:12px;">
            <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <h3>Environment</h3>
    <p>Current mode: <strong><?php echo htmlspecialchars($diagnostics['environment'] ?? 'unknown'); ?></strong></p>

    <h3>Diagnostics</h3>
    <ul class="diagnostics">
        <li>PHP Version: <?php echo htmlspecialchars($diagnostics['php_version'] ?? ''); ?></li>
        <li>/data writable: <?php echo !empty($diagnostics['data_writable']) ? 'Yes' : 'No'; ?></li>
        <li>Base URL: <?php echo htmlspecialchars($diagnostics['base_url'] ?? ''); ?></li>
    </ul>

    <p>This is the Yojaka skeleton. Superadmin, departments, document templates, dak/file movement and other modules will be configured in future steps.</p>
</section>
