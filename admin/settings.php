<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
if (is_post()) {
    verify_csrf();
    set_setting('auto_approve_listings', isset($_POST['auto_approve_listings']) ? '1' : '0');
    set_setting('site_name', trim($_POST['site_name'] ?? 'Kinyan'));
    set_setting('support_email', trim($_POST['support_email'] ?? 'support@kinyan.live'));
    flash('success', 'Settings saved.');
    redirect('settings.php');
}
render_header('Admin Settings', 'Manage Kinyan settings.');
?>
<section class="form-shell">
    <h1>Site settings</h1>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>Site name<input name="site_name" value="<?= e(setting('site_name', 'Kinyan')) ?>"></label>
        <label>Support email<input type="email" name="support_email" value="<?= e(setting('support_email', 'support@kinyan.live')) ?>"></label>
        <label class="check full"><input type="checkbox" name="auto_approve_listings" <?= checked(setting('auto_approve_listings', '0') === '1') ?>> Auto-approve new listings and wanted posts</label>
        <button class="button full" type="submit">Save settings</button>
    </form>
</section>
<?php render_footer(); ?>
