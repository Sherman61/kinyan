<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

if (is_post()) {
    verify_csrf();
    require_app_rate_limit('dashboard_action', 120, 60 * 60);
    $type = $_POST['type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $table = $type === 'wanted' ? 'wanted_posts' : 'car_listings';
    if (!owns_listing($table, $id)) render_status_page(403, 'Access denied', 'You can only manage posts that belong to your account.', ['Go to dashboard' => 'dashboard.php']);
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) render_status_page(404, 'Post not found', 'That post could not be found or may have been removed.', ['Go to dashboard' => 'dashboard.php']);
    if ($action === 'renew' && in_array($post['status'], ['expired', 'inactive'], true)) {
        require_app_rate_limit('renew_' . $type, 20, 60 * 60);
        $nextStatus = new_listing_status();
        $stmt = db()->prepare("UPDATE {$table} SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$nextStatus, $id]);
        flash('success', $nextStatus === 'active' ? 'Post renewed and active.' : 'Post renewed and submitted for approval.');
    } elseif ($action === 'delete') {
        $stmt = db()->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        if ($table === 'car_listings') {
            delete_history_report_file((string)($post['history_report_file'] ?? ''));
        }
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
$carRows = $cars->fetchAll();
$carStats = contact_click_stats('car', array_column($carRows, 'id'));
$wanted = db()->prepare('SELECT * FROM wanted_posts WHERE user_id = ? ORDER BY created_at DESC');
$wanted->execute([current_user()['id']]);
$wantedRows = $wanted->fetchAll();
render_header('Dashboard', 'Manage your Kinyan listings and wanted posts.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Dashboard</h1><p>Manage your listings, buyer requests, and profile.</p></div>
    <div class="dash-actions"><a class="button" href="post-car.php">Post a car</a><a class="button secondary" href="post-wanted.php">Post wanted</a><a class="button ghost" href="library.php">Image library</a><a class="button ghost" href="saved.php">Saved listings</a></div>
    <?php if (!$carRows && !$wantedRows): ?><section class="first-use-panel" aria-labelledby="getting-started-title"><h2 id="getting-started-title">Get started on Kinyan</h2><p>Your dashboard is ready. Choose the action that matches what you need:</p><div class="first-use-actions"><a href="post-car.php"><strong>Sell a car</strong><span>Create a detailed listing with up to 10 photos.</span></a><a href="post-wanted.php"><strong>Find a car</strong><span>Post what you need so sellers can contact you.</span></a><a href="cars.php"><strong>Browse first</strong><span>Save cars and compare your options.</span></a></div></section><?php endif; ?>
    <?php if (array_filter([...$carRows, ...$wantedRows], fn(array $row): bool => ($row['status'] ?? '') === 'pending')): ?><div class="flash info dashboard-notice" role="status"><strong>Pending review</strong><span>One or more posts are waiting for moderation. They remain visible to you here and will appear publicly after approval.</span></div><?php endif; ?>
    <section class="details-card">
        <h2>My car listings</h2>
        <div class="table-wrap"><table class="dashboard-table"><thead><tr><th>Listing</th><th>Status</th><th>Views</th><th>Contact clicks</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($carRows as $car): $stats = $carStats[(int)$car['id']] ?? ['calls'=>0,'texts'=>0,'emails'=>0,'copy_links'=>0,'shares'=>0,'total'=>0]; ?><tr><td data-label="Listing"><strong><?= e($car['title']) ?></strong><br><span><?= e($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) ?></span></td><td data-label="Status"><span class="badge"><?= e($car['status']) ?></span></td><td data-label="Views"><?= (int)$car['views'] ?></td><td data-label="Contact clicks"><div class="stats-mini"><strong><?= (int)$stats['total'] ?> total</strong><span>Call <?= (int)$stats['calls'] ?> · Text <?= (int)$stats['texts'] ?> · Email <?= (int)$stats['emails'] ?></span><span>Copy <?= (int)$stats['copy_links'] ?> · Share <?= (int)$stats['shares'] ?></span></div></td><td class="table-actions" data-label="Actions"><a class="icon-action" data-confirm="Open editor for this listing?" href="post-car.php?id=<?= (int)$car['id'] ?>"><span aria-hidden="true">✎</span>Edit</a><a class="icon-action" href="listing.php?id=<?= (int)$car['id'] ?>"><span aria-hidden="true">↗</span>View</a><form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="car"><input type="hidden" name="id" value="<?= (int)$car['id'] ?>"><?php foreach (status_options_for_user($car, 'car_listings') as $statusValue => $statusLabel): ?><button name="action" value="<?= e($statusValue) ?>" data-confirm="Set this listing to <?= e(strtolower($statusLabel)) ?>?"><span aria-hidden="true"><?= $statusValue === 'active' ? '✓' : ($statusValue === 'sold' ? '$' : '−') ?></span><?= e($statusLabel) ?></button><?php endforeach; ?><button class="danger-link" name="action" value="delete" data-confirm="Delete this car listing permanently?"><span aria-hidden="true">×</span>Delete</button></form></td></tr><?php endforeach; ?>
        <?php if (!$carRows): ?><tr class="empty-row"><td colspan="5"><div class="empty-state"><h3>No car listings yet</h3><p>Create your first listing to start receiving direct inquiries.</p><a class="button" href="post-car.php">Post a car</a></div></td></tr><?php endif; ?></tbody></table></div>
    </section>
    <section class="details-card">
        <h2>My wanted posts</h2>
        <div class="table-wrap"><table class="dashboard-table"><thead><tr><th>Post</th><th>Status</th><th>Views</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($wantedRows as $post): ?><tr><td data-label="Post"><strong><?= e($post['title']) ?></strong></td><td data-label="Status"><span class="badge"><?= e($post['status']) ?></span></td><td data-label="Views"><?= (int)$post['views'] ?></td><td class="table-actions" data-label="Actions"><a class="icon-action" data-confirm="Open editor for this wanted post?" href="post-wanted.php?id=<?= (int)$post['id'] ?>"><span aria-hidden="true">✎</span>Edit</a><a class="icon-action" href="wanted-details.php?id=<?= (int)$post['id'] ?>"><span aria-hidden="true">↗</span>View</a><form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="wanted"><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php foreach (status_options_for_user($post, 'wanted_posts') as $statusValue => $statusLabel): ?><button name="action" value="<?= e($statusValue) ?>" data-confirm="Set this wanted post to <?= e(strtolower($statusLabel)) ?>?"><span aria-hidden="true"><?= $statusValue === 'active' ? '✓' : '−' ?></span><?= e($statusLabel) ?></button><?php endforeach; ?><button class="danger-link" name="action" value="delete" data-confirm="Delete this wanted post permanently?"><span aria-hidden="true">×</span>Delete</button></form></td></tr><?php endforeach; ?>
        <?php if (!$wantedRows): ?><tr class="empty-row"><td colspan="4"><div class="empty-state"><h3>No wanted posts yet</h3><p>Post the vehicle you need so matching sellers can find you.</p><a class="button" href="post-wanted.php">Post wanted</a></div></td></tr><?php endif; ?></tbody></table></div>
    </section>
    <section class="details-card"><h2>Profile settings</h2><p><?= e(current_user()['name']) ?> · <?= e(current_user()['email']) ?> · <?= e(current_user()['phone'] ?? '') ?></p><p><span class="badge"><?= e(trust_label(trust_level())) ?></span></p></section>
</section>
<?php render_footer(); ?>
