<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$ids = preg_split('/\s*,\s*/', (string)($_GET['ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn(int $id): bool => $id > 0))), 0, 4);
$cars = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $order = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM car_listings WHERE status = 'active' AND id IN ({$placeholders}) ORDER BY FIELD(id, {$order})");
    $stmt->execute([...$ids, ...$ids]);
    $cars = $stmt->fetchAll();
}

render_header('Compare Cars', 'Compare selected Kinyan vehicle listings side by side.');
$fields = [
    'Price' => fn(array $car): string => !empty($car['lease_takeover']) ? money($car['lease_monthly_payment']) . '/month' : money($car['price']),
    'Listing type' => fn(array $car): string => !empty($car['lease_takeover']) ? 'Lease takeover' : 'Regular sale',
    'Year' => fn(array $car): string => (string)$car['year'],
    'Mileage' => fn(array $car): string => number_short($car['mileage']) . ' miles',
    'Condition' => fn(array $car): string => (string)$car['condition_status'],
    'Body type' => fn(array $car): string => (string)$car['body_type'],
    'Fuel' => fn(array $car): string => (string)$car['fuel_type'],
    'Transmission' => fn(array $car): string => (string)$car['transmission'],
    'Title' => fn(array $car): string => !empty($car['clean_title']) ? 'Clean title reported' : 'Not reported as clean',
    'Location' => fn(array $car): string => $car['city'] . ', ' . $car['state'],
];
?>
<section class="section compare-page">
    <div class="section-heading"><div><h1>Compare cars</h1><p class="muted">Review up to four active listings side by side.</p></div><a class="button secondary" href="cars.php">Browse cars</a></div>
    <?php if (count($cars) < 2): ?>
        <div class="empty-state"><h2>Select at least two cars</h2><p>Use the compare button on car cards, then return here to review them together.</p><a class="button" href="cars.php">Choose cars</a></div>
    <?php else: ?>
        <div class="table-wrap"><table class="compare-table"><thead><tr><th>Detail</th><?php foreach ($cars as $car): ?><th><a href="listing.php?id=<?= (int)$car['id'] ?>"><?= e($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) ?></a></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($fields as $label => $formatter): ?><tr><th scope="row"><?= e($label) ?></th><?php foreach ($cars as $car): ?><td><?= e($formatter($car)) ?></td><?php endforeach; ?></tr><?php endforeach; ?><tr><th scope="row">Action</th><?php foreach ($cars as $car): ?><td><a class="button small" href="listing.php?id=<?= (int)$car['id'] ?>">View listing</a></td><?php endforeach; ?></tr></tbody></table></div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
