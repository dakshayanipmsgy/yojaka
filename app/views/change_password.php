<?php
require_login();
$user = current_user();
if (!$user) {
    echo 'Unable to load user profile.';
    return;
}

$errors = [];
$notice = '';
$csrfToken = $_SESSION['change_password_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['change_password_csrf'] = $csrfToken;
$requireCurrent = !($user['username'] === 'admin' && !empty($user['force_password_change']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!$submittedToken || !hash_equals($_SESSION['change_password_csrf'], $submittedToken)) {
        $errors[] = 'Security token mismatch. Please retry.';
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($requireCurrent && !password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $user['force_password_change'] = false;
        $user['updated_at'] = gmdate('c');
        if (save_user($user)) {
            $_SESSION['force_password_change'] = false;
            log_event('password_changed_self', $user['username']);
            write_audit_log('user', $user['username'], 'password_changed', ['via' => 'self_service']);
            $roleConfig = get_role_dashboard_config($_SESSION['role'] ?? 'user');
            $landingPage = $roleConfig['landing_page'] ?? 'dashboard';
            header('Location: ' . YOJAKA_BASE_URL . '/app.php?page=' . urlencode($landingPage));
            exit;
        }
        $errors[] = 'Unable to update password.';
    }
}
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($user['force_password_change'])): ?>
    <div class="alert info"><?= htmlspecialchars(i18n_get('users.force_password_change_message')); ?></div>
<?php endif; ?>

<form method="post" class="form-stacked" action="<?= YOJAKA_BASE_URL; ?>/app.php?page=change_password">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
    <?php if ($requireCurrent): ?>
        <div class="form-field">
            <label for="current_password"><?= htmlspecialchars(i18n_get('users.current_password')); ?></label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
    <?php endif; ?>
    <div class="form-field">
        <label for="new_password"><?= htmlspecialchars(i18n_get('users.new_password')); ?></label>
        <input type="password" id="new_password" name="new_password" required>
    </div>
    <div class="form-field">
        <label for="confirm_password"><?= htmlspecialchars(i18n_get('users.confirm_password')); ?></label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <div class="form-actions">
        <button class="btn-primary" type="submit"><?= htmlspecialchars(i18n_get('users.change_password')); ?></button>
    </div>
</form>
