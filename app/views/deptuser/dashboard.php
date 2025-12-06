<section class="page-intro">
    <h1>Welcome</h1>
    <p>Your department workspace and role-based tools will appear here.</p>
</section>

<section class="panel">
    <h2>Current Session</h2>
    <ul class="meta-list">
        <li><strong>Login Identity:</strong> <?php echo yojaka_escape($user['login_identity'] ?? ''); ?></li>
        <li><strong>Role:</strong> <?php echo yojaka_escape($user['role_id'] ?? ''); ?></li>
        <li><strong>Department:</strong> <?php echo yojaka_escape($department['name'] ?? ($user['department_slug'] ?? '')); ?></li>
        <li><strong>Status:</strong> <?php echo yojaka_escape($user['status'] ?? ''); ?></li>
    </ul>
</section>

<section class="panel">
    <h2>Next steps</h2>
    <p>Module links for your assigned role will appear here in future phases.</p>
</section>
