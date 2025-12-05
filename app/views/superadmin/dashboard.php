<section>
    <h2>Welcome, <?php echo htmlspecialchars($user['username'] ?? ''); ?> (Superadmin)</h2>
    <p class="tagline">Superadmin Dashboard</p>

    <div style="margin-top:16px;">
        <h3>Summary</h3>
        <ul class="diagnostics">
            <li>Departments: (to be managed here in future prompts).</li>
            <li>Subscription status: (to be shown here later).</li>
            <li>Environment: <?php echo htmlspecialchars($environment); ?></li>
            <li>Total system users: <?php echo (int) $userCount; ?></li>
        </ul>
    </div>

    <div style="margin-top:20px;">
        <a href="?route=auth/logout" style="color:#0f4c81;text-decoration:none;font-weight:bold;">Logout</a>
    </div>
</section>
