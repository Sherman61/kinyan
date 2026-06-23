<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$checks = [];
$failed = false;

try {
    db()->query('SELECT 1')->fetchColumn();
    $checks['database'] = ['status' => 'ok'];
} catch (Throwable) {
    $checks['database'] = ['status' => 'failed', 'message' => 'Database connection failed.'];
    $failed = true;
}

$uploadParent = dirname(UPLOAD_DIR);
$storageReady = (is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR)) || (!is_dir(UPLOAD_DIR) && is_dir($uploadParent) && is_writable($uploadParent));
$checks['image_storage'] = $storageReady
    ? ['status' => 'ok']
    : ['status' => 'failed', 'message' => 'Image storage is not writable.'];
$failed = $failed || !$storageReady;

try {
    $lastRun = setting('last_listing_expiration_run');
    $lastTimestamp = $lastRun === '' ? false : strtotime($lastRun);
    $recent = $lastTimestamp !== false && $lastTimestamp >= time() - 36 * 60 * 60;
    $checks['listing_expiration'] = $recent
        ? ['status' => 'ok', 'last_run' => $lastRun]
        : ['status' => 'warning', 'message' => 'Listing expiration has not completed in the last 36 hours.', 'last_run' => $lastRun ?: null];
    $checks['application_errors'] = ['status' => 'ok', 'open' => app_open_error_count()];
} catch (Throwable) {
    $checks['maintenance'] = ['status' => 'warning', 'message' => 'Maintenance status could not be read.'];
}

echo json_encode([
    'status' => $failed ? 'failed' : 'ok',
    'checked_at' => gmdate('c'),
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed ? 1 : 0);
