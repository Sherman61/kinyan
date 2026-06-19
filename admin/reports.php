<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM reports WHERE id = ?')->execute([$id]);
        flash('success', 'Report cleared.');
    }
    redirect('reports.php');
}

$reports = db()->query("SELECT r.*, u.email reporter_email,
        c.title car_title, c.status car_status,
        w.title wanted_title, w.status wanted_status
    FROM reports r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN car_listings c ON r.target_type = 'car' AND c.id = r.target_id
    LEFT JOIN wanted_posts w ON r.target_type = 'wanted' AND w.id = r.target_id
    ORDER BY r.created_at DESC
    LIMIT 300")->fetchAll();

render_header('Admin Reports', 'Review reported cars and wanted posts.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Reports</h1><p>Review reports submitted by users and open the reported listing or wanted post.</p></div>
    <nav class="admin-tabs"><a href="index.php">Admin</a><a href="listings.php">Listings</a><a href="wanted.php">Wanted</a><a href="reports.php">Reports</a><a href="users.php">Users</a></nav>
    <div class="table-wrap"><table><thead><tr><th>Reported item</th><th>Reason</th><th>Details</th><th>Reporter</th><th>Date</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($reports as $report):
        $isCar = $report['target_type'] === 'car';
        $title = $isCar ? ($report['car_title'] ?: 'Deleted car listing') : ($report['wanted_title'] ?: 'Deleted wanted post');
        $status = $isCar ? ($report['car_status'] ?: 'deleted') : ($report['wanted_status'] ?: 'deleted');
        $href = $isCar ? '../listing.php?id=' . (int)$report['target_id'] : '../wanted-details.php?id=' . (int)$report['target_id'];
    ?>
        <tr>
            <td><strong><?= e($title) ?></strong><br><span class="badge"><?= e($report['target_type']) ?></span> <span class="badge"><?= e($status) ?></span></td>
            <td><?= e($report['reason']) ?></td>
            <td><?= nl2br(e($report['details'] ?: 'No extra details provided.')) ?></td>
            <td><?= e($report['reporter_email'] ?: 'Guest') ?></td>
            <td><?= e(date('M j, Y g:i A', strtotime($report['created_at']))) ?></td>
            <td class="table-actions"><a class="icon-action" href="<?= e($href) ?>"><span aria-hidden="true">↗</span>Open</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$report['id'] ?>"><button name="action" value="delete" type="submit" data-confirm="Clear this report after review?"><span aria-hidden="true">✓</span>Clear</button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$reports): ?><tr><td colspan="6"><div class="empty-state"><h3>No reports</h3><p>No cars or wanted posts have been reported.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php render_footer(); ?>
