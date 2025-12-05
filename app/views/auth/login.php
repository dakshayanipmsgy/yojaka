<section>
    <h2>Yojaka</h2>
    <p class="tagline">Superadmin Login</p>

    <?php if (!empty($notice)): ?>
        <div style="background:#e8f4ff;border:1px solid #b6d7ff;padding:10px;border-radius:4px;margin-bottom:12px;">
            <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background:#ffe8e8;border:1px solid #ffb6b6;padding:10px;border-radius:4px;margin-bottom:12px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="?route=auth/login" style="display:flex;flex-direction:column;gap:12px;">
        <div>
            <label for="username">Username</label><br>
            <input type="text" id="username" name="username" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
        </div>
        <div>
            <label for="password">Password</label><br>
            <input type="password" id="password" name="password" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
        </div>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit" style="padding:10px 16px;background:#0f4c81;color:#fff;border:none;border-radius:4px;cursor:pointer;">Login</button>
    </form>

    <p style="margin-top:16px;color:#555;">Use your Superadmin credentials to access the platform dashboard.</p>
</section>
