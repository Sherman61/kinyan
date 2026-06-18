<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

if (is_post()) {
    verify_csrf();
    $type = $_POST['type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $table = $type === 'wanted' ? 'wanted_posts' : 'car_listings';
    if (!owns_listing($table, $id)) die('Not allowed.');
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) die('Post not found.');
    if ($action === 'delete') {
        $stmt = db()->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        flash('success', 'Post deleted.');
    } elseif (in_array($action, array_keys(status_options_for_user($post, $table)), true) && $table === 'car_listings') {
        $stmt = db()->prepare('UPDATE car_listings SET status = ? WHERE id = ?');
        $stmt->execute([$action, $id]);
        flash('success', 'Listing updated.');
    } elseif (in_array($action, array_keys(status_options_for_user($post, $table)), true)) {
        $stmt = db()->prepare('UPDATE wanted_posts SET status = ? WHERE id = ?');
        $stmt->execute([$action, $id]);
        flash('success', 'Wanted post updated.');
    }
    redirect('dashboard.php');
}

$cars = db()->prepare('SELECT * FROM car_listings WHERE user_id = ? ORDER BY created_at DESC');
$cars->execute([current_user()['id']]);
$wanted = db()->prepare('SELECT * FROM wanted_posts WHERE user_id = ? ORDER BY created_at DESC');
$wanted->execute([current_user()['id']]);
render_header('Dashboard', 'Manage your Kinyan listings and wanted posts.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Dashboard</h1><p>Manage your listings, buyer requests, and profile.</p></div>
    <div class="dash-actions"><a class="button" href="post-car.php">Post a car</a><a class="button secondary" href="post-wanted.php">Post wanted</a></div>
    <section class="details-card">
        <h2>My car listings</h2>
        <div class="table-wrap"><table><thead><tr><th>Listing</th><th>Status</th><th>Views</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($cars->fetchAll() as $car): ?><tr><td><strong><?= e($car['title']) ?></strong><br><span><?= e($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) ?></span></td><td><span class="badge"><?= e($car['status']) ?></span></td><td><?= (int)$car['views'] ?></td><td class="table-actions"><a class="icon-action" data-confirm="Open editor for this listing?" href="post-car.php?id=<?= (int)$car['id'] ?>"><span aria-hidden="true">✎</span>Edit</a><a class="icon-action" href="listing.php?id=<?= (int)$car['id'] ?>"><span aria-hidden="true">↗</span>View</a><form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="car"><input type="hidden" name="id" value="<?= (int)$car['id'] ?>"><?php foreach (status_options_for_user($car, 'car_listings') as $statusValue => $statusLabel): ?><button name="action" value="<?= e($statusValue) ?>" data-confirm="Set this listing to <?= e(strtolower($statusLabel)) ?>?"><span aria-hidden="true"><?= $statusValue === 'active' ? '✓' : ($statusValue === 'sold' ? '$' : '−') ?></span><?= e($statusLabel) ?></button><?php endforeach; ?><button class="danger-link" name="action" value="delete" data-confirm="Delete this car listing permanently?"><span aria-hidden="true">×</span>Delete</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <section class="details-card">
        <h2>My wanted posts</h2>
        <div class="table-wrap"><table><thead><tr><th>Post</th><th>Status</th><th>Views</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($wanted->fetchAll() as $post): ?><tr><td><strong><?= e($post['title']) ?></strong></td><td><span class="badge"><?= e($post['status']) ?></span></td><td><?= (int)$post['views'] ?></td><td class="table-actions"><a class="icon-action" data-confirm="Open editor for this wanted post?" href="post-wanted.php?id=<?= (int)$post['id'] ?>"><span aria-hidden="true">✎</span>Edit</a><a class="icon-action" href="wanted-details.php?id=<?= (int)$post['id'] ?>"><span aria-hidden="true">↗</span>View</a><form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="wanted"><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php foreach (status_options_for_user($post, 'wanted_posts') as $statusValue => $statusLabel): ?><button name="action" value="<?= e($statusValue) ?>" data-confirm="Set this wanted post to <?= e(strtolower($statusLabel)) ?>?"><span aria-hidden="true"><?= $statusValue === 'active' ? '✓' : '−' ?></span><?= e($statusLabel) ?></button><?php endforeach; ?><button class="danger-link" name="action" value="delete" data-confirm="Delete this wanted post permanently?"><span aria-hidden="true">×</span>Delete</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <section class="details-card"><h2>Profile settings</h2><p><?= e(current_user()['name']) ?> · <?= e(current_user()['email']) ?> · <?= e(current_user()['phone'] ?? '') ?></p><p><span class="badge"><?= e(trust_label(trust_level())) ?></span></p></section>
</section>
<?php render_footer(); ?>
