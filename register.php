<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$form = ['name' => '', 'email' => '', 'phone' => '', 'accepted_terms' => false];
if (is_post()) {
    verify_csrf();
    require_app_rate_limit('register', 5, 60 * 60);
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $acceptedTerms = isset($_POST['accept_terms']);
    $form = ['name' => $name, 'email' => $email, 'phone' => $phone, 'accepted_terms' => $acceptedTerms];
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        flash('error', 'Enter a name, valid email, and password with at least 8 characters.');
    } elseif (!$acceptedTerms) {
        flash('error', 'You must agree to the Terms of Service to create an account.');
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'user']);
            $user = ['id' => db()->lastInsertId(), 'role' => 'user'];
            login_user($user);
            redirect('dashboard.php');
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                flash('error', 'That email is already registered.');
            } else {
                $message = 'We could not create your account right now. Please try again.';
                $errorId = log_app_error($e, $message, ['registration_email' => $email], 'error');
                flash('error', $message . ($errorId ? ' Error reference: ERR-' . $errorId . '.' : ''));
            }
        }
    }
}
render_header('Register', 'Create a Kinyan account.');
?>
<section class="auth-shell">
    <form method="post" class="auth-card">
        <?= csrf_field() ?>
        <h1>Create account</h1>
        <label>Name<input required name="name" autocomplete="name" value="<?= e($form['name']) ?>" placeholder="Your full name"></label>
        <label>Email<input required type="email" name="email" autocomplete="email" value="<?= e($form['email']) ?>" placeholder="you@example.com"></label>
        <label>Phone<input name="phone" inputmode="tel" autocomplete="tel" value="<?= e($form['phone']) ?>" placeholder="732-555-1234"></label>
        <label>Password<input required minlength="8" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters"></label>
        <label class="check"><input required type="checkbox" name="accept_terms" value="1" <?= checked($form['accepted_terms']) ?>> I agree to the <a class="terms-link" href="tos.html" target="_blank" rel="noopener">Terms of Service</a></label>
        <button class="button full-width" type="submit">Register</button>
        <p>Already have an account? <a href="login.php">Log in</a></p>
    </form>
</section>
<?php render_footer(); ?>
