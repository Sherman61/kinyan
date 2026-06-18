<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cards.php';
require_once __DIR__ . '/includes/options.php';

$where = ["status = 'active'"];
$params = [];
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = '(title LIKE ? OR preferred_make LIKE ? OR preferred_model LIKE ? OR location LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
foreach (['preferred_make','preferred_model','preferred_body_type','preferred_transmission','preferred_fuel_type'] as $field) {
    if (!empty($_GET[$field])) {
        $where[] = "$field = ?";
        $params[] = $_GET[$field];
    }
}
foreach ([['min_year','max_year >= ?'],['max_year','min_year <= ?'],['min_budget','max_budget >= ?'],['max_budget','min_budget <= ?'],['max_mileage','max_mileage <= ?']] as [$key,$sql]) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $where[] = $sql;
        $params[] = (int)$_GET[$key];
    }
}
$sorts = ['newest'=>'created_at DESC','oldest'=>'created_at ASC','budget_high'=>'max_budget DESC','budget_low'=>'max_budget ASC'];
$sort = $sorts[$_GET['sort'] ?? 'newest'] ?? $sorts['newest'];
$stmt = db()->prepare('SELECT * FROM wanted_posts WHERE ' . implode(' AND ', $where) . " ORDER BY $sort LIMIT 80");
$stmt->execute($params);
$posts = $stmt->fetchAll();

render_header('Wanted Cars', 'Browse buyer requests for wanted cars on Kinyan.');
?>
<section class="page-title"><h1>Wanted cars</h1><p>Find buyers looking for specific cars and contact them directly.</p></section>
<section class="browse-layout">
    <aside class="filters">
        <form method="get" class="filter-form">
            <input name="q" value="<?= e($q) ?>" placeholder="Search requests">
            <input name="preferred_make" value="<?= e($_GET['preferred_make'] ?? '') ?>" placeholder="Make">
            <input name="preferred_model" value="<?= e($_GET['preferred_model'] ?? '') ?>" placeholder="Model">
            <div class="two"><input name="min_year" value="<?= e($_GET['min_year'] ?? '') ?>" placeholder="Min year"><input name="max_year" value="<?= e($_GET['max_year'] ?? '') ?>" placeholder="Max year"></div>
            <div class="two"><input name="min_budget" value="<?= e($_GET['min_budget'] ?? '') ?>" placeholder="Min budget"><input name="max_budget" value="<?= e($_GET['max_budget'] ?? '') ?>" placeholder="Max budget"></div>
            <input name="max_mileage" value="<?= e($_GET['max_mileage'] ?? '') ?>" placeholder="Max mileage">
            <select name="preferred_body_type"><option value="">Body type</option><?php foreach ($bodyTypes as $v): ?><option <?= selected($_GET['preferred_body_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="preferred_transmission"><option value="">Transmission</option><?php foreach ($transmissions as $v): ?><option <?= selected($_GET['preferred_transmission'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <select name="preferred_fuel_type"><option value="">Fuel type</option><?php foreach ($fuelTypes as $v): ?><option <?= selected($_GET['preferred_fuel_type'] ?? '', $v) ?>><?= e($v) ?></option><?php endforeach; ?></select>
            <button class="button" type="submit">Apply filters</button>
            <a class="button ghost" href="wanted.php">Clear</a>
        </form>
    </aside>
    <div class="browse-main">
        <div class="browse-toolbar"><span><?= count($posts) ?> wanted posts</span><form method="get"><select name="sort" onchange="this.form.submit()"><option value="newest">Newest</option><option value="oldest" <?= selected($_GET['sort'] ?? '', 'oldest') ?>>Oldest</option><option value="budget_high" <?= selected($_GET['sort'] ?? '', 'budget_high') ?>>Highest budget</option><option value="budget_low" <?= selected($_GET['sort'] ?? '', 'budget_low') ?>>Lowest budget</option></select></form></div>
        <?php if ($posts): ?><div class="stack"><?php foreach ($posts as $post) render_wanted_card($post); ?></div>
        <?php else: ?><div class="empty-state"><h3>No wanted posts match</h3><p>Try a broader search or post what you are looking for.</p></div><?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
