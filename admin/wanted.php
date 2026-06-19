<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/options.php';
require_admin();
if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') db()->prepare('DELETE FROM wanted_posts WHERE id = ?')->execute([$id]);
    elseif ($action === 'set_status' && in_array($_POST['status'] ?? '', $statuses, true)) db()->prepare('UPDATE wanted_posts SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
    flash('success', 'Wanted post updated.');
    redirect('wanted.php');
}
$status = in_array($_GET['status'] ?? '', $statuses, true) ? (string)$_GET['status'] : '';
$sql = 'SELECT w.*, u.email user_email FROM wanted_posts w JOIN users u ON u.id = w.user_id';
$params = [];
if ($status !== '') { $sql .= ' WHERE w.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY w.created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();
render_header('Admin Wanted Posts', 'Manage Kinyan wanted posts.');
?>
<section class="dashboard"><div class="page-title"><h1>Wanted posts</h1><p>Approve, reject, edit, or delete buyer requests.</p></div><?php render_admin_nav('wanted'); ?><nav class="admin-subnav" aria-label="Wanted post filters"><a class="<?= $status === '' ? 'active' : '' ?>" href="wanted.php">All</a><a class="<?= $status === 'pending' ? 'active' : '' ?>" href="wanted.php?status=pending">Pending</a><a class="<?= $status === 'active' ? 'active' : '' ?>" href="wanted.php?status=active">Active</a><a class="<?= $status === 'inactive' ? 'active' : '' ?>" href="wanted.php?status=inactive">Inactive</a></nav><div class="table-wrap"><table><thead><tr><th>Post</th><th>Status</th><th>User</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($posts as $post): ?><tr><td><strong><?= e($post['title']) ?></strong></td><td><span class="badge"><?= e($post['status']) ?></span></td><td><?= e($post['user_email']) ?></td><td class="table-actions"><a class="icon-action" data-confirm="Open editor for this wanted post?" href="../post-wanted.php?id=<?= (int)$post['id'] ?>"><span aria-hidden="true">✎</span>Edit</a><a class="icon-action" href="../wanted-details.php?id=<?= (int)$post['id'] ?>"><span aria-hidden="true">↗</span>View</a><form method="post" class="inline-controls"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><select name="status"><?php foreach ($statuses as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= selected($post['status'], $statusOption) ?>><?= e(ucfirst($statusOption)) ?></option><?php endforeach; ?></select><button name="action" value="set_status" data-confirm="Update this wanted post status?"><span aria-hidden="true">✓</span>Status</button><button class="danger-link" name="action" value="delete" data-confirm="Delete this wanted post permanently?"><span aria-hidden="true">×</span>Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php render_footer(); ?>
