<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'logout') {
        logout_user();
        redirect('index.php');
    }
    require_app_rate_limit('login', 8, 15 * 60);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (password_verify($_POST['password'] ?? '', $hash) && $user) {
        login_user($user);
        redirect('dashboard.php');
    }
    flash('error', 'Invalid email or password.');
}
render_header('Log In', 'Log in to your Kinyan account.');
?>
<section class="auth-shell">
    <form method="post" class="auth-card">
        <?= csrf_field() ?>
        <h1>Log in</h1>
        <label>Email<input required type="email" name="email" autocomplete="email" placeholder="you@example.com"></label>
        <label>Password<input required type="password" name="password" autocomplete="current-password" placeholder="Your password"></label>
        <button class="button full-width" type="submit">Log in</button>
        <p><a href="forgot-password.php">Forgot your password?</a></p>
        <p>New to Kinyan? <a href="register.php">Create an account</a></p>
    </form>
</section>
<?php render_footer(); ?>
