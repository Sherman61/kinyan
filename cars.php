<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cards.php';
require_once __DIR__ . '/includes/options.php';

$where = ["c.status = 'active'"];
$params = [];
$q = trim($_GET['q'] ?? '');
foreach (['make','model','body_type','transmission','fuel_type','condition_status','vehicle_history','state'] as $field) {
    if (!empty($_GET[$field])) {
        $where[] = "c.$field = ?";
        $params[] = $_GET[$field];
    }
}
$listingType = $_GET['listing_type'] ?? '';
if ($listingType === 'sale') {
    $where[] = 'c.lease_takeover = 0';
} elseif ($listingType === 'lease') {
    $where[] = 'c.lease_takeover = 1';
}
if ($q !== '') {
    $where[] = '(c.title LIKE ? OR c.make LIKE ? OR c.model LIKE ? OR c.city LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
foreach ([['min_year','c.year >= ?'],['max_year','c.year <= ?'],['max_mileage','c.mileage <= ?']] as [$key,$sql]) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $where[] = $sql;
        $params[] = (int)$_GET[$key];
    }
}
foreach ([['min_price','c.price >= ?'],['max_price','c.price <= ?']] as [$key,$sql]) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $where[] = 'c.lease_takeover = 0';
        $where[] = $sql;
        $params[] = (int)$_GET[$key];
    }
}
foreach ([['min_monthly_payment','c.lease_monthly_payment >= ?'],['max_monthly_payment','c.lease_monthly_payment <= ?'],['max_takeover_due','c.lease_down_payment <= ?'],['min_months_left','c.lease_months_left >= ?'],['max_months_left','c.lease_months_left <= ?']] as [$key,$sql]) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $where[] = 'c.lease_takeover = 1';
        $where[] = $sql;
        $params[] = (int)$_GET[$key];
    }
}
if (isset($_GET['clean_title']) && $_GET['clean_title'] !== '') {
    $where[] = 'c.clean_title = ?';
    $params[] = (int)$_GET['clean_title'];
}
if (!empty($_GET['featured'])) {
    $where[] = 'c.featured = 1';
}
$sorts = [
    'newest' => 'c.created_at DESC',
    'oldest' => 'c.created_at ASC',
    'price_asc' => 'c.price ASC',
    'price_desc' => 'c.price DESC',
    'monthly_asc' => 'c.lease_takeover DESC, c.lease_monthly_payment ASC',
    'monthly_desc' => 'c.lease_takeover DESC, c.lease_monthly_payment DESC',
    'takeover_due_asc' => 'c.lease_takeover DESC, c.lease_down_payment ASC',
    'mileage_asc' => 'c.mileage ASC',
    'year_desc' => 'c.year DESC',
    'year_asc' => 'c.year ASC',
];
$sort = $sorts[$_GET['sort'] ?? 'newest'] ?? $sorts['newest'];
$sql = "SELECT c.*, (SELECT image_path FROM car_images i WHERE i.car_listing_id = c.id ORDER BY sort_order, id LIMIT 1) AS primary_image FROM car_listings c WHERE " . implode(' AND ', $where) . " ORDER BY $sort LIMIT 80";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();

render_header('Cars for Sale', 'Search cars for sale on Kinyan and contact sellers directly.');
?>
<section class="page-title">
    <h1>Cars for sale</h1>
    <p>Search active vehicle listings and contact sellers directly.</p>
</section>
<section class="browse-layout filters-collapsed" data-browse-layout>
    <aside class="filters" data-filters>
        <button class="filter-close" data-filter-toggle>Filters</button>
        <form method="get" class="filter-form">
            <input name="q" value="<?= e($q) ?>" placeholder="Search make, model, city">
            <select name="listing_type"><option value="">Sale and lease</option><option value="sale" <?= selected($listingType, 'sale') ?>>Regular sale only</option><option value="lease" <?= selected($listingType, 'lease') ?>>Lease takeover only</option></select>
            <input name="make" value="<?= e($_GET['make'] ?? '') ?>" placeholder="Make, e.g. Toyota">
            <input name="model" value="<?= e($_GET['model'] ?? '') ?>" placeholder="Model, e.g. Sienna">
            <div class="two"><input type="number" name="min_year" value="<?= e($_GET['min_year'] ?? '') ?>" placeholder="Min year"><input type="number" name="max_year" value="<?= e($_GET['max_year'] ?? '') ?>" placeholder="Max year"></div>
            <div class="filter-group"><span>Regular sale price</span><div class="two"><input type="number" name="min_price" value="<?= e($_GET['min_price'] ?? '') ?>" placeholder="Min sale price"><input type="number" name="max_price" value="<?= e($_GET['max_price'] ?? '') ?>" placeholder="Max sale price"></div></div>
            <div class="filter-group"><span>Lease takeover terms</span><div class="two"><input type="number" name="min_monthly_payment" value="<?= e($_GET['min_monthly_payment'] ?? '') ?>" placeholder="Min monthly"><input type="number" name="max_monthly_payment" value="<?= e($_GET['max_monthly_payment'] ?? '') ?>" placeholder="Max monthly"></div><input type="number" name="max_takeover_due" value="<?= e($_GET['max_takeover_due'] ?? '') ?>" placeholder="Max due at takeover"><div class="two"><input type="number" name="min_months_left" value="<?= e($_GET['min_months_left'] ?? '') ?>" placeholder="Min months left"><input type="number" name="max_months_left" value="<?= e($_GET['max_months_left'] ?? '') ?>" placeholder="Max months left"></div></div>
            <input type="number" name="max_mileage" value="<?= e($_GET['max_mileage'] ?? '') ?>" placeholder="Max mileage">
            <select name="body_type"><option value="">Body type</option><?php foreach ($bodyTypes as $v): ?><option <?= selected($_GET['body_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="transmission"><option value="">Transmission</option><?php foreach ($transmissions as $v): ?><option <?= selected($_GET['transmission'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="fuel_type"><option value="">Fuel type</option><?php foreach ($fuelTypes as $v): ?><option <?= selected($_GET['fuel_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="vehicle_history"><option value="">New or used</option><?php foreach ($vehicleHistories as $v): ?><option <?= selected($_GET['vehicle_history'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="condition_status"><option value="">Condition</option><?php foreach ($conditions as $v): ?><option <?= selected($_GET['condition_status'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="clean_title"><option value="">Clean title</option><option value="1" <?= selected($_GET['clean_title'] ?? '', '1') ?>>Yes</option><option value="0" <?= selected($_GET['clean_title'] ?? '', '0') ?>>No</option></select>
            <input name="state" value="<?= e($_GET['state'] ?? '') ?>" placeholder="State">
            <button class="button" type="submit">Apply filters</button>
            <a class="button ghost" href="cars.php">Clear</a>
        </form>
    </aside>
    <div class="browse-main">
        <div class="browse-toolbar">
            <button class="button secondary filter-toggle-button" data-filter-toggle>Filters</button>
            <span><?= count($cars) ?> cars</span>
            <form method="get">
                <?php foreach ($_GET as $k => $v): if ($k !== 'sort'): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
                <select name="sort" onchange="this.form.requestSubmit()">
                    <option value="newest" <?= selected($_GET['sort'] ?? '', 'newest') ?>>Newest</option>
                    <option value="oldest" <?= selected($_GET['sort'] ?? '', 'oldest') ?>>Oldest</option>
                    <option value="price_asc" <?= selected($_GET['sort'] ?? '', 'price_asc') ?>>Price low to high</option>
                    <option value="price_desc" <?= selected($_GET['sort'] ?? '', 'price_desc') ?>>Price high to low</option>
                    <option value="monthly_asc" <?= selected($_GET['sort'] ?? '', 'monthly_asc') ?>>Lease payment low to high</option>
                    <option value="monthly_desc" <?= selected($_GET['sort'] ?? '', 'monthly_desc') ?>>Lease payment high to low</option>
                    <option value="takeover_due_asc" <?= selected($_GET['sort'] ?? '', 'takeover_due_asc') ?>>Takeover due low to high</option>
                    <option value="mileage_asc" <?= selected($_GET['sort'] ?? '', 'mileage_asc') ?>>Mileage low to high</option>
                    <option value="year_desc" <?= selected($_GET['sort'] ?? '', 'year_desc') ?>>Year newest first</option>
                    <option value="year_asc" <?= selected($_GET['sort'] ?? '', 'year_asc') ?>>Year oldest first</option>
                </select>
            </form>
            <button class="icon-button" data-grid-toggle title="Grid or list">▦</button>
        </div>
        <?php if ($cars): ?><div class="grid cards-grid" data-results-grid><?php foreach ($cars as $car) render_car_card($car); ?></div>
        <?php else: ?><div class="empty-state"><h3>No cars match your search</h3><p>Try broadening your filters or checking wanted posts.</p></div><?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
