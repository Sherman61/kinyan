<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/options.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_image') {
        $img = db()->prepare('SELECT image_path FROM car_images WHERE id = ?');
        $img->execute([(int)$_POST['image_id']]);
        $path = $img->fetchColumn();
        db()->prepare('DELETE FROM car_images WHERE id = ?')->execute([(int)$_POST['image_id']]);
        if ($path && is_file(BASE_PATH . '/' . $path)) @unlink(BASE_PATH . '/' . $path);
    } elseif ($action === 'reorder_images') {
        foreach (($_POST['sort'] ?? []) as $imageId => $sort) db()->prepare('UPDATE car_images SET sort_order = ? WHERE id = ?')->execute([(int)$sort, (int)$imageId]);
    } elseif ($action === 'delete') {
        $reportStmt = db()->prepare('SELECT history_report_file FROM car_listings WHERE id = ? LIMIT 1');
        $reportStmt->execute([$id]);
        $reportFile = (string)($reportStmt->fetchColumn() ?: '');
        db()->prepare('DELETE FROM car_listings WHERE id = ?')->execute([$id]);
        delete_history_report_file($reportFile);
    } elseif ($action === 'set_status' && in_array($_POST['status'] ?? '', $statuses, true)) {
        db()->prepare('UPDATE car_listings SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
    } elseif ($action === 'feature') {
        db()->prepare('UPDATE car_listings SET featured = 1 - featured WHERE id = ?')->execute([$id]);
    }
    flash('success', 'Listing updated.');
    redirect('listings.php');
}
$status = $_GET['status'] ?? '';
$sql = 'SELECT c.*, u.email user_email FROM car_listings c JOIN users u ON u.id = c.user_id';
$params = [];
if ($status) { $sql .= ' WHERE c.status = ?'; $params[] = $status; }
$sort = ($_GET['sort'] ?? '') === 'views' ? 'views' : '';
$sql .= $sort === 'views' ? ' ORDER BY c.views DESC, c.created_at DESC LIMIT 200' : ' ORDER BY c.created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();
$carStats = contact_click_stats('car', array_column($cars, 'id'));
render_header('Admin Listings', 'Manage Kinyan car listings.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Car listings</h1><p>Approve, reject, feature, edit, delete, or mark sold.</p></div>
    <?php render_admin_nav('listings'); ?>
    <nav class="admin-subnav" aria-label="Car listing filters"><a class="<?= $status === '' && $sort === '' ? 'active' : '' ?>" href="listings.php">All</a><a class="<?= $status === 'pending' ? 'active' : '' ?>" href="listings.php?status=pending">Pending</a><a class="<?= $status === 'active' ? 'active' : '' ?>" href="listings.php?status=active">Active</a><a class="<?= $status === 'sold' ? 'active' : '' ?>" href="listings.php?status=sold">Sold</a><a class="<?= $sort === 'views' ? 'active' : '' ?>" href="listings.php?sort=views">Most viewed</a></nav>
    <div class="table-wrap"><table><thead><tr><th>Listing</th><th>Status</th><th>Featured</th><th>User</th><th>Views</th><th>Contact clicks</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($cars as $car): $stats = $carStats[(int)$car['id']] ?? ['calls'=>0,'texts'=>0,'emails'=>0,'copy_links'=>0,'shares'=>0,'total'=>0]; ?><tr>
        <td><strong><?= e($car['title']) ?></strong><br><?= e($car['year'] . ' ' . $car['make'] . ' ' . $car['model']) ?></td>
        <td><span class="badge"><?= e($car['status']) ?></span></td><td><?= $car['featured'] ? 'Yes' : 'No' ?></td><td><?= e($car['user_email']) ?></td>
        <td><?= (int)$car['views'] ?></td><td><div class="stats-mini"><strong><?= (int)$stats['total'] ?> total</strong><span>Call <?= (int)$stats['calls'] ?> · Text <?= (int)$stats['texts'] ?> · Email <?= (int)$stats['emails'] ?></span><span>Copy <?= (int)$stats['copy_links'] ?> · Share <?= (int)$stats['shares'] ?></span></div></td>
        <td class="table-actions"><a class="icon-action" data-confirm="Open editor for this listing?" href="../post-car.php?id=<?= (int)$car['id'] ?>"><span aria-hidden="true">✎</span>Edit</a><a class="icon-action" href="../listing.php?id=<?= (int)$car['id'] ?>"><span aria-hidden="true">↗</span>View</a><form method="post" class="inline-controls"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$car['id'] ?>"><select name="status"><?php foreach ($statuses as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= selected($car['status'], $statusOption) ?>><?= e(ucfirst($statusOption)) ?></option><?php endforeach; ?></select><button name="action" value="set_status" data-confirm="Update this listing status?"><span aria-hidden="true">✓</span>Status</button><button name="action" value="feature" data-confirm="Toggle featured status?"><span aria-hidden="true">★</span>Feature</button><button class="danger-link" name="action" value="delete" data-confirm="Delete this car listing permanently?"><span aria-hidden="true">×</span>Delete</button></form></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php render_footer(); ?>
