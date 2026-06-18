<?php
declare(strict_types=1);

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($value): string
{
    if ($value === null || $value === '') {
        return 'Contact';
    }
    return '$' . number_format((float)$value, 0);
}

function lease_months_left(?string $endDate): ?int
{
    if (!$endDate) {
        return null;
    }

    try {
        $today = new DateTimeImmutable('today');
        $end = new DateTimeImmutable($endDate);
    } catch (Exception $e) {
        return null;
    }

    if ($end <= $today) {
        return 0;
    }

    $diff = $today->diff($end);
    $months = ($diff->y * 12) + $diff->m;
    return $diff->d > 0 ? $months + 1 : $months;
}

function number_short($value): string
{
    if ($value === null || $value === '') {
        return 'N/A';
    }
    return number_format((float)$value, 0);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        render_status_page(419, 'Session expired', 'Your security token expired or was invalid. Please go back and try again.', ['Go back' => $_SERVER['HTTP_REFERER'] ?? 'index.php', 'Go home' => 'index.php']);
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function selected($left, $right): string
{
    return (string)$left === (string)$right ? 'selected' : '';
}

function checked($value): string
{
    return !empty($value) ? 'checked' : '';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?: '';
    return trim($text, '-') ?: 'listing';
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function trust_level(?array $user = null): int
{
    $user = $user ?? current_user();
    $level = (int)($user['trust_level'] ?? 1);
    return max(1, min(3, $level));
}

function trust_label(int $level): string
{
    return match ($level) {
        3 => 'Level 3 - auto-approve new posts and edits',
        2 => 'Level 2 - auto-approve edits to own cars',
        default => 'Level 1 - admin approval required',
    };
}

function new_listing_status(): string
{
    return setting('auto_approve_listings', '0') === '1' || trust_level() >= 3 ? 'active' : 'pending';
}

function edited_listing_status(string $currentStatus, string $table = 'car_listings'): string
{
    if (is_admin()) {
        return $currentStatus;
    }
    if ($table === 'car_listings' && trust_level() >= 2) {
        return $currentStatus;
    }
    if (trust_level() >= 3) {
        return $currentStatus;
    }
    return $currentStatus === 'active' ? 'pending' : $currentStatus;
}

function user_can_activate_post(array $post, string $table): bool
{
    if (is_admin()) {
        return true;
    }
    if ((int)$post['user_id'] !== (int)(current_user()['id'] ?? 0)) {
        return false;
    }
    if (($post['status'] ?? '') === 'inactive') {
        return true;
    }
    if ($table === 'car_listings' && trust_level() >= 2) {
        return true;
    }
    return trust_level() >= 3;
}

function status_options_for_user(array $post, string $table): array
{
    $options = ['inactive' => 'Inactive'];
    if ($table === 'car_listings') {
        $options['sold'] = 'Sold';
    }
    if (user_can_activate_post($post, $table)) {
        $options = ['active' => 'Active'] + $options;
    }
    return $options;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function page_url(string $path, array $params = []): string
{
    $query = $params ? '?' . http_build_query($params) : '';
    return $path . $query;
}

function car_primary_image(int $listingId): ?string
{
    $stmt = db()->prepare('SELECT image_path FROM car_images WHERE car_listing_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
    $stmt->execute([$listingId]);
    $path = $stmt->fetchColumn();
    return $path ? (string)$path : null;
}

function car_images(int $listingId): array
{
    $stmt = db()->prepare('SELECT * FROM car_images WHERE car_listing_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$listingId]);
    return $stmt->fetchAll();
}

function car_image_library_for_user(int $userId, int $excludeListingId = 0): array
{
    $sql = 'SELECT MIN(i.id) id, i.image_path, COALESCE(MAX(NULLIF(i.image_title, "")), "") image_title, MAX(i.created_at) created_at
        FROM car_images i
        JOIN car_listings c ON c.id = i.car_listing_id
        WHERE c.user_id = ?';
    $params = [$userId];
    if ($excludeListingId > 0) {
        $sql .= ' AND i.car_listing_id <> ?';
        $params[] = $excludeListingId;
    }
    $sql .= ' GROUP BY i.image_path ORDER BY created_at DESC LIMIT 80';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function clean_phone_href(?string $phone): string
{
    return preg_replace('/[^0-9+]/', '', (string)$phone) ?: '';
}

function require_rate_limit(string $key, int $seconds = 60): void
{
    $now = time();
    $last = $_SESSION['rate'][$key] ?? 0;
    if ($last && ($now - (int)$last) < $seconds) {
        render_status_page(429, 'Slow down', 'Please wait a moment before trying again.', ['Go back' => $_SERVER['HTTP_REFERER'] ?? 'index.php']);
    }
    $_SESSION['rate'][$key] = $now;
}

function require_app_rate_limit(string $action, int $limit, int $windowSeconds): void
{
    $identity = current_user()['id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash('sha256', $action . '|' . $identity);
    $resetAt = date('Y-m-d H:i:s', time() + $windowSeconds);

    db()->prepare('DELETE FROM rate_limits WHERE reset_at < NOW()')->execute();
    $stmt = db()->prepare('SELECT hits, reset_at FROM rate_limits WHERE rate_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) {
        db()->prepare('INSERT INTO rate_limits (rate_key, hits, reset_at) VALUES (?, 1, ?)')->execute([$key, $resetAt]);
        return;
    }

    if ((int)$row['hits'] >= $limit) {
        $retryAt = strtotime((string)$row['reset_at']) ?: (time() + $windowSeconds);
        $wait = max(1, (int)ceil(($retryAt - time()) / 60));
        http_response_code(429);
        flash('error', 'Too many attempts. Please wait about ' . $wait . ' minute' . ($wait === 1 ? '' : 's') . ' and try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }

    db()->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?')->execute([$key]);
}

function validate_choice(string $value, array $allowed, string $fallback = ''): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function upload_car_images(int $listingId, array $files): array
{
    if (empty($files['name'][0])) {
        return ['saved' => 0, 'failed' => 0, 'skipped' => 0];
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!class_exists('Imagick')) {
        throw new RuntimeException('Image processing is temporarily unavailable. Please try again later.');
    }

    $validUploads = [];
    $allowedExt = ['jpg', 'jpeg', 'jfif', 'png', 'webp'];
    $allowedMimeByType = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $failed = 0;

    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $failed++;
            continue;
        }
        if (($files['size'][$i] ?? 0) > 15 * 1024 * 1024) {
            $failed++;
            continue;
        }
        $tmp = $files['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) {
            $failed++;
            continue;
        }
        $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
        $mime = (string)$finfo->file($tmp);
        $imageInfo = @getimagesize($tmp);
        $imageType = @exif_imagetype($tmp);
        if (
            !in_array($ext, $allowedExt, true) ||
            !isset($allowedMimeByType[$imageType]) ||
            (!str_starts_with($mime, 'image/') && $mime !== 'application/octet-stream') ||
            !$imageInfo ||
            ($imageInfo[2] ?? null) !== $imageType
        ) {
            $failed++;
            continue;
        }
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 50000000) {
            $failed++;
            continue;
        }
        $validUploads[] = ['tmp' => $tmp];
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM car_images WHERE car_listing_id = ?');
    $countStmt->execute([$listingId]);
    $sort = (int)$countStmt->fetchColumn();
    $availableSlots = max(0, 10 - $sort);
    $skipped = max(0, count($validUploads) - $availableSlots);
    if ($availableSlots <= 0) {
        return ['saved' => 0, 'failed' => $failed, 'skipped' => count($validUploads)];
    }
    $validUploads = array_slice($validUploads, 0, $availableSlots);
    $saved = 0;

    foreach ($validUploads as $upload) {
        $safe = bin2hex(random_bytes(16)) . '.webp';
        $dest = UPLOAD_DIR . '/' . $safe;
        if (!sanitize_uploaded_image($upload['tmp'], $dest)) {
            $failed++;
            continue;
        }
        try {
            $stmt = db()->prepare('INSERT INTO car_images (car_listing_id, image_path, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$listingId, UPLOAD_URL . '/' . $safe, $sort++]);
            $saved++;
        } catch (PDOException $e) {
            @unlink($dest);
            error_log($e->getMessage());
            $failed++;
        }
    }

    return ['saved' => $saved, 'failed' => $failed, 'skipped' => $skipped];
}

function sanitize_uploaded_image(string $source, string $dest): bool
{
    try {
        $image = new Imagick();
        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
        $image->readImage($source);
        $image->setIteratorIndex(0);
        $frame = $image->getImage();
        $image->clear();
        $image->destroy();

        if (method_exists($frame, 'autoOrientImage')) {
            $frame->autoOrientImage();
        }
        $frame->setImageBackgroundColor('white');
        if ($frame->getImageAlphaChannel()) {
            $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $flattened = $frame->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $frame->clear();
            $frame->destroy();
            $frame = $flattened;
        }
        $width = $frame->getImageWidth();
        $height = $frame->getImageHeight();
        $maxDimension = 1800;
        if ($width > $maxDimension || $height > $maxDimension) {
            $frame->thumbnailImage($maxDimension, $maxDimension, true, true);
        }
        $frame->stripImage();
        $frame->setImageFormat('webp');
        $frame->setImageCompressionQuality(85);
        $ok = $frame->writeImage($dest);
        $frame->clear();
        $frame->destroy();
        return $ok;
    } catch (ImagickException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function active_status_sql(string $alias = ''): string
{
    $prefix = $alias ? "{$alias}." : '';
    return "{$prefix}status = 'active'";
}

function increment_view(string $table, int $id): void
{
    if (!in_array($table, ['car_listings', 'wanted_posts'], true)) {
        return;
    }
    $stmt = db()->prepare("UPDATE {$table} SET views = views + 1 WHERE id = ?");
    $stmt->execute([$id]);
}

function save_contact_click(string $targetType, int $targetId, string $method): void
{
    $stmt = db()->prepare('INSERT INTO contact_clicks (target_type, target_id, method, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$targetType, $targetId, $method, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function report_target(string $targetType, int $targetId, string $reason, string $details): void
{
    $stmt = db()->prepare('INSERT INTO reports (user_id, target_type, target_id, reason, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([current_user()['id'] ?? null, $targetType, $targetId, $reason, $details]);
}
