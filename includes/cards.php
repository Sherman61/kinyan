<?php
declare(strict_types=1);

function render_car_card(array $car): void
{
    $image = $car['primary_image'] ?? car_primary_image((int)$car['id']);
    $fallback = 'assets/css/car-placeholder.svg';
    ?>
    <article class="card car-card" data-favorite-id="<?= (int)$car['id'] ?>">
        <a class="card-image" href="listing.php?id=<?= (int)$car['id'] ?>&slug=<?= e(slugify($car['title'])) ?>">
            <img src="<?= e($image ?: $fallback) ?>" alt="<?= e(trim(($car['year'] ?? '') . ' ' . ($car['make'] ?? '') . ' ' . ($car['model'] ?? ''))) ?>">
            <div class="badges">
                <?php if (!empty($car['featured'])): ?><span class="badge gold">Featured</span><?php endif; ?>
                <?php if (!empty($car['vehicle_history'])): ?><span class="badge"><?= e($car['vehicle_history']) ?></span><?php endif; ?>
                <?php if (!empty($car['lease_takeover'])): ?><span class="badge teal">Lease Takeover</span><?php endif; ?>
                <?php if (($car['status'] ?? '') !== 'active'): ?><span class="badge"><?= e(ucfirst($car['status'])) ?></span><?php endif; ?>
                <?php if (!empty($car['clean_title'])): ?><span class="badge green">Clean Title</span><?php endif; ?>
            </div>
        </a>
        <div class="card-body">
            <div class="card-topline">
                <strong><?= !empty($car['lease_takeover']) ? money($car['lease_monthly_payment']) . '/mo' : money($car['price']) ?></strong>
                <button class="icon-button" data-save-car="<?= (int)$car['id'] ?>" title="Save car">♡</button>
            </div>
            <h3><a href="listing.php?id=<?= (int)$car['id'] ?>"><?= e(trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'] . ' ' . ($car['trim'] ?? ''))) ?></a></h3>
            <p><?= e(number_short($car['mileage'])) ?> miles · <?= e($car['city']) ?>, <?= e($car['state']) ?></p>
            <?php if (!empty($car['lease_takeover'])): ?><p><?= e((string)$car['lease_months_left']) ?> months left · <?= money($car['lease_down_payment']) ?> due at takeover</p><?php endif; ?>
            <span class="muted">Posted <?= e(date('M j, Y', strtotime($car['created_at']))) ?></span>
        </div>
    </article>
    <?php
}

function render_wanted_card(array $post): void
{
    ?>
    <article class="card wanted-card">
        <div class="card-body">
            <div class="card-topline">
                <span class="badge teal">Wanted</span>
                <?php if (($post['status'] ?? '') !== 'active'): ?><span class="badge"><?= e(ucfirst($post['status'])) ?></span><?php endif; ?>
            </div>
            <h3><a href="wanted-details.php?id=<?= (int)$post['id'] ?>&slug=<?= e(slugify($post['title'])) ?>"><?= e($post['title']) ?></a></h3>
            <p><?= e(trim(($post['preferred_make'] ?? '') . ' ' . ($post['preferred_model'] ?? ''))) ?: 'Open to options' ?></p>
            <p><strong><?= money($post['min_budget']) ?> - <?= money($post['max_budget']) ?></strong> · up to <?= e(number_short($post['max_mileage'])) ?> miles</p>
            <p><?= e($post['location']) ?> · <?= e($post['travel_distance']) ?> mi travel</p>
            <div class="card-actions">
                <a class="button small" href="wanted-details.php?id=<?= (int)$post['id'] ?>">Contact buyer</a>
                <span class="muted"><?= e(date('M j, Y', strtotime($post['created_at']))) ?></span>
            </div>
        </div>
    </article>
    <?php
}
