<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$stats = [
    ['label' => 'Total users', 'value' => db()->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'href' => 'users.php'],
    ['label' => 'Active cars', 'value' => db()->query("SELECT COUNT(*) FROM car_listings WHERE status='active'")->fetchColumn(), 'href' => 'listings.php?status=active'],
    ['label' => 'Pending cars', 'value' => db()->query("SELECT COUNT(*) FROM car_listings WHERE status='pending'")->fetchColumn(), 'href' => 'listings.php?status=pending'],
    ['label' => 'Pending wanted', 'value' => db()->query("SELECT COUNT(*) FROM wanted_posts WHERE status='pending'")->fetchColumn(), 'href' => 'wanted.php?status=pending'],
    ['label' => 'Sold listings', 'value' => db()->query("SELECT COUNT(*) FROM car_listings WHERE status='sold'")->fetchColumn(), 'href' => 'listings.php?status=sold'],
    ['label' => 'Open reports', 'value' => db()->query("SELECT COUNT(*) FROM reports WHERE status IN ('open','investigating')")->fetchColumn(), 'href' => 'reports.php?status=open'],
    ['label' => 'Open errors', 'value' => app_open_error_count(), 'href' => 'errors.php?status=open'],
];
$mostViewed = db()->query("SELECT id,title,views FROM car_listings ORDER BY views DESC LIMIT 8")->fetchAll();
render_header('Admin Dashboard', 'Kinyan admin dashboard.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Admin dashboard</h1><p>Moderate listings, wanted posts, users, and site settings.</p></div>
    <?php render_admin_nav('index'); ?>
    <div class="stat-grid admin-stat-grid"><?php foreach ($stats as $stat): ?><a class="stat" href="<?= e($stat['href']) ?>"><span><?= e($stat['label']) ?></span><strong><?= (int)$stat['value'] ?></strong><small>View details</small></a><?php endforeach; ?></div>
    <section class="details-card admin-most-viewed"><div class="section-heading"><h2><a href="listings.php?sort=views">Most viewed cars</a></h2><a href="listings.php?sort=views">View all</a></div><div class="table-wrap"><table><thead><tr><th>Listing</th><th>Views</th></tr></thead><tbody><?php foreach ($mostViewed as $car): ?><tr><td><a href="../listing.php?id=<?= (int)$car['id'] ?>"><?= e($car['title']) ?></a></td><td><a href="../listing.php?id=<?= (int)$car['id'] ?>"><?= (int)$car['views'] ?></a></td></tr><?php endforeach; ?></tbody></table></div></section>
</section>
<?php render_footer(); ?>
