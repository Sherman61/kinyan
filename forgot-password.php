<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$submitted = false;
$mailUnavailable = !mail_service_enabled();
if (is_post()) {
    verify_csrf();
    require_app_rate_limit('password_reset_request', 5, 60 * 60);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $submitted = true;
    if (!human_submission_passes('password_reset_request')) {
        require_app_rate_limit('password_reset_bot_block', 3, 60 * 60);
    } elseif ($mailUnavailable) {
        http_response_code(503);
        header('Retry-After: 10800');
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            require_app_rate_limit('password_reset_email_' . hash('sha256', $email), 3, 60 * 60);
            $token = bin2hex(random_bytes(32));
            db()->prepare("UPDATE account_tokens SET used_at = NOW() WHERE user_id = ? AND purpose = 'password_reset' AND used_at IS NULL")->execute([$user['id']]);
            db()->prepare("INSERT INTO account_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, 'password_reset', ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")
                ->execute([$user['id'], hash('sha256', $token)]);
            $tokenId = (int)db()->lastInsertId();
            $url = site_url('reset-password.php?token=' . rawurlencode($token));
            $subject = 'Reset your Kinyan password';
            $body = "Use this secure link within 30 minutes to reset your Kinyan password:\n\n{$url}\n\nIf you did not request this, you can ignore this email.";
            if (!send_app_mail($user['email'], $subject, $body)) {
                db()->prepare('UPDATE account_tokens SET used_at = NOW() WHERE id = ?')->execute([$tokenId]);
                log_app_error('Password reset email delivery failed.', 'Password reset email delivery is unavailable.', ['user_id' => (int)$user['id']], 'warning');
                $mailUnavailable = true;
                http_response_code(503);
                header('Retry-After: 10800');
            }
        }
    }
}

render_header('Reset Password', 'Request a secure Kinyan password reset link.');
?>
<section class="auth-shell">
    <form method="post" class="auth-card">
        <?= csrf_field() ?>
        <?= bot_protection_fields('password_reset_request') ?>
        <h1>Reset password</h1>
        <?php if ($submitted && $mailUnavailable): ?>
            <div class="inline-status error" role="alert">Our email service is temporarily offline. Please try again in a few hours.</div>
        <?php elseif ($submitted): ?>
            <div class="inline-status success" role="status">If an account matches that email, a reset link has been sent. Check your inbox and spam folder.</div>
        <?php endif; ?>
        <label>Email<input required type="email" name="email" autocomplete="email" placeholder="you@example.com"></label>
        <button class="button full-width" type="submit">Send reset link</button>
        <p><a href="login.php">Return to log in</a></p>
    </form>
</section>
<?php render_footer(); ?>
