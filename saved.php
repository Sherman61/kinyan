<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cards.php';

function saved_listing_ids(): array
{
    $ids = preg_split('/\s*,\s*/', (string)($_GET['ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    return array_slice($ids, 0, 80);
}

function saved_listing_rows(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $orderPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT c.*, (SELECT image_path FROM car_images i WHERE i.car_listing_id = c.id ORDER BY sort_order, id LIMIT 1) AS primary_image
        FROM car_listings c
        WHERE c.status = 'active' AND c.id IN ($placeholders)
        ORDER BY FIELD(c.id, $orderPlaceholders)";
    $stmt = db()->prepare($sql);
    $stmt->execute([...$ids, ...$ids]);
    return $stmt->fetchAll();
}

$ids = saved_listing_ids();
$cars = saved_listing_rows($ids);

if (($_GET['partial'] ?? '') === '1') {
    if ($cars) {
        ?><div class="grid cards-grid" data-results-grid><?php foreach ($cars as $car) render_car_card($car); ?></div><?php
    } else {
        ?><div class="empty-state"><h3>No saved cars found</h3><p>Your saved cars may have been sold, removed, or saved in another browser.</p><a class="button" href="cars.php">Browse cars</a></div><?php
    }
    exit;
}

require_once __DIR__ . '/includes/layout.php';
render_header('Saved Listings', 'See the car listings you saved with the heart button.');
?>
<section class="section saved-page" data-saved-page>
    <div class="section-heading">
        <div>
            <h1>Saved listings</h1>
            <p class="muted" data-saved-status>Loading your saved cars...</p>
        </div>
        <a class="button secondary" href="cars.php">Browse more cars</a>
    </div>
    <div data-saved-results>
        <div class="grid cards-grid">
            <?php for ($i = 0; $i < 4; $i++): ?><article class="skeleton-card"><div></div><span></span><span></span><span></span></article><?php endfor; ?>
        </div>
    </div>
</section>
<?php render_footer(); ?>
