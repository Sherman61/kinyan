<?php
declare(strict_types=1);

function render_header(string $title, string $description = '', array $meta = []): void
{
    $fullTitle = $title === APP_NAME ? APP_NAME : $title . ' | ' . APP_NAME;
    $description = $description ?: 'Kinyan is a direct-contact car marketplace for cars for sale and wanted car posts.';
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';
    $user = current_user();
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
    <?php if (!empty($meta['image'])): ?><meta property="og:image" content="<?= e($meta['image']) ?>"><?php endif; ?>
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
    <div class="flash <?= e($item['type']) ?>" role="alert"><span><?= e($item['message']) ?></span><button type="button" data-dismiss-alert aria-label="Dismiss alert">×</button></div>
<?php endforeach; ?>
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
    </div>
</footer>
<script src="<?= $prefix ?>assets/js/app.js"></script>
</body>
</html>
<?php
}
