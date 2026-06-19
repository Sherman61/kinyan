<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

if (is_post()) {
    verify_csrf();
    require_app_rate_limit('edit_library_image_title', 40, 60 * 60);

    $imagePath = trim((string)($_POST['image_path'] ?? ''));
    $imageTitle = trim((string)($_POST['image_title'] ?? ''));

    if ($imagePath === '') {
        flash('error', 'That image could not be found. Please refresh the page and try again.');
    } elseif (mb_strlen($imageTitle) > 160) {
        flash('error', 'Image titles must be 160 characters or fewer.');
    } else {
        $updateStmt = db()->prepare('UPDATE car_images i
            JOIN car_listings c ON c.id = i.car_listing_id
            SET i.image_title = ?
            WHERE c.user_id = ? AND i.image_path = ?');
        $updateStmt->execute([$imageTitle, current_user()['id'], $imagePath]);

        if ($updateStmt->rowCount() > 0) {
            flash('success', $imageTitle === '' ? 'Image title removed.' : 'Image title saved.');
        } else {
            $ownedStmt = db()->prepare('SELECT 1 FROM car_images i JOIN car_listings c ON c.id = i.car_listing_id WHERE c.user_id = ? AND i.image_path = ? LIMIT 1');
            $ownedStmt->execute([current_user()['id'], $imagePath]);
            $imageExists = (bool)$ownedStmt->fetchColumn();
            flash($imageExists ? 'info' : 'error', $imageExists ? 'The image title was already up to date.' : 'That image could not be found. Please refresh the page and try again.');
        }
    }

    redirect('library.php');
}

$stmt = db()->prepare('SELECT i.image_path, COALESCE(MAX(NULLIF(i.image_title, "")), "") image_title, MAX(i.created_at) created_at, COUNT(DISTINCT c.id) listing_count
    FROM car_images i
    JOIN car_listings c ON c.id = i.car_listing_id
    WHERE c.user_id = ?
    GROUP BY i.image_path
    ORDER BY created_at DESC');
$stmt->execute([current_user()['id']]);
$images = $stmt->fetchAll();

$linksStmt = db()->prepare('SELECT c.id, c.title, c.year, c.make, c.model, i.image_title, i.sort_order
    FROM car_images i
    JOIN car_listings c ON c.id = i.car_listing_id
    WHERE c.user_id = ? AND i.image_path = ?
    ORDER BY c.created_at DESC, i.sort_order ASC');

render_header('Image Library', 'Manage and review your uploaded Kinyan car images.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Image library</h1><p>All photos you have uploaded, with the car listings each photo is attached to.</p></div>
    <div class="dash-actions"><a class="button" href="post-car.php">Post a car</a><a class="button secondary" href="dashboard.php">Dashboard</a></div>
    <?php if ($images): ?>
    <div class="library-page-grid">
        <?php foreach ($images as $image): ?>
            <?php $linksStmt->execute([current_user()['id'], $image['image_path']]); $links = $linksStmt->fetchAll(); ?>
            <article class="library-card">
                <img src="<?= e($image['image_path']) ?>" alt="<?= e($image['image_title'] ?: 'Car photo') ?>">
                <div>
                    <h2><?= e($image['image_title'] ?: 'Untitled photo') ?></h2>
                    <p><?= (int)$image['listing_count'] ?> synced listing<?= (int)$image['listing_count'] === 1 ? '' : 's' ?></p>
                    <form method="post" class="library-title-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="image_path" value="<?= e($image['image_path']) ?>">
                        <label for="image-title-<?= md5((string)$image['image_path']) ?>">Image title</label>
                        <div>
                            <input id="image-title-<?= md5((string)$image['image_path']) ?>" name="image_title" value="<?= e($image['image_title']) ?>" maxlength="160" placeholder="Front exterior, dashboard, odometer">
                            <button class="button small" type="submit">Save</button>
                        </div>
                        <small>This title is used anywhere this photo is synced.</small>
                    </form>
                    <div class="synced-list">
                        <?php foreach ($links as $link): ?>
                            <a href="post-car.php?id=<?= (int)$link['id'] ?>">
                                <?= e(trim($link['year'] . ' ' . $link['make'] . ' ' . $link['model'])) ?>
                                <span><?= e($link['title']) ?> · position <?= (int)$link['sort_order'] + 1 ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><h3>No images yet</h3><p>Upload photos while creating or editing a car listing, then they will appear here.</p><a class="button" href="post-car.php">Post a car</a></div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
