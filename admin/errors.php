<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($id > 0 && in_array($action, ['resolve', 'reopen', 'delete'], true)) {
        if ($action === 'delete') {
            db()->prepare('DELETE FROM app_errors WHERE id = ?')->execute([$id]);
            flash('success', 'Error entry deleted.');
        } else {
            $status = $action === 'resolve' ? 'resolved' : 'open';
            db()->prepare('UPDATE app_errors SET status = ?, resolved_at = ? WHERE id = ?')
                ->execute([$status, $status === 'resolved' ? date('Y-m-d H:i:s') : null, $id]);
            flash('success', $status === 'resolved' ? 'Error marked as resolved.' : 'Error reopened.');
        }
    }
    redirect('errors.php');
}

$status = in_array($_GET['status'] ?? '', ['open', 'resolved'], true) ? (string)$_GET['status'] : '';
$query = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'e.status = ?';
    $params[] = $status;
}
if ($query !== '') {
    $where[] = '(e.technical_message LIKE ? OR e.user_message LIKE ? OR e.exception_class LIKE ? OR e.request_uri LIKE ? OR CAST(e.id AS CHAR) = ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like, $like, preg_replace('/^ERR-/i', '', $query));
}
$sql = 'SELECT e.*, u.email AS user_email FROM app_errors e LEFT JOIN users u ON u.id = e.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY e.created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$errors = $stmt->fetchAll();
$summary = db()->query("SELECT COUNT(*) total, SUM(status = 'open') open_count, SUM(created_at >= CURDATE()) today_count FROM app_errors")->fetch() ?: [];

$fallbackRows = [];
if (is_readable(APP_ERROR_FALLBACK_FILE)) {
    $lines = file(APP_ERROR_FALLBACK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice(array_reverse($lines), 0, 30) as $line) {
        $decoded = json_decode($line, true);
        $fallbackRows[] = is_array($decoded) ? $decoded : ['raw' => $line];
    }
}

render_header('Application Errors', 'Review and resolve application errors.');
?>
<section class="dashboard error-log-page">
    <div class="page-title"><h1>Application errors</h1><p>Review what users saw, the technical cause, sanitized request data, and stack traces.</p></div>
    <?php render_admin_nav('errors'); ?>
    <nav class="admin-subnav" aria-label="Error filters"><a class="<?= $status === 'open' ? 'active' : '' ?>" href="errors.php?status=open">Open</a><a class="<?= $status === 'resolved' ? 'active' : '' ?>" href="errors.php?status=resolved">Resolved</a><a class="<?= $status === '' ? 'active' : '' ?>" href="errors.php">All errors</a></nav>
    <div class="stat-grid error-stat-grid">
        <div class="stat"><span>Open</span><strong><?= (int)($summary['open_count'] ?? 0) ?></strong></div>
        <div class="stat"><span>Today</span><strong><?= (int)($summary['today_count'] ?? 0) ?></strong></div>
        <div class="stat"><span>Total</span><strong><?= (int)($summary['total'] ?? 0) ?></strong></div>
    </div>
    <form class="error-filter" method="get">
        <label><span>Search errors</span><input name="q" value="<?= e($query) ?>" placeholder="Reference, message, class, or page"></label>
        <label><span>Status</span><select name="status"><option value="">All statuses</option><option value="open" <?= selected($status, 'open') ?>>Open</option><option value="resolved" <?= selected($status, 'resolved') ?>>Resolved</option></select></label>
        <button class="button" type="submit">Search</button>
    </form>

    <div class="error-list">
    <?php foreach ($errors as $error): ?>
        <article class="error-entry <?= $error['status'] === 'resolved' ? 'resolved' : '' ?>">
            <header>
                <div><span class="badge <?= $error['status'] === 'open' ? 'gold' : 'green' ?>"><?= e($error['status']) ?></span> <span class="badge"><?= e($error['severity']) ?></span><h2>ERR-<?= (int)$error['id'] ?>: <?= e($error['user_message']) ?></h2></div>
                <time datetime="<?= e($error['created_at']) ?>"><?= e(date('M j, Y g:i:s A', strtotime($error['created_at']))) ?></time>
            </header>
            <dl class="error-meta">
                <div><dt>Cause</dt><dd><?= e($error['technical_message']) ?></dd></div>
                <div><dt>Page</dt><dd><?= e(trim(($error['request_method'] ?: '') . ' ' . ($error['request_uri'] ?: 'Unknown'))) ?></dd></div>
                <div><dt>User</dt><dd><?= e($error['user_email'] ?: ($error['user_id'] ? 'User #' . $error['user_id'] : 'Guest')) ?></dd></div>
                <div><dt>Location</dt><dd><?= e($error['file_path'] ?: 'Unknown') ?><?= $error['line_number'] ? ':' . (int)$error['line_number'] : '' ?></dd></div>
            </dl>
            <details class="error-raw"><summary>Raw technical data</summary>
                <h3>Exception</h3><pre><?= e(($error['exception_class'] ?: 'Application error') . ': ' . $error['technical_message']) ?></pre>
                <h3>Sanitized request context</h3><pre><?= e($error['context_json'] ?: 'No context recorded.') ?></pre>
                <h3>Stack trace</h3><pre><?= e($error['stack_trace'] ?: 'No stack trace recorded.') ?></pre>
                <p><strong>IP:</strong> <?= e($error['ip_address'] ?: 'Not recorded') ?></p>
            </details>
            <div class="table-actions">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$error['id'] ?>"><button name="action" value="<?= $error['status'] === 'open' ? 'resolve' : 'reopen' ?>" type="submit"><span aria-hidden="true"><?= $error['status'] === 'open' ? '✓' : '↻' ?></span><?= $error['status'] === 'open' ? 'Resolve' : 'Reopen' ?></button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$error['id'] ?>"><button class="danger-link" name="action" value="delete" type="submit" data-confirm="Permanently delete this error entry?"><span aria-hidden="true">×</span>Delete</button></form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$errors): ?><div class="empty-state"><h2>No errors found</h2><p>There are no application errors matching these filters.</p></div><?php endif; ?>
    </div>

    <?php if ($fallbackRows): ?>
    <details class="details-card fallback-errors"><summary>Fallback log (<?= count($fallbackRows) ?> recent entries)</summary><p>These were recorded when the database error table could not be reached.</p><?php foreach ($fallbackRows as $row): ?><pre><?= e(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre><?php endforeach; ?></details>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
