<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (is_post()) {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        flash('error', 'Enter a name, valid email, and password with at least 8 characters.');
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'user']);
            $user = ['id' => db()->lastInsertId(), 'role' => 'user'];
            login_user($user);
            redirect('dashboard.php');
        } catch (PDOException $e) {
            flash('error', 'That email is already registered.');
        }
    }
}
render_header('Register', 'Create a Kinyan account.');
?>
<section class="auth-shell">
    <form method="post" class="auth-card">
        <?= csrf_field() ?>
        <h1>Create account</h1>
        <label>Name<input required name="name"></label>
        <label>Email<input required type="email" name="email"></label>
        <label>Phone<input name="phone"></label>
        <label>Password<input required minlength="8" type="password" name="password"></label>
        <button class="button full-width" type="submit">Register</button>
        <p>Already have an account? <a href="login.php">Log in</a></p>
    </form>
</section>
<?php render_footer(); ?>
