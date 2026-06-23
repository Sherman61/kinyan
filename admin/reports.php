<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    require_app_rate_limit('admin_report_update', 120, 60 * 60);
    $id = (int)($_POST['id'] ?? 0);
    $status = validate_choice($_POST['status'] ?? '', ['open','investigating','resolved','dismissed'], 'open');
    $notes = trim($_POST['admin_notes'] ?? '');
    if (mb_strlen($notes) > 4000) {
        flash('error', 'Admin notes must be 4,000 characters or fewer.');
    } else {
        $closed = in_array($status, ['resolved', 'dismissed'], true);
        $stmt = db()->prepare('UPDATE reports SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = ? WHERE id = ?');
        $stmt->execute([$status, $notes, $closed ? current_user()['id'] : null, $closed ? date('Y-m-d H:i:s') : null, $id]);
        flash('success', 'Report review updated.');
    }
    redirect('reports.php');
}

$statusFilter = validate_choice($_GET['status'] ?? '', ['open','investigating','resolved','dismissed'], '');
$where = $statusFilter ? 'WHERE r.status = ?' : '';
$stmt = db()->prepare("SELECT r.*, u.email reporter_email,
        c.title car_title, c.status car_status,
        w.title wanted_title, w.status wanted_status
    FROM reports r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN car_listings c ON r.target_type = 'car' AND c.id = r.target_id
    LEFT JOIN wanted_posts w ON r.target_type = 'wanted' AND w.id = r.target_id
    {$where}
    ORDER BY r.created_at DESC
    LIMIT 300");
$stmt->execute($statusFilter ? [$statusFilter] : []);
$reports = $stmt->fetchAll();

render_header('Admin Reports', 'Review reported cars and wanted posts.');
?>
<section class="dashboard">
    <div class="page-title"><h1>Reports</h1><p>Review reports submitted by users and open the reported listing or wanted post.</p></div>
    <?php render_admin_nav('reports'); ?>
    <nav class="admin-subnav" aria-label="Report status filters"><a href="reports.php" class="<?= $statusFilter === '' ? 'active' : '' ?>">All</a><?php foreach (['open','investigating','resolved','dismissed'] as $value): ?><a href="reports.php?status=<?= e($value) ?>" class="<?= $statusFilter === $value ? 'active' : '' ?>"><?= e(ucfirst($value)) ?></a><?php endforeach; ?></nav>
    <div class="table-wrap"><table><thead><tr><th>Reported item</th><th>Reason</th><th>Details</th><th>Reporter</th><th>Date</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($reports as $report):
        $isCar = $report['target_type'] === 'car';
        $title = $isCar ? ($report['car_title'] ?: 'Deleted car listing') : ($report['wanted_title'] ?: 'Deleted wanted post');
        $status = $isCar ? ($report['car_status'] ?: 'deleted') : ($report['wanted_status'] ?: 'deleted');
        $href = $isCar ? '../listing.php?id=' . (int)$report['target_id'] : '../wanted-details.php?id=' . (int)$report['target_id'];
    ?>
        <tr>
            <td><strong><?= e($title) ?></strong><br><span class="badge"><?= e($report['target_type']) ?></span> <span class="badge"><?= e($status) ?></span></td>
            <td><?= e($report['reason']) ?><br><span class="badge"><?= e(ucfirst($report['status'])) ?></span></td>
            <td><?= nl2br(e($report['details'] ?: 'No extra details provided.')) ?></td>
            <td><?= e($report['reporter_email'] ?: 'Guest') ?></td>
            <td><?= e(date('M j, Y g:i A', strtotime($report['created_at']))) ?></td>
            <td class="table-actions"><a class="icon-action" href="<?= e($href) ?>"><span aria-hidden="true">↗</span>Open item</a><form method="post" class="report-review-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$report['id'] ?>"><select name="status" aria-label="Report status"><?php foreach (['open','investigating','resolved','dismissed'] as $value): ?><option value="<?= e($value) ?>" <?= selected($report['status'], $value) ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select><textarea name="admin_notes" maxlength="4000" aria-label="Private admin notes" placeholder="Private review notes"><?= e($report['admin_notes'] ?? '') ?></textarea><button type="submit"><span aria-hidden="true">✓</span>Save review</button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$reports): ?><tr><td colspan="6"><div class="empty-state"><h3>No reports</h3><p>No cars or wanted posts have been reported.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php render_footer(); ?>
