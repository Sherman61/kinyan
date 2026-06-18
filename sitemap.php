<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'kinyan.live') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach (['index.php','cars.php','wanted.php','post-car.php','post-wanted.php'] as $p) echo '<url><loc>' . e($base . $p) . "</loc></url>\n";
$cars = db()->query("SELECT id, updated_at FROM car_listings WHERE status='active'");
foreach ($cars as $car) echo '<url><loc>' . e($base . 'listing.php?id=' . $car['id']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($car['updated_at']))) . "</lastmod></url>\n";
$wanted = db()->query("SELECT id, updated_at FROM wanted_posts WHERE status='active'");
foreach ($wanted as $post) echo '<url><loc>' . e($base . 'wanted-details.php?id=' . $post['id']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($post['updated_at']))) . "</lastmod></url>\n";
echo "</urlset>";
