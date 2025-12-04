<?php $user = current_user(); ?>
<div class="grid">
    <div class="card highlight">
        <h2>Welcome, <?= htmlspecialchars($user['full_name'] ?? ''); ?>!</h2>
        <p>You are logged in as <strong><?= htmlspecialchars($user['role'] ?? ''); ?></strong>.</p>
        <p>This is the starting point for Yojaka modules.</p>
    </div>
    <div class="card">
        <h3>Letters &amp; Notices</h3>
        <p>Coming soon.</p>
    </div>
    <div class="card">
        <h3>RTI Replies</h3>
        <p>Coming soon.</p>
    </div>
    <div class="card">
        <h3>Dak &amp; File Movement</h3>
        <p>Coming soon.</p>
    </div>
</div>
