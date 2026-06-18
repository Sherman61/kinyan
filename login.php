<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (($_GET['action'] ?? '') === 'logout') {
    logout_user();
    redirect('index.php');
}
if (is_post()) {
    verify_csrf();
    require_app_rate_limit('login', 8, 15 * 60);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
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
        <p>New to Kinyan? <a href="register.php">Create an account</a></p>
    </form>
</section>
<?php render_footer(); ?>
