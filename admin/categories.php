<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'add' && trim($_POST['name'] ?? '') !== '') db()->prepare('INSERT IGNORE INTO vehicle_makes (name) VALUES (?)')->execute([trim($_POST['name'])]);
    if (($_POST['action'] ?? '') === 'toggle') db()->prepare('UPDATE vehicle_makes SET active = 1 - active WHERE id = ?')->execute([(int)$_POST['id']]);
    redirect('categories.php');
}
$makes = db()->query('SELECT * FROM vehicle_makes ORDER BY name ASC')->fetchAll();
render_header('Admin Categories', 'Manage Kinyan vehicle makes.');
?>
<section class="dashboard"><div class="page-title"><h1>Categories and makes</h1><p>Manage popular vehicle makes used by the marketplace.</p></div><?php render_admin_nav('categories'); ?><form method="post" class="inline-form"><?= csrf_field() ?><input name="name" placeholder="Add make"><button class="button" name="action" value="add">Add</button></form><div class="chips admin-chips"><?php foreach ($makes as $make): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$make['id'] ?>"><button name="action" value="toggle" class="<?= $make['active'] ? '' : 'muted-chip' ?>"><?= e($make['name']) ?></button></form><?php endforeach; ?></div></section>
<?php render_footer(); ?>
