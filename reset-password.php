<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');
$tokenHash = preg_match('/^[a-f0-9]{64}$/', $token) ? hash('sha256', $token) : '';
$stmt = db()->prepare("SELECT id, user_id FROM account_tokens WHERE token_hash = ? AND purpose = 'password_reset' AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
$stmt->execute([$tokenHash]);
$record = $stmt->fetch();

if (is_post()) {
    verify_csrf();
    require_app_rate_limit('password_reset_submit', 8, 60 * 60);
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';
    if (!human_submission_passes('password_reset_submit')) {
        require_app_rate_limit('password_reset_submit_bot_block', 3, 60 * 60);
        flash('error', 'We could not verify that submission. Please try again.');
    } elseif (!$record) {
        flash('error', 'This reset link is invalid or has expired. Request a new link.');
    } elseif (strlen($password) < 12) {
        flash('error', 'Use a password with at least 12 characters.');
    } elseif (!hash_equals($password, $confirmation)) {
        flash('error', 'The password confirmation does not match.');
    } else {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $record['user_id']]);
            db()->prepare('UPDATE account_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL')->execute([$record['id']]);
            db()->commit();
            flash('success', 'Your password was reset. Log in with your new password.');
            redirect('login.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }
}

render_header('Choose New Password', 'Choose a new password for your Kinyan account.');
?>
<section class="auth-shell">
    <?php if (!$record): ?>
        <div class="status-card"><span>!</span><h1>Reset link unavailable</h1><p>This link is invalid, expired, or already used.</p><div class="status-actions"><a class="button" href="forgot-password.php">Request a new link</a></div></div>
    <?php else: ?>
        <form method="post" class="auth-card">
            <?= csrf_field() ?><?= bot_protection_fields('password_reset_submit') ?><input type="hidden" name="token" value="<?= e($token) ?>">
            <h1>Choose a new password</h1>
            <label>New password<input required minlength="12" type="password" name="password" autocomplete="new-password" placeholder="At least 12 characters"></label>
            <label>Confirm new password<input required minlength="12" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Repeat your new password"></label>
            <button class="button full-width" type="submit">Reset password</button>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
