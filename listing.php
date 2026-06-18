<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cards.php';
$id = (int)($_GET['id'] ?? 0);
if (isset($_GET['contact'])) { require_rate_limit('contact_car_' . $id, 5); save_contact_click('car', $id, $_GET['contact']); exit('ok'); }
if (is_post()) { verify_csrf(); report_target('car', $id, trim($_POST['reason'] ?? 'Concern'), trim($_POST['details'] ?? '')); flash('success', 'Report submitted.'); redirect('listing.php?id=' . $id); }
$stmt = db()->prepare('SELECT * FROM car_listings WHERE id = ?');
$stmt->execute([$id]);
$car = $stmt->fetch();
if (!$car || ($car['status'] !== 'active' && !owns_listing('car_listings', $id) && !is_admin())) { http_response_code(404); die('Listing not found.'); }
increment_view('car_listings', $id);
$images = car_images($id);
$primary = $images[0]['image_path'] ?? 'assets/css/car-placeholder.svg';
$primaryTitle = $images[0]['image_title'] ?? $car['title'];
$sim = db()->prepare("SELECT c.*, (SELECT image_path FROM car_images i WHERE i.car_listing_id = c.id ORDER BY sort_order, id LIMIT 1) AS primary_image FROM car_listings c WHERE c.status='active' AND c.id <> ? AND (c.make = ? OR c.body_type = ?) ORDER BY c.created_at DESC LIMIT 4");
$sim->execute([$id, $car['make'], $car['body_type']]);
$vehicleName = trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' ' . $car['trim']);
$shareDescription = $vehicleName . ' on Kinyan: ' . (!empty($car['lease_takeover']) ? money($car['lease_monthly_payment']) . '/mo lease takeover' : money($car['price'])) . ', ' . number_short($car['mileage']) . ' miles, located in ' . $car['city'] . ', ' . $car['state'] . '. Contact the seller directly.';
render_header($car['title'], $shareDescription, ['type'=>'product','image'=>$primary]);
?>
<script type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'Vehicle','name'=>$car['title'],'brand'=>$car['make'],'model'=>$car['model'],'vehicleModelDate'=>$car['year'],'mileageFromOdometer'=>$car['mileage'],'offers'=>['@type'=>'Offer','price'=>$car['price'],'priceCurrency'=>'USD','availability'=>'https://schema.org/InStock']], JSON_UNESCAPED_SLASHES) ?></script>
<section class="details-layout">
    <div>
        <div class="gallery">
            <div class="gallery-frame">
                <button class="gallery-nav prev" type="button" data-gallery-prev aria-label="Previous photo">‹</button>
                <img class="gallery-main" src="<?= e($primary) ?>" alt="<?= e($primaryTitle) ?>" data-gallery-main data-gallery-open>
                <button class="gallery-nav next" type="button" data-gallery-next aria-label="Next photo">›</button>
            </div>
            <p class="gallery-caption" data-gallery-caption><?= e($primaryTitle) ?></p>
            <div class="thumbs" data-gallery-thumbs><?php foreach ($images as $img): ?><button data-thumb="<?= e($img['image_path']) ?>" data-title="<?= e($img['image_title'] ?: $car['title']) ?>"><img src="<?= e($img['image_path']) ?>" alt="<?= e($img['image_title'] ?: $car['title']) ?>"></button><?php endforeach; ?></div>
        </div>
        <section class="details-card"><h2>Description</h2><p><?= nl2br(e($car['description'])) ?></p></section>
        <section class="details-card"><h2>Vehicle details</h2><div class="spec-grid">
            <?php foreach (['Year'=>'year','Make'=>'make','Model'=>'model','Trim'=>'trim','Mileage'=>'mileage','Body type'=>'body_type','Transmission'=>'transmission','Drivetrain'=>'drivetrain','Fuel'=>'fuel_type','Engine'=>'engine','Condition'=>'condition_status','Accident history'=>'accident_history','Clean title'=>'clean_title','Lease takeover'=>'lease_takeover','VIN'=>'vin'] as $label=>$key): ?>
            <div><span><?= e($label) ?></span><strong><?= in_array($key, ['clean_title','lease_takeover'], true) ? (!empty($car[$key]) ? 'Yes' : 'No') : e((string)$car[$key]) ?></strong></div>
            <?php endforeach; ?>
        </div></section>
        <?php if (!empty($car['lease_takeover'])): ?>
        <section class="details-card"><h2>Lease takeover details</h2><div class="spec-grid">
            <?php foreach (['Lease end date'=>'lease_end_date','Months left'=>'lease_months_left','Monthly payment'=>'lease_monthly_payment','Due at takeover'=>'lease_down_payment','Annual mileage allowance'=>'lease_mileage_allowance','Miles used'=>'lease_miles_used','Transfer fee'=>'lease_transfer_fee','Lease company'=>'lease_company'] as $label=>$key): ?>
            <div><span><?= e($label) ?></span><strong><?= in_array($key, ['lease_monthly_payment','lease_down_payment','lease_transfer_fee'], true) ? money($car[$key]) : e((string)($car[$key] ?? '')) ?></strong></div>
            <?php endforeach; ?>
        </div></section>
        <?php endif; ?>
    </div>
    <aside class="contact-panel">
        <span class="badge <?= e($car['status']) ?>"><?= e(ucfirst($car['status'])) ?></span>
        <h1><?= e(trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' ' . $car['trim'])) ?></h1>
        <div class="price"><?= !empty($car['lease_takeover']) ? money($car['lease_monthly_payment']) . '/mo' : money($car['price']) ?></div>
        <?php if (!empty($car['lease_takeover'])): ?><p><span class="badge teal">Lease Takeover</span> <?= e((string)$car['lease_months_left']) ?> months left · <?= money($car['lease_down_payment']) ?> due at takeover</p><?php endif; ?>
        <p><?= e(number_short($car['mileage'])) ?> miles · <?= e($car['city']) ?>, <?= e($car['state']) ?></p>
        <h2>Contact Seller Directly</h2>
        <a class="button full-width" data-track-contact="call" href="tel:<?= e(clean_phone_href($car['seller_phone'])) ?>">Call Seller</a>
        <a class="button secondary full-width" data-track-contact="text" href="sms:<?= e(clean_phone_href($car['seller_phone'])) ?>">Text Seller</a>
        <?php if ($car['seller_email']): ?><a class="button ghost full-width" data-track-contact="email" href="mailto:<?= e($car['seller_email']) ?>">Email Seller</a><?php endif; ?>
        <button class="button ghost full-width" data-copy-link>Copy link</button>
        <button class="button ghost full-width" data-share data-share-text="<?= e($shareDescription) ?>">Share</button>
        <form method="post" class="report-form"><?= csrf_field() ?><input name="reason" placeholder="Report reason"><textarea name="details" placeholder="Details"></textarea><button class="danger" type="submit">Report listing</button></form>
    </aside>
</section>
<section class="section"><div class="section-heading"><h2>Similar cars</h2></div><div class="grid cards-grid"><?php foreach ($sim->fetchAll() as $item) render_car_card($item); ?></div></section>
<div class="image-lightbox" data-lightbox hidden>
    <button class="lightbox-close" type="button" data-lightbox-close aria-label="Close enlarged photo">×</button>
    <button class="lightbox-nav prev" type="button" data-gallery-prev aria-label="Previous photo">‹</button>
    <figure><img src="<?= e($primary) ?>" alt="<?= e($primaryTitle) ?>" data-lightbox-image><figcaption data-lightbox-caption><?= e($primaryTitle) ?></figcaption></figure>
    <button class="lightbox-nav next" type="button" data-gallery-next aria-label="Next photo">›</button>
</div>
<?php render_footer(); ?>
