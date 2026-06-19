<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

render_header('VIN Check', 'Check a VIN and review decoded vehicle details before posting or contacting a seller.');
?>
<section class="form-shell">
    <h1>VIN check</h1>
    <p class="form-intro">Enter a 17-character VIN to decode vehicle identity, engine, drivetrain, body, manufacturing, safety, battery, dimensions, equipment, and other available details.</p>
    <div class="details-card vin-check-card">
        <?php if (is_logged_in()): ?>
        <label>VIN<input name="vin" maxlength="17" placeholder="17-character VIN" data-vin-check-input></label>
        <button class="button" type="button" data-vin-check>Check VIN</button>
        <p class="vin-status" data-vin-check-status>VIN details can help confirm the basics, but always review the car and paperwork yourself.</p>
        <div class="vin-results" data-vin-check-results hidden></div>
        <div class="vin-history-note" data-vin-history-note hidden></div>
        <?php else: ?>
        <h2>Log in to use VIN check</h2>
        <p class="field-note">VIN lookup is available to logged-in users so we can rate limit checks and keep the service reliable.</p>
        <a class="button" href="login.php">Log in to check a VIN</a>
        <a class="button ghost" href="register.php">Create account</a>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
