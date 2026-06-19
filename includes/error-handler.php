<?php
declare(strict_types=1);

function app_error_user_message(Throwable $error): string
{
    if ($error instanceof PDOException) {
        return 'We could not load or save that information right now. Please try again.';
    }
    return 'Something went wrong while processing your request. Please try again.';
}

function render_unhandled_error(string $message, ?int $errorId): never
{
    $reference = $errorId ? ' Error reference: ERR-' . $errorId . '.' : '';
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $isJson = str_contains($accept, 'application/json') || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($isJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => $message, 'reference' => $errorId ? 'ERR-' . $errorId : null]);
        exit;
    }

    $safeMessage = htmlspecialchars($message . $reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong | Kinyan</title><link rel="stylesheet" href="/assets/css/styles.css"></head><body><main><section class="status-page"><div class="status-card"><span>500</span><h1>Something went wrong</h1><p>' . $safeMessage . '</p><div class="status-actions"><a class="button" href="/index.php">Go home</a><a class="button ghost" href="javascript:history.back()">Go back</a></div></div></section></main></body></html>';
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (in_array($severity, [E_WARNING, E_USER_WARNING, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    log_app_error(new ErrorException($message, 0, $severity, $file, $line), 'A background application issue was detected.', [], 'warning');
    return true;
});

set_exception_handler(static function (Throwable $error): void {
    $userMessage = app_error_user_message($error);
    $errorId = log_app_error($error, $userMessage, [], 'error');
    render_unhandled_error($userMessage, $errorId);
});

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if (!$last || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $error = new ErrorException($last['message'], 0, $last['type'], $last['file'], $last['line']);
    $message = 'The page could not finish loading. Please try again.';
    $errorId = log_app_error($error, $message, [], 'critical');
    if (!headers_sent()) {
        render_unhandled_error($message, $errorId);
    }
});
