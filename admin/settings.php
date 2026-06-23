<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
if (is_post()) {
    verify_csrf();
    require_app_rate_limit('admin_settings', 60, 60 * 60);
    set_setting('auto_approve_listings', isset($_POST['auto_approve_listings']) ? '1' : '0');
    $expirationDays = max(14, min(180, (int)($_POST['listing_expiration_days'] ?? 45)));
    set_setting('listing_expiration_days', (string)$expirationDays);
    set_setting('site_name', trim($_POST['site_name'] ?? 'Kinyan'));
    set_setting('support_email', trim($_POST['support_email'] ?? 'support@kinyan.live'));
    flash('success', 'Settings saved.');
    redirect('settings.php');
}
render_header('Admin Settings', 'Manage Kinyan settings.');
?>
<section class="form-shell admin-settings-page">
    <div class="page-title"><h1>Site settings</h1><p>Control marketplace defaults and support contact information.</p></div>
    <?php render_admin_nav('settings'); ?>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>Site name<input name="site_name" value="<?= e(setting('site_name', 'Kinyan')) ?>"></label>
        <label>Support email<input type="email" name="support_email" value="<?= e(setting('support_email', 'support@kinyan.live')) ?>"></label>
        <label>Expire stale listings after (days)<input required type="number" min="14" max="180" name="listing_expiration_days" value="<?= e(setting('listing_expiration_days', '45')) ?>"><small>Run the expiration maintenance command daily.</small></label>
        <label class="check full"><input type="checkbox" name="auto_approve_listings" <?= checked(setting('auto_approve_listings', '0') === '1') ?>> Auto-approve new listings and wanted posts</label>
        <button class="button full" type="submit">Save settings</button>
    </form>
</section>
<?php render_footer(); ?>
