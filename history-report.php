<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, user_id, status, history_report_file, history_report_name FROM car_listings WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$car = $stmt->fetch();

$canView = $car && ($car['status'] === 'active' || (int)$car['user_id'] === (int)(current_user()['id'] ?? 0) || is_admin());
$storedFile = $canView ? (string)($car['history_report_file'] ?? '') : '';
$path = $storedFile !== '' && basename($storedFile) === $storedFile ? HISTORY_REPORT_DIR . '/' . $storedFile : '';
if (!$canView || $path === '' || !is_file($path)) {
    http_response_code(404);
    exit('History report not found.');
}

$downloadName = basename((string)($car['history_report_name'] ?: 'vehicle-history-report.pdf'));
$fallbackName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $downloadName) ?: 'vehicle-history-report.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Cache-Control: private, no-store');
readfile($path);
