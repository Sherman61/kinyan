<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cards.php';

$featured = db()->query("SELECT c.*, (SELECT image_path FROM car_images i WHERE i.car_listing_id = c.id ORDER BY sort_order, id LIMIT 1) AS primary_image FROM car_listings c WHERE c.status = 'active' AND c.featured = 1 ORDER BY c.created_at DESC LIMIT 4")->fetchAll();
$latestCars = db()->query("SELECT c.*, (SELECT image_path FROM car_images i WHERE i.car_listing_id = c.id ORDER BY sort_order, id LIMIT 1) AS primary_image FROM car_listings c WHERE c.status = 'active' ORDER BY c.created_at DESC LIMIT 8")->fetchAll();
$latestWanted = db()->query("SELECT * FROM wanted_posts WHERE status = 'active' ORDER BY created_at DESC LIMIT 4")->fetchAll();
$makes = db()->query("SELECT make, COUNT(*) total FROM car_listings WHERE status = 'active' GROUP BY make ORDER BY total DESC, make ASC LIMIT 12")->fetchAll();

render_header('Kinyan', 'Find your next car or the right buyer on Kinyan.');
?>
<section class="hero">
    <div class="hero-content">
        <span class="eyebrow">Direct-contact car marketplace</span>
        <h1>Find your next car or the right buyer on Kinyan.</h1>
        <p>A direct-contact car marketplace for buying, selling, and posting what you’re looking for.</p>
        <form class="hero-search" action="cars.php" method="get">
            <input name="q" type="search" placeholder="Search make, model, year, city..." aria-label="Search cars">
            <button class="button" type="submit">Search Cars</button>
        </form>
        <div class="hero-actions">
            <a class="button" href="cars.php">Browse Cars</a>
            <a class="button secondary" href="post-car.php">Post Your Car</a>
            <a class="button ghost" href="post-wanted.php">Post What You’re Looking For</a>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<section class="section">
    <div class="section-heading"><h2>Featured cars</h2><a href="cars.php?featured=1">View all</a></div>
    <div class="grid cards-grid"><?php foreach ($featured as $car) render_car_card($car); ?></div>
</section>
<?php endif; ?>

<section class="section">
    <div class="section-heading"><h2>Latest cars for sale</h2><a href="cars.php">Browse all</a></div>
    <?php if ($latestCars): ?><div class="grid cards-grid"><?php foreach ($latestCars as $car) render_car_card($car); ?></div>
    <?php else: ?><div class="empty-state"><h3>No active cars yet</h3><p>Be the first to post a vehicle on Kinyan.</p><a class="button" href="post-car.php">Post a car</a></div><?php endif; ?>
</section>

<section class="section split-section">
    <div>
        <div class="section-heading"><h2>Latest wanted posts</h2><a href="wanted.php">Browse requests</a></div>
        <div class="stack"><?php foreach ($latestWanted as $post) render_wanted_card($post); ?></div>
        <?php if (!$latestWanted): ?><div class="empty-state"><h3>No active buyer requests yet</h3><p>Post the exact car you are looking for.</p></div><?php endif; ?>
    </div>
    <div class="why-panel">
        <h2>Why use Kinyan?</h2>
        <ul class="check-list">
            <li>No checkout, cart, or online payments.</li>
            <li>Call, text, or email sellers and buyers directly.</li>
            <li>Wanted posts help sellers find serious buyers.</li>
            <li>Admin approval keeps listings cleaner.</li>
        </ul>
    </div>
</section>

<section class="section">
    <div class="section-heading"><h2>Popular makes</h2></div>
    <div class="chips">
        <?php foreach ($makes as $make): ?><a href="cars.php?make=<?= e($make['make']) ?>"><?= e($make['make']) ?> <span><?= (int)$make['total'] ?></span></a><?php endforeach; ?>
        <?php if (!$makes): foreach (['Toyota','Honda','Ford','Chevrolet','Nissan','Hyundai'] as $make): ?><a href="cars.php?make=<?= e($make) ?>"><?= e($make) ?></a><?php endforeach; endif; ?>
    </div>
</section>
<?php render_footer(); ?>
