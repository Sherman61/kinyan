<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $trustLevel = max(1, min(3, (int)($_POST['trust_level'] ?? 1)));
    if ($id === (int)current_user()['id']) {
        $role = 'admin';
    }
    db()->prepare('UPDATE users SET role = ?, trust_level = ? WHERE id = ?')->execute([$role, $trustLevel, $id]);
    flash('success', 'User updated.');
    redirect('users.php');
}
$users = db()->query('SELECT u.*, (SELECT COUNT(*) FROM car_listings c WHERE c.user_id=u.id) cars, (SELECT COUNT(*) FROM wanted_posts w WHERE w.user_id=u.id) wanted FROM users u ORDER BY created_at DESC LIMIT 300')->fetchAll();
render_header('Admin Users', 'Manage Kinyan users.');
?>
<section class="dashboard"><div class="page-title"><h1>Users</h1><p>Manage roles, trust levels, and account activity.</p></div><?php render_admin_nav('users'); ?><div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Trust</th><th>Cars</th><th>Wanted</th><th>Action</th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr><td><strong><?= e($u['name']) ?></strong><br><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td><td><?= e(trust_label((int)($u['trust_level'] ?? 1))) ?></td><td><?= (int)$u['cars'] ?></td><td><?= (int)$u['wanted'] ?></td><td><form method="post" class="inline-controls"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><select name="role"><option value="user" <?= selected($u['role'], 'user') ?>>user</option><option value="admin" <?= selected($u['role'], 'admin') ?>>admin</option></select><select name="trust_level"><option value="1" <?= selected($u['trust_level'] ?? 1, 1) ?>>Trust 1</option><option value="2" <?= selected($u['trust_level'] ?? 1, 2) ?>>Trust 2</option><option value="3" <?= selected($u['trust_level'] ?? 1, 3) ?>>Trust 3</option></select><button type="submit" data-confirm="Save this user's role and trust level?"><span aria-hidden="true">✓</span>Save</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php render_footer(); ?>
