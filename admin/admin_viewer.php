<?php
session_name('QA_LOGGER_SESSION');

require_once __DIR__ . '/../auth/require_login.php';
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../repo/user_repo.php';
require_once __DIR__ . '/../viewer_repo/logs.php';
require_once __DIR__ . '/../viewer_repo/iterations.php';
require_once __DIR__ . '/../viewer_repo/programs.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$userRepo = new UserRepository(qa_db());
$userRow = $userRepo->findByUsername($_SESSION['user']['username']);

if (!$userRow) {
    session_destroy();
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$_SESSION['user']['first_login'] = (bool)$userRow['first_login'];
$db = qa_db();

/* ==========================
   INPUT STATE
========================== */
$selectedProgram   = $_GET['user'] ?? '';
$selectedSession   = $_GET['session'] ?? '';
$selectedIteration = $_GET['iteration'] ?? '';
$fromDate          = $_GET['from_date'] ?? '';
$toDate            = $_GET['to_date'] ?? '';
$fromTime          = $_GET['from_time'] ?? '';
$toTime            = $_GET['to_time'] ?? '';
$branchID          = $_GET['branch'] ?? '';
$userId            = $_GET['user_id'] ?? '';
$clientIP          = $_GET['client_ip'] ?? '';

$fromDateTime = $fromDate ? $fromDate . ' ' . ($fromTime ?: '00:00:00') : null;
$toDateTime   = $toDate   ? $toDate   . ' ' . ($toTime   ?: '23:59:59') : null;

/* ==========================
   LOAD LOGS
========================== */
$logsToShow = [];
if ($selectedProgram && $selectedSession && $selectedIteration) {
    $logsToShow = loadLogsForViewer(
        $db,
        $selectedProgram,
        $selectedSession,
        $selectedIteration,
        $branchID ?: null,
        $userId   ?: null,
        $clientIP ?: null,
        [],
        $fromDateTime,
        $toDateTime
    );
}

/* ==========================
   PROGRAM LIST
========================== */
$programs = loadPrograms($db);

/* ==========================
   Helpers
========================== */
function is_error_log(array $log): bool
{
    return in_array($log['type'] ?? '', ['backend-error', 'backend-fatal'], true);
}

function group_error_logs(array $errorLogs): array
{
    $grouped = [];

    foreach ($errorLogs as $log) {
        $decoded  = json_decode($log['response_body'] ?? '', true);
        $message  = $decoded['message'] ?? '';
        $severity = $decoded['severity'] ?? '';

        $key = md5(($log['type'] ?? '') . '|' . $message . '|' . $severity);

        if (!isset($grouped[$key])) {
            $base = $log;
            unset($base['endpoint']);
            $base['_count']     = 0;
            $base['_endpoints'] = [];
            $grouped[$key]      = $base;
        }

        if (!empty($log['endpoint'])) {
            $grouped[$key]['_endpoints'][$log['endpoint']] = true;
        }

        $grouped[$key]['_count']++;
    }

    foreach ($grouped as &$group) {
        $group['_endpoints'] = array_keys($group['_endpoints']);
    }

    return array_values($grouped);
}

function render_log_entry(array $log): string
{
    $type = $log['type'] ?? '';

    $endpoints = !empty($log['_endpoints']) && is_array($log['_endpoints'])
        ? $log['_endpoints']
        : (!empty($log['endpoint']) ? [$log['endpoint']] : []);

    $cardClass = 'bg-light border';
    if ($type === 'backend-error') {
        $cardClass = 'bg-danger-subtle border-danger';
    }

    $html  = '<div class="card mb-3 ' . $cardClass . '">';
    $html .= '<div class="card-body p-3">';

    $html .= '<h6 class="card-title mb-2">' .
             ($type === 'backend-error'
                 ? '<span class="text-danger">Backend Error</span>'
                 : htmlspecialchars($type)) .
             '</h6>';

    if (!empty($endpoints)) {
        $html .= '<p class="mb-2"><strong>Endpoints:</strong><br>';
        foreach ($endpoints as $ep) {
            [$file, $line] = array_pad(explode(':', $ep, 2), 2, '');
            $html .= '• <code>' . htmlspecialchars($file) . '</code>';
            if ($line !== '') $html .= ' : <code>' . htmlspecialchars($line) . '</code>';
            $html .= '<br>';
        }
        $html .= '</p>';
    }

    if (!empty($log['request_body'])) {
        $json   = json_decode($log['request_body'], true);
        $pretty = $json !== null
            ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $log['request_body'];
        $html .= '<p class="mb-2"><strong>Request:</strong><pre class="p-2 bg-white border rounded" style="overflow-x:auto;">'
                 . htmlspecialchars($pretty) . '</pre></p>';
    }

    if (!empty($log['response_body'])) {
        $json   = json_decode($log['response_body'], true);
        $pretty = $json !== null
            ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $log['response_body'];
        $html .= '<p class="mb-2"><strong>Response:</strong><pre class="p-2 bg-white border rounded" style="overflow-x:auto;">'
                 . htmlspecialchars($pretty) . '</pre></p>';
    }

    if (!in_array($type, ['frontend-io', 'backend-response'], true)) {
        if (!empty($log['iteration']))  $html .= '<p class="mb-1"><strong>Iteration:</strong> ' . htmlspecialchars($log['iteration']) . '</p>';
        if (!empty($log['method']))     $html .= '<p class="mb-1"><strong>Method:</strong> ' . htmlspecialchars($log['method']) . '</p>';
        if (isset($log['status_code'])) $html .= '<p class="mb-1"><strong>Status:</strong> ' . (int)$log['status_code'] . '</p>';
    }

    if ($type === 'backend-error' && !empty($log['_count']) && $log['_count'] > 1) {
        $extra = (int)$log['_count'] - 1;
        $html .= '<div class="alert alert-warning p-2 mt-2 mb-2" role="alert">
                    <strong>Occurrences:</strong> ' . (int)$log['_count'] . '<br>
                    + ' . $extra . ' more occurrence' . ($extra > 1 ? 's' : '') . '
                </div>';
    }

    if (!empty($log['created_at'])) {
        $html .= '<p class="text-muted small mb-0">Created at: '
               . date('Y-m-d H:i:s', strtotime($log['created_at'])) . '</p>';
    }

    $html .= '</div></div>';

    return $html;
}

/* ==========================
   ITERATIONS WITH ERRORS
========================== */
$iterations      = [];
$errorIterations = [];

if ($selectedProgram && $selectedSession) {
    $iterations = getAllIterations(
        $db, $selectedProgram, $selectedSession,
        $fromDateTime, $toDateTime,
        $branchID ?: null, $userId ?: null, $clientIP ?: null
    );

    $errorIterations = getErrorIterations(
        $db, $selectedProgram, $selectedSession,
        $fromDateTime, $toDateTime,
        $branchID ?: null, $userId ?: null, $clientIP ?: null
    );

    sort($iterations);
}

/* ==========================
   FILTER QUERY STRINGS
========================== */
$filterQuery = http_build_query([
    'user'      => $selectedProgram,
    'session'   => $selectedSession,
    'from_date' => $fromDate,
    'from_time' => $fromTime,
    'to_date'   => $toDate,
    'to_time'   => $toTime,
    'branch'    => $branchID,
    'user_id'   => $userId,
    'client_ip' => $clientIP,
]);

$indexFilterQuery = http_build_query([
    'user'      => $selectedProgram,
    'from_date' => $fromDate,
    'from_time' => $fromTime,
    'to_date'   => $toDate,
    'to_time'   => $toTime,
    'branch'    => $branchID,
    'user_id'   => $userId,
    'client_ip' => $clientIP,
]);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>QA Logger – Iterations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { width: 260px; min-height: 100vh; background: #fff; flex-shrink: 0; }
        .sidebar .user-box { border-bottom: 1px solid #e5e5e5; padding-bottom: 1rem; margin-bottom: 1rem; }
        .clickable-row { cursor: pointer; }

        @media print {
            .sidebar { display: none !important; }
            main { padding: 0 !important; }
            .print-header { display: block !important; }
            body { background: white; }
        }
    </style>
</head>
<body>

<div class="d-flex vh-100">

    <!-- SIDEBAR -->
    <aside class="sidebar d-flex flex-column border-end p-3" style="height: 100vh;">
        <div class="user-box mb-3">
            <div class="fw-bold text-center text-uppercase">
                Hello <?= htmlspecialchars($_SESSION['user']['username']) ?>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a href="admin.php?<?= $indexFilterQuery ?>" class="btn btn-primary btn-sm">Back to Dashboard</a>
            <button onclick="printLogs()" class="btn btn-outline-dark btn-sm">
                Print Activity Log
            </button>
        </div>

        <div class="mt-auto">
            <a href="../auth/logger_logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-fill p-4 overflow-auto" style="height:100%;">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Activity Logs for Session: <?= htmlspecialchars($selectedSession) ?></h4>
        </div>

        <!-- Iteration Dropdown -->
        <div class="mb-3 d-flex align-items-center gap-2">
            <form method="GET" class="d-flex align-items-center gap-2 m-0">
                <input type="hidden" name="user"    value="<?= htmlspecialchars($selectedProgram) ?>">
                <input type="hidden" name="session" value="<?= htmlspecialchars($selectedSession) ?>">

                <label class="form-label"><strong>Activity Log:</strong></label>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle w-100" type="button"
                            id="iterationDropdown" data-bs-toggle="dropdown"
                            aria-expanded="false" data-bs-display="static">
                        <?= $selectedIteration ? htmlspecialchars($selectedIteration) : '-- Select Activity Log --' ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-scroll w-100 text-wrap" aria-labelledby="iterationDropdown">

                        <!-- Session Summary -->
                        <li>
                            <a class="dropdown-item"
                               href="?<?= $filterQuery ?>&iteration=summary">
                                Session Summary
                            </a>
                        </li>

                        <?php foreach ($iterations as $iter):
                            $hasError = isset($errorIterations[$iter]);
                            $label    = $iter;
                            if ($hasError) $label .= ' ⚠';
                        ?>
                        <li>
                            <a class="dropdown-item text-wrap <?= $hasError ? 'text-danger fw-semibold' : '' ?>"
                               href="?<?= $filterQuery ?>&iteration=<?= urlencode($iter) ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>

                    </ul>
                </div>
            </form>
        </div>

        <!-- Logs -->
        <div id="print-area">

            <div class="print-header d-none">
                <h4 class="mb-1">QA Logger Report</h4>
                <div class="small">
                    Program: <?= htmlspecialchars($selectedProgram ?? '-') ?><br>
                    Session: <?= htmlspecialchars($selectedSession ?? '-') ?><br>
                    Activity Log: <?= htmlspecialchars($selectedIteration ?? '-') ?><br>
                    Printed by: <?= htmlspecialchars($_SESSION['user']['username']) ?><br>
                    Printed at: <?= date('Y-m-d H:i:s') ?>
                </div>
                <hr>
            </div>

            <?php if (!empty($logsToShow)): ?>

                <?php if ($selectedIteration === 'summary'):

                    $logsByIteration  = [];
                    $allowedIterations = array_flip($iterations);

                    foreach ($logsToShow as $log) {
                        $iter = $log['iteration'] ?? 0;
                        if (!is_error_log($log)) continue;
                        if (!isset($logsByIteration[$iter])) $logsByIteration[$iter] = [];
                        $logsByIteration[$iter][] = $log;
                    }

                    foreach ($logsByIteration as $iter => $logs):
                        echo '<h5 class="mt-3">Activity Log ' . htmlspecialchars($iter) . '</h5>';

                        $logsToRender = group_error_logs($logs);
                        foreach ($logsToRender as $log) {
                            echo render_log_entry($log);
                        }
                    endforeach;

                else: // SINGLE ITERATION

                    $logsToRender = group_error_logs($logsToShow);
                    foreach ($logsToRender as $log) {
                        echo render_log_entry($log);
                    }

                endif; ?>

            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="../scripts/bootstrap.bundle.min.js"></script>

<script>
function printLogs() {
    const printContents = document.getElementById('print-area').innerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
</script>

</body>
</html>