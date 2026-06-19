<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_NAME', 'Kinyan');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads/cars');
define('UPLOAD_URL', 'uploads/cars');
define('HISTORY_REPORT_DIR', BASE_PATH . '/storage/history-reports');
define('APP_ERROR_FALLBACK_FILE', BASE_PATH . '/storage/app-errors.log');

function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

load_env_file(BASE_PATH . '/.env');

ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? '1' : '0');
error_reporting(E_ALL);

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'kinyan';
$dbUser = getenv('DB_USER') ?: 'kinyan_user';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    $fallback = json_encode([
        'created_at' => date(DATE_ATOM),
        'severity' => 'critical',
        'exception_class' => $e::class,
        'technical_message' => $e->getMessage(),
        'user_message' => 'The site is temporarily unable to reach its database.',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
    @file_put_contents(APP_ERROR_FALLBACK_FILE, $fallback . PHP_EOL, FILE_APPEND | LOCK_EX);
    error_log('Kinyan database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service temporarily unavailable | Kinyan</title><link rel="stylesheet" href="/assets/css/styles.css"><body><main><section class="status-page"><div class="status-card"><span>503</span><h1>Service temporarily unavailable</h1><p>We cannot load the site right now. Please wait a moment and try again.</p><div class="status-actions"><a class="button" href="/index.php">Try again</a></div></div></section></main></body></html>');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/error-handler.php';
