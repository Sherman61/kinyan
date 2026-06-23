<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');
if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Use POST for contact events.']);
    exit;
}

verify_csrf();
$type = $_POST['target_type'] ?? '';
$id = (int)($_POST['target_id'] ?? 0);
$method = $_POST['method'] ?? '';
if (!in_array($type, ['car', 'wanted'], true) || $id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid contact target.']);
    exit;
}

$table = $type === 'car' ? 'car_listings' : 'wanted_posts';
$stmt = db()->prepare("SELECT status FROM {$table} WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if ($stmt->fetchColumn() !== 'active') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'This post is no longer available.']);
    exit;
}

require_app_rate_limit('contact_' . $type . '_' . $id, 30, 60);
save_contact_click($type, $id, $method);
echo json_encode(['ok' => true]);
