<?php
declare(strict_types=1);

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function e(string|int|float|bool|null $value): string
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

function bot_protection_fields(string $action): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['bot_form_tokens'][$token] = [
        'action' => $action,
        'started_at' => time(),
    ];

    return '<div class="bot-trap" aria-hidden="true">'
        . '<label>Website<input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>'
        . '</div>'
        . '<input type="hidden" name="form_token" value="' . e($token) . '">';
}

function human_submission_passes(string $action, int $minimumSeconds = 2, int $maximumSeconds = 86400): bool
{
    $honeypot = trim((string)($_POST['company_website'] ?? ''));
    $token = (string)($_POST['form_token'] ?? '');
    $record = $_SESSION['bot_form_tokens'][$token] ?? null;
    unset($_SESSION['bot_form_tokens'][$token]);

    $reason = '';
    if ($honeypot !== '') {
        $reason = 'honeypot_filled';
    } elseif (!$token || !is_array($record) || ($record['action'] ?? '') !== $action) {
        $reason = 'missing_or_invalid_form_token';
    } else {
        $age = time() - (int)($record['started_at'] ?? 0);
        if ($age < $minimumSeconds) {
            $reason = 'submitted_too_quickly';
        } elseif ($age > $maximumSeconds) {
            $reason = 'form_expired';
        }
    }

    if ($reason === '') {
        return true;
    }

    log_app_error('Bot-like form submission blocked.', 'A form submission was blocked by abuse protection.', [
        'action' => $action,
        'reason' => $reason,
        'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
    ], 'warning');
    return false;
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

function sanitize_error_data(mixed $value, int $depth = 0): mixed
{
    if ($depth > 5) {
        return '[maximum depth reached]';
    }
    if (is_array($value)) {
        $clean = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if (++$count > 100) {
                $clean['truncated'] = '[additional values omitted]';
                break;
            }
            $name = (string)$key;
            if (preg_match('/password|passwd|secret|token|csrf|authorization|cookie|session|tmp_name/i', $name)) {
                $clean[$name] = '[redacted]';
                continue;
            }
            $clean[$name] = sanitize_error_data($item, $depth + 1);
        }
        return $clean;
    }
    if (is_object($value)) {
        return '[object ' . $value::class . ']';
    }
    if (is_resource($value)) {
        return '[resource]';
    }
    if (is_string($value) && strlen($value) > 4000) {
        return substr($value, 0, 4000) . '[truncated]';
    }
    return $value;
}

function app_error_request_context(): array
{
    $files = [];
    foreach ($_FILES as $field => $file) {
        $files[$field] = [
            'name' => $file['name'] ?? null,
            'type' => $file['type'] ?? null,
            'size' => $file['size'] ?? null,
            'error' => $file['error'] ?? null,
        ];
    }
    return sanitize_error_data([
        'query' => $_GET,
        'form' => $_POST,
        'files' => $files,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'referer' => $_SERVER['HTTP_REFERER'] ?? null,
    ]);
}

function append_fallback_error(array $record): void
{
    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($line !== false) {
        @file_put_contents(APP_ERROR_FALLBACK_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function log_app_error(Throwable|string $error, string $userMessage = 'Something went wrong. Please try again.', array $context = [], string $severity = 'error'): ?int
{
    static $logging = false;
    if ($logging) {
        return null;
    }
    $logging = true;
    $throwable = $error instanceof Throwable ? $error : null;
    $record = [
        'severity' => substr($severity, 0, 20),
        'exception_class' => $throwable ? $throwable::class : null,
        'technical_message' => $throwable ? $throwable->getMessage() : $error,
        'user_message' => mb_substr($userMessage, 0, 500),
        'file_path' => $throwable ? $throwable->getFile() : null,
        'line_number' => $throwable ? $throwable->getLine() : null,
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? null),
        'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'context_json' => json_encode(sanitize_error_data(array_merge(app_error_request_context(), $context)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        'stack_trace' => $throwable ? $throwable->getTraceAsString() : null,
        'created_at' => date(DATE_ATOM),
    ];

    try {
        $stmt = db()->prepare('INSERT INTO app_errors (severity, exception_class, technical_message, user_message, file_path, line_number, request_method, request_uri, user_id, ip_address, context_json, stack_trace) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $record['severity'], $record['exception_class'], $record['technical_message'], $record['user_message'],
            $record['file_path'], $record['line_number'], $record['request_method'], $record['request_uri'],
            $record['user_id'], $record['ip_address'], $record['context_json'], $record['stack_trace'],
        ]);
        $id = (int)db()->lastInsertId();
        $logging = false;
        return $id;
    } catch (Throwable $loggingError) {
        $record['logging_failure'] = $loggingError->getMessage();
        append_fallback_error($record);
        error_log('Kinyan error logger failure: ' . $loggingError->getMessage() . '; original: ' . $record['technical_message']);
        $logging = false;
        return null;
    }
}

function app_open_error_count(): int
{
    try {
        return (int)db()->query("SELECT COUNT(*) FROM app_errors WHERE status = 'open'")->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function selected($left, $right): string
{
    return (string)$left === (string)$right ? 'selected' : '';
}

function checked($value): string
{
    return !empty($value) ? 'checked' : '';
}

function pagination_state(int $total, int $perPage = 24): array
{
    $perPage = max(1, min(60, $perPage));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
    return ['page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function pagination_url(int $page): string
{
    $query = $_GET;
    unset($query['partial']);
    $query['page'] = max(1, $page);
    return basename($_SERVER['SCRIPT_NAME'] ?? '') . '?' . http_build_query($query);
}

function render_pagination(array $pagination): void
{
    if (($pagination['pages'] ?? 1) <= 1) return;
    $page = (int)$pagination['page'];
    $pages = (int)$pagination['pages'];
    ?>
    <nav class="pagination" aria-label="Results pages">
        <?php if ($page > 1): ?><a class="button ghost" rel="prev" href="<?= e(pagination_url($page - 1)) ?>">Previous</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="button ghost" rel="next" href="<?= e(pagination_url($page + 1)) ?>">Next</a><?php endif; ?>
    </nav>
    <?php
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
    if (in_array($post['status'] ?? '', ['expired', 'inactive'], true)) {
        $options = ['renew' => 'Renew'] + $options;
    }
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

function site_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = rtrim(getenv('APP_URL') ?: 'https://kinyan.shop', '/');
    return $base . '/' . ltrim($path, '/');
}

function mail_service_enabled(): bool
{
    if (!filter_var(getenv('MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL)) {
        return false;
    }

    $transport = strtolower(trim((string)(getenv('MAIL_TRANSPORT') ?: 'mail')));
    if ($transport !== 'smtp') {
        return true;
    }

    return (bool)(getenv('SMTP_HOST') && getenv('SMTP_PORT') && getenv('SMTP_USER') && getenv('SMTP_PASS'));
}

function mail_sender_address(): string
{
    $from = trim((string)(getenv('MAIL_FROM') ?: setting('support_email', 'support@kinyan.live')));
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'support@kinyan.live';
}

function send_app_mail(string $to, string $subject, string $body): bool
{
    if (!mail_service_enabled() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = mail_sender_address();
    $fromName = trim((string)(getenv('MAIL_FROM_NAME') ?: 'Kinyan'));
    $safeSubject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? $subject);
    $headers = [
        'From: ' . mail_header_address($from, $fromName),
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: Kinyan',
    ];

    try {
        $transport = strtolower(trim((string)(getenv('MAIL_TRANSPORT') ?: 'mail')));
        if ($transport === 'smtp') {
            smtp_send_mail($from, $to, $safeSubject, $body, $headers);
            return true;
        }

        return @mail($to, $safeSubject, $body, implode("\r\n", $headers));
    } catch (Throwable $e) {
        log_app_error($e, 'Email delivery is temporarily unavailable.', [
            'transport' => strtolower(trim((string)(getenv('MAIL_TRANSPORT') ?: 'mail'))),
            'to_hash' => hash('sha256', strtolower($to)),
            'subject' => $safeSubject,
        ], 'warning');
        return false;
    }
}

function mail_header_address(string $email, string $name = ''): string
{
    $cleanName = trim(preg_replace('/[\r\n"]+/', '', $name) ?? '');
    if ($cleanName === '') {
        return $email;
    }
    return sprintf('"%s" <%s>', addcslashes($cleanName, '\\'), $email);
}

function smtp_send_mail(string $from, string $to, string $subject, string $body, array $headers): void
{
    $host = trim((string)getenv('SMTP_HOST'));
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $user = (string)getenv('SMTP_USER');
    $pass = (string)getenv('SMTP_PASS');
    $encryption = strtolower(trim((string)(getenv('SMTP_ENCRYPTION') ?: 'tls')));
    $timeout = 15;
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
        ],
    ]);
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) {
        throw new RuntimeException('SMTP connection failed.');
    }
    stream_set_timeout($socket, $timeout);

    try {
        smtp_expect($socket, [220]);
        $serverName = preg_replace('/[^A-Za-z0-9.-]/', '', parse_url(site_url(), PHP_URL_HOST) ?: 'kinyan.shop') ?: 'kinyan.shop';
        smtp_write($socket, 'EHLO ' . $serverName);
        smtp_expect($socket, [250]);

        if ($encryption === 'tls') {
            smtp_write($socket, 'STARTTLS');
            smtp_expect($socket, [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP TLS negotiation failed.');
            }
            smtp_write($socket, 'EHLO ' . $serverName);
            smtp_expect($socket, [250]);
        }

        smtp_write($socket, 'AUTH LOGIN');
        smtp_expect($socket, [334]);
        smtp_write($socket, base64_encode($user));
        smtp_expect($socket, [334]);
        smtp_write($socket, base64_encode($pass));
        smtp_expect($socket, [235]);

        smtp_write($socket, 'MAIL FROM:<' . $from . '>');
        smtp_expect($socket, [250]);
        smtp_write($socket, 'RCPT TO:<' . $to . '>');
        smtp_expect($socket, [250, 251]);
        smtp_write($socket, 'DATA');
        smtp_expect($socket, [354]);

        $messageHeaders = array_merge([
            'Date: ' . date(DATE_RFC2822),
            'To: <' . $to . '>',
            'Subject: ' . smtp_header_encode($subject),
        ], $headers);
        smtp_write($socket, implode("\r\n", $messageHeaders) . "\r\n\r\n" . smtp_dot_stuff($body) . "\r\n.");
        smtp_expect($socket, [250]);
        smtp_write($socket, 'QUIT');
    } finally {
        fclose($socket);
    }
}

function smtp_header_encode(string $value): string
{
    $clean = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? $value);
    if (preg_match('/^[\x20-\x7E]*$/', $clean)) {
        return $clean;
    }
    return '=?UTF-8?B?' . base64_encode($clean) . '?=';
}

function smtp_dot_stuff(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $normalized);
    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);
    return implode("\r\n", $lines);
}

function smtp_write($socket, string $line): void
{
    if (@fwrite($socket, $line . "\r\n") === false) {
        throw new RuntimeException('SMTP write failed.');
    }
}

function smtp_expect($socket, array $codes): string
{
    $response = smtp_read_response($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP server returned unexpected response ' . $code . '.');
    }
    return $response;
}

function smtp_read_response($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line)) {
            return $response;
        }
    }
    throw new RuntimeException('SMTP response was incomplete.');
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
    $userId = (int)(current_user()['id'] ?? 0);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $identities = $userId > 0
        ? ['user:' . $userId => $limit, 'ip:' . $ip => max($limit * 10, $limit + 20)]
        : ['ip:' . $ip => $limit];
    $resetAt = date('Y-m-d H:i:s', time() + $windowSeconds);

    if (random_int(1, 100) === 1) {
        db()->prepare('DELETE FROM rate_limits WHERE reset_at < NOW() LIMIT 1000')->execute();
    }

    foreach ($identities as $identity => $identityLimit) {
        $key = hash('sha256', $action . '|' . $identity);
        $stmt = db()->prepare('INSERT INTO rate_limits (rate_key, hits, reset_at) VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                hits = IF(reset_at <= NOW(), 1, hits + 1),
                reset_at = IF(reset_at <= NOW(), VALUES(reset_at), reset_at)');
        $stmt->execute([$key, $resetAt]);
        $read = db()->prepare('SELECT hits, reset_at FROM rate_limits WHERE rate_key = ? LIMIT 1');
        $read->execute([$key]);
        $row = $read->fetch();
        if ($row && (int)$row['hits'] > $identityLimit) {
            $retryAt = strtotime((string)$row['reset_at']) ?: (time() + $windowSeconds);
            $retrySeconds = max(1, $retryAt - time());
            $wait = max(1, (int)ceil($retrySeconds / 60));
            http_response_code(429);
            header('Retry-After: ' . $retrySeconds);
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Too many attempts. Please wait about ' . $wait . ' minute' . ($wait === 1 ? '' : 's') . ' and try again.']);
                exit;
            }
            flash('error', 'Too many attempts. Please wait about ' . $wait . ' minute' . ($wait === 1 ? '' : 's') . ' and try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
        }
    }
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
    $allowedExtByType = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg', 'jfif'],
        IMAGETYPE_PNG => ['png'],
        IMAGETYPE_WEBP => ['webp'],
    ];
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
            !in_array($ext, $allowedExtByType[$imageType] ?? [], true) ||
            !isset($allowedMimeByType[$imageType]) ||
            ($mime !== $allowedMimeByType[$imageType] && $mime !== 'application/octet-stream') ||
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
            log_app_error($e, 'A photo could not be attached to the listing.', ['listing_id' => $listingId], 'error');
            $failed++;
        }
    }

    return ['saved' => $saved, 'failed' => $failed, 'skipped' => $skipped];
}

function update_history_report(int $listingId, array $file, bool $remove): array
{
    $stmt = db()->prepare('SELECT history_report_file FROM car_listings WHERE id = ? LIMIT 1');
    $stmt->execute([$listingId]);
    $oldFile = (string)($stmt->fetchColumn() ?: '');
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        if (!$remove || $oldFile === '') {
            return ['old' => '', 'new' => '', 'uploaded' => false, 'removed' => false];
        }
        db()->prepare('UPDATE car_listings SET history_report_file = NULL, history_report_name = NULL, history_report_uploaded_at = NULL WHERE id = ?')->execute([$listingId]);
        return ['old' => $oldFile, 'new' => '', 'uploaded' => false, 'removed' => true];
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The history report is too large. Upload a PDF up to 10MB.'
            : 'The history report could not be uploaded. Please try again.');
    }

    $size = (int)($file['size'] ?? 0);
    $tmp = (string)($file['tmp_name'] ?? '');
    $originalName = trim(basename((string)($file['name'] ?? 'history-report.pdf')));
    if ($size < 1 || $size > 10 * 1024 * 1024) {
        throw new RuntimeException('The history report must be a PDF up to 10MB.');
    }
    if (!is_uploaded_file($tmp) || strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf') {
        throw new RuntimeException('Upload a valid PDF history report.');
    }

    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $handle = @fopen($tmp, 'rb');
    $signature = $handle ? (string)fread($handle, 5) : '';
    $pdfContent = $handle ? (string)stream_get_contents($handle) : '';
    if ($handle) fclose($handle);
    $dangerousPdfFeatures = preg_match('#/(JavaScript|JS|OpenAction|Launch|EmbeddedFile|RichMedia|XFA|AcroForm)\b#i', $pdfContent);
    if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true) || $signature !== '%PDF-' || !str_contains(substr($pdfContent, -2048), '%%EOF') || $dangerousPdfFeatures) {
        throw new RuntimeException('Upload a valid PDF history report.');
    }

    if (!is_dir(HISTORY_REPORT_DIR) && !mkdir(HISTORY_REPORT_DIR, 0750, true) && !is_dir(HISTORY_REPORT_DIR)) {
        throw new RuntimeException('History report storage is temporarily unavailable.');
    }

    $safeFile = bin2hex(random_bytes(20)) . '.pdf';
    $destination = HISTORY_REPORT_DIR . '/' . $safeFile;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('The history report could not be saved. Please try again.');
    }
    @chmod($destination, 0640);

    $displayName = preg_replace('/[\x00-\x1F\x7F]+/u', '', $originalName) ?: 'history-report.pdf';
    $displayName = mb_substr($displayName, 0, 190);
    try {
        db()->prepare('UPDATE car_listings SET history_report_file = ?, history_report_name = ?, history_report_uploaded_at = NOW() WHERE id = ?')
            ->execute([$safeFile, $displayName, $listingId]);
    } catch (Throwable $e) {
        @unlink($destination);
        throw $e;
    }

    return ['old' => $oldFile, 'new' => $safeFile, 'uploaded' => true, 'removed' => false];
}

function delete_history_report_file(string $file): void
{
    if ($file === '' || basename($file) !== $file) {
        return;
    }
    $path = HISTORY_REPORT_DIR . '/' . $file;
    if (is_file($path)) {
        @unlink($path);
    }
}

function sanitize_uploaded_image(string $source, string $dest): bool
{
    try {
        $image = new Imagick();
        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024);
        if (defined('Imagick::RESOURCETYPE_THREAD')) {
            $image->setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
        }
        if (defined('Imagick::RESOURCETYPE_TIME')) {
            $image->setResourceLimit(Imagick::RESOURCETYPE_TIME, 20);
        }
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
        log_app_error($e, 'An uploaded photo could not be processed.', [], 'error');
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
    if (!in_array($targetType, ['car', 'wanted'], true) || $targetId <= 0) {
        return;
    }
    $method = str_replace('-', '_', strtolower($method));
    if (!in_array($method, ['call', 'text', 'email', 'copy_link', 'share'], true)) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO contact_clicks (target_type, target_id, method, ip_address, created_at) VALUES (?, ?, ?, NULL, NOW())');
    $stmt->execute([$targetType, $targetId, $method]);
}

function contact_click_stats(string $targetType, array $targetIds): array
{
    $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds), fn($id) => $id > 0)));
    if (!$targetIds || !in_array($targetType, ['car', 'wanted'], true)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
    $stmt = db()->prepare("SELECT target_id,
            SUM(method = 'call') calls,
            SUM(method = 'text') texts,
            SUM(method = 'email') emails,
            SUM(method = 'copy_link') copy_links,
            SUM(method = 'share') shares,
            COUNT(*) total
        FROM contact_clicks
        WHERE target_type = ? AND target_id IN ($placeholders)
        GROUP BY target_id");
    $stmt->execute([$targetType, ...$targetIds]);

    $stats = [];
    foreach ($stmt->fetchAll() as $row) {
        $stats[(int)$row['target_id']] = [
            'calls' => (int)$row['calls'],
            'texts' => (int)$row['texts'],
            'emails' => (int)$row['emails'],
            'copy_links' => (int)$row['copy_links'],
            'shares' => (int)$row['shares'],
            'total' => (int)$row['total'],
        ];
    }
    return $stats;
}

function report_target(string $targetType, int $targetId, string $reason, string $details): bool
{
    $reason = trim($reason);
    $details = trim($details);
    if (!in_array($targetType, ['car', 'wanted'], true) || $targetId <= 0 || $reason === '' || mb_strlen($reason) > 120 || mb_strlen($details) > 4000) {
        return false;
    }
    $table = $targetType === 'car' ? 'car_listings' : 'wanted_posts';
    $exists = db()->prepare("SELECT 1 FROM {$table} WHERE id = ? AND status = 'active' LIMIT 1");
    $exists->execute([$targetId]);
    if (!$exists->fetchColumn()) {
        return false;
    }
    $stmt = db()->prepare('INSERT INTO reports (user_id, target_type, target_id, reason, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([current_user()['id'] ?? null, $targetType, $targetId, $reason, $details]);
    return true;
}
