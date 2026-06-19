<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$stats = [
    'Total users' => db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Active cars' => db()->query("SELECT COUNT(*) FROM car_listings WHERE status='active'")->fetchColumn(),
    'Pending cars' => db()->query("SELECT COUNT(*) FROM car_listings WHERE status='pending'")->fetchColumn(),
    'Pending wanted' => db()->query("SELECT COUNT(*) FROM wanted_posts WHERE status='pending'")->fetchColumn(),
    'Sold listings' => db()->query("SELECT COUNT(*) FROM car_listings WHERE status='sold'")->fetchColumn(),
    'Reported posts' => db()->query('SELECT COUNT(*) FROM reports')->fetchColumn(),
];
$mostViewed = db()->query("SELECT id,title,views FROM car_listings ORDER BY views DESC LIMIT 8")->fetchAll();
render_header('Admin Dashboard', 'Kinyan admin dashboard.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Admin dashboard</h1><p>Moderate listings, wanted posts, users, and site settings.</p></div>
    <nav class="admin-tabs"><a href="listings.php">Listings</a><a href="wanted.php">Wanted</a><a href="reports.php">Reports</a><a href="users.php">Users</a><a href="categories.php">Categories</a><a href="settings.php">Settings</a></nav>
    <div class="stat-grid"><?php foreach ($stats as $label=>$value): ?><div class="stat"><span><?= e($label) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?></div>
    <section class="details-card"><h2>Most viewed cars</h2><div class="table-wrap"><table><thead><tr><th>Listing</th><th>Views</th></tr></thead><tbody><?php foreach ($mostViewed as $car): ?><tr><td><a href="../listing.php?id=<?= (int)$car['id'] ?>"><?= e($car['title']) ?></a></td><td><?= (int)$car['views'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</section>
<?php render_footer(); ?>
