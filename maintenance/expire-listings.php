<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$days = max(14, min(180, (int)setting('listing_expiration_days', '45')));
$cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
$cars = db()->prepare("UPDATE car_listings SET status = 'expired' WHERE status = 'active' AND updated_at < ?");
$cars->execute([$cutoff]);
$wanted = db()->prepare("UPDATE wanted_posts SET status = 'expired' WHERE status = 'active' AND updated_at < ?");
$wanted->execute([$cutoff]);
set_setting('last_listing_expiration_run', gmdate('c'));

echo 'Expired ' . $cars->rowCount() . ' car listing(s) and ' . $wanted->rowCount() . " wanted post(s).\n";
