<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');
if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Use POST to update saved listings.']);
    exit;
}
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Log in to sync saved listings.']);
    exit;
}

verify_csrf();
require_app_rate_limit('save_listing', 120, 60 * 60);
$id = (int)($_POST['car_id'] ?? 0);
$save = ($_POST['save'] ?? '') === '1';
$stmt = db()->prepare("SELECT id FROM car_listings WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'This car is no longer available.']);
    exit;
}

if ($save) {
    db()->prepare('INSERT IGNORE INTO saved_listings (user_id, car_listing_id) VALUES (?, ?)')->execute([current_user()['id'], $id]);
} else {
    db()->prepare('DELETE FROM saved_listings WHERE user_id = ? AND car_listing_id = ?')->execute([current_user()['id'], $id]);
}
echo json_encode(['ok' => true, 'saved' => $save]);
