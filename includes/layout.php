<?php
declare(strict_types=1);

function render_header(string $title, string $description = '', array $meta = []): void
{
    $fullTitle = $title === APP_NAME ? APP_NAME : $title . ' | ' . APP_NAME;
    $description = $description ?: 'Kinyan is a direct-contact car marketplace for cars for sale and wanted car posts.';
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';
    $user = current_user();
    $openErrorCount = ($user['role'] ?? '') === 'admin' ? app_open_error_count() : 0;
    $metaImage = !empty($meta['image']) ? site_url((string)$meta['image']) : '';
    $metaImageAlt = (string)($meta['image_alt'] ?? $fullTitle);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
    <?php if ($metaImage): ?>
    <meta property="og:image" content="<?= e($metaImage) ?>">
    <meta property="og:image:secure_url" content="<?= e($metaImage) ?>">
    <meta property="og:image:alt" content="<?= e($metaImageAlt) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= e($metaImage) ?>">
    <meta name="twitter:image:alt" content="<?= e($metaImageAlt) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="<?= $prefix ?>assets/favicon.svg">
    <link rel="stylesheet" href="<?= $prefix ?>assets/css/styles.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $prefix ?>index.php"><span>K</span>Kinyan</a>
    <button class="nav-toggle" data-nav-toggle aria-label="Open navigation">☰</button>
    <nav class="site-nav" data-nav>
        <a class="<?= $current === 'cars.php' ? 'active' : '' ?>" href="<?= $prefix ?>cars.php">Cars</a>
        <a class="<?= $current === 'saved.php' ? 'active' : '' ?>" href="<?= $prefix ?>saved.php">Saved</a>
        <a class="<?= $current === 'vin-check.php' ? 'active' : '' ?>" href="<?= $prefix ?>vin-check.php">VIN Check</a>
        <a class="<?= $current === 'wanted.php' ? 'active' : '' ?>" href="<?= $prefix ?>wanted.php">Wanted</a>
        <a href="<?= $prefix ?>post-car.php">Sell</a>
        <a href="<?= $prefix ?>post-wanted.php">Request</a>
        <?php if ($user): ?>
            <a href="<?= $prefix ?>dashboard.php">Dashboard</a>
            <a class="<?= $current === 'library.php' ? 'active' : '' ?>" href="<?= $prefix ?>library.php">Library</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?><a href="<?= $prefix ?>admin/index.php">Admin</a><?php endif; ?>
            <a href="<?= $prefix ?>login.php?action=logout">Log out</a>
        <?php else: ?>
            <a href="<?= $prefix ?>login.php">Log in</a>
            <a class="button small" href="<?= $prefix ?>register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>
<div class="offline-banner" data-offline-banner role="alert" hidden>You appear to be offline. You can keep browsing loaded pages, but submitting forms may fail until your connection returns.</div>
<main>
<?php foreach (flashes() as $item): ?>
    <div class="flash <?= e($item['type']) ?>" role="alert" data-flash-alert><strong><?= e(ucfirst((string)$item['type'])) ?></strong><span><?= e($item['message']) ?></span><button type="button" data-dismiss-alert aria-label="Dismiss alert">×</button></div>
<?php endforeach; ?>
<?php if ($openErrorCount > 0): ?>
    <div class="flash error admin-error-alert" role="alert" data-flash-alert><strong>Error alert</strong><span><?= $openErrorCount ?> unresolved application error<?= $openErrorCount === 1 ? '' : 's' ?>. <a href="<?= $prefix ?>admin/errors.php">View error log</a></span><button type="button" data-dismiss-alert aria-label="Dismiss alert">×</button></div>
<?php endif; ?>
<div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>
<div class="confirm-modal" data-confirm-modal hidden>
    <div class="confirm-backdrop" data-confirm-cancel></div>
    <section class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <h2 id="confirm-title" data-confirm-title>Confirm action</h2>
        <p data-confirm-message>Are you sure?</p>
        <div class="confirm-actions">
            <button class="button ghost" type="button" data-confirm-cancel>Cancel</button>
            <button class="button danger-button" type="button" data-confirm-accept>Continue</button>
        </div>
    </section>
</div>
    <?php
}

function render_status_page(int $code, string $title, string $message, array $actions = []): never
{
    http_response_code($code);
    render_header($title, $message);
    ?>
    <section class="status-page">
        <div class="status-card">
            <span><?= (int)$code ?></span>
            <h1><?= e($title) ?></h1>
            <p><?= e($message) ?></p>
            <div class="status-actions">
                <?php if ($actions): ?>
                    <?php foreach ($actions as $label => $href): ?><a class="button <?= $label === array_key_first($actions) ? '' : 'ghost' ?>" href="<?= e($href) ?>"><?= e((string)$label) ?></a><?php endforeach; ?>
                <?php else: ?>
                    <a class="button" href="index.php">Go home</a>
                    <a class="button ghost" href="cars.php">Browse cars</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    render_footer();
    exit;
}

function render_admin_nav(string $active = ''): void
{
    $current = $active ?: basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $counts = ['pending_cars' => 0, 'pending_wanted' => 0, 'reports' => 0, 'errors' => 0];
    try {
        $counts = db()->query("SELECT
            (SELECT COUNT(*) FROM car_listings WHERE status = 'pending') pending_cars,
            (SELECT COUNT(*) FROM wanted_posts WHERE status = 'pending') pending_wanted,
            (SELECT COUNT(*) FROM reports) reports,
            (SELECT COUNT(*) FROM app_errors WHERE status = 'open') errors")->fetch() ?: $counts;
    } catch (Throwable) {
    }
    $items = [
        'index' => ['Overview', 'index.php', 0],
        'listings' => ['Cars', 'listings.php', (int)$counts['pending_cars']],
        'wanted' => ['Wanted', 'wanted.php', (int)$counts['pending_wanted']],
        'reports' => ['Reports', 'reports.php', (int)$counts['reports']],
        'errors' => ['Errors', 'errors.php', (int)$counts['errors']],
        'users' => ['Users', 'users.php', 0],
        'categories' => ['Makes', 'categories.php', 0],
        'settings' => ['Settings', 'settings.php', 0],
    ];
    ?>
    <nav class="admin-primary-nav" aria-label="Admin navigation">
        <?php foreach ($items as $key => [$label, $href, $count]): ?>
            <a class="<?= $current === $key ? 'active' : '' ?>" href="<?= e($href) ?>" <?= $current === $key ? 'aria-current="page"' : '' ?>>
                <span><?= e($label) ?></span><?php if ($count > 0): ?><strong><?= $count ?></strong><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function render_footer(): void
{
    $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';
    ?>
</main>
<footer class="site-footer">
    <div>
        <strong>Kinyan</strong>
        <p>Direct-contact car listings. No checkout, cart, or site payments.</p>
    </div>
    <div class="footer-links">
        <a href="<?= $prefix ?>cars.php">Browse cars</a>
        <a href="<?= $prefix ?>wanted.php">Buyer requests</a>
        <a href="<?= $prefix ?>post-car.php">Post a car</a>
        <a href="<?= $prefix ?>post-wanted.php">Post wanted</a>
        <a href="<?= $prefix ?>tos.html">Terms</a>
    </div>
</footer>
<script src="<?= $prefix ?>assets/js/app.js"></script>
</body>
</html>
<?php
}
