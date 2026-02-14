<?php
session_name('QA_LOGGER_SESSION');

require_once __DIR__ . '/../auth/require_login.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../repo/user_repo.php';
require_once __DIR__ . '/../viewer_repo/logs.php';
require_once __DIR__ . '/../viewer_repo/iterations.php';
require_once __DIR__ . '/../viewer_repo/programs.php';

$db = qa_db();

$program   = $_GET['user'] ?? '';
$session   = $_GET['session'] ?? '';
$iteration = $_GET['iteration'] ?? 'summary';

if (!$program || !$session) {
    die('Invalid request');
}

// Load logs (reuse your function)
$logsToShow = loadLogs($db, $program, $session, $iteration);

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
        // Decode response_body safely
        $decoded = json_decode($log['response_body'] ?? '', true);

        $message  = $decoded['message'] ?? '';
        $severity = $decoded['severity'] ?? '';

        // 🔑 Logical grouping key
        $key = md5(
            ($log['type'] ?? '') . '|' . $message . '|' . $severity
        );

        if (!isset($grouped[$key])) {
            $base = $log;

            // Remove per-occurrence fields
            unset($base['endpoint']);

            $base['_count'] = 0;
            $base['_endpoints'] = [];

            $grouped[$key] = $base;
        }

        // Collect endpoints
        if (!empty($log['endpoint'])) {
            $grouped[$key]['_endpoints'][$log['endpoint']] = true;
        }

        $grouped[$key]['_count']++;
    }

    // Normalize endpoint list
    foreach ($grouped as &$group) {
        $group['_endpoints'] = array_keys($group['_endpoints']);
    }

    return array_values($grouped);
}


function render_log_entry(array $log): string
{
    $type = $log['type'] ?? '';

    // Normalize endpoints
    $endpoints = !empty($log['_endpoints']) && is_array($log['_endpoints'])
        ? $log['_endpoints']
        : (!empty($log['endpoint']) ? [$log['endpoint']] : []);

    // Determine card style
    $cardClass = 'bg-light border';
    if ($type === 'backend-error') {
        $cardClass = 'bg-danger-subtle border-danger';
    }

    $html = '<div class="card mb-3 ' . $cardClass . '">';
    $html .= '<div class="card-body p-3">';

    // Card title
    $html .= '<h6 class="card-title mb-2">' . 
             ($type === 'backend-error' 
                 ? '<span class="text-danger">Backend Error</span>' 
                 : htmlspecialchars($type)) . 
             '</h6>';

    // Endpoints
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

    // Request body
    if (!empty($log['request_body'])) {
        $json = json_decode($log['request_body'], true);
        $pretty = $json !== null
            ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $log['request_body'];
        $html .= '<p class="mb-2"><strong>Request:</strong><pre class="p-2 bg-white border rounded" style="overflow-x:auto;">' 
                 . htmlspecialchars($pretty) . '</pre></p>';
    }

    // Response body
    if (!empty($log['response_body'])) {
        $json = json_decode($log['response_body'], true);
        $pretty = $json !== null
            ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $log['response_body'];
        $html .= '<p class="mb-2"><strong>Response:</strong><pre class="p-2 bg-white border rounded" style="overflow-x:auto;">' 
                 . htmlspecialchars($pretty) . '</pre></p>';
    }

    // Iteration / Method / Status
    if (!in_array($type, ['frontend-io', 'backend-response'], true)) {
        if (!empty($log['iteration'])) $html .= '<p class="mb-1"><strong>Iteration:</strong> ' . htmlspecialchars($log['iteration']) . '</p>';
        if (!empty($log['method'])) $html .= '<p class="mb-1"><strong>Method:</strong> ' . htmlspecialchars($log['method']) . '</p>';
        if (isset($log['status_code'])) $html .= '<p class="mb-1"><strong>Status:</strong> ' . (int)$log['status_code'] . '</p>';
    }

    // Occurrences
    if ($type === 'backend-error' && !empty($log['_count']) && $log['_count'] > 1) {
        $extra = (int)$log['_count'] - 1;
        $html .= '<div class="alert alert-warning p-2 mt-2 mb-2" role="alert">
                    <strong>Occurrences:</strong> ' . (int)$log['_count'] . '<br>
                    + ' . $extra . ' more occurrence' . ($extra > 1 ? 's' : '') . '
                </div>';
    }

    // Created At
    if (!empty($log['created_at'])) {
        $html .= '<p class="text-muted small mb-0">Created at: ' . htmlspecialchars($log['created_at']) . '</p>';
    }

    $html .= '</div></div>';

    return $html;
}
// /* ==========================
//    ITERATION LIST FOR SELECTED SESSION
// ========================== */
// $iterations = [];
// if ($selectedSession && isset($filteredRemarked[$selectedSession])) {
//     // Include iterations with remarks
//     $iterations = array_keys($filteredRemarked[$selectedSession]);
// }

// /* ==========================
//    ITERATIONS WITH ERRORS
// ========================== */
// $iterations = [];

// if ($selectedProgram && $selectedSession) {
//     $iterations = getAllIterations(
//         $db,
//         $selectedProgram,
//         $selectedSession,
//         null,
//         null,
//         null,       // branch
//         null,       // userId
//         null,       // clientIP
//     );

//     $errorIterations = getErrorIterations(
//         $db,
//         $selectedProgram,
//         $selectedSession,
//         null,       // branch
//         null,       // userId
//         null,       // clientIP
//     );

//     // Ensure error iterations always appear
//     foreach ($errorIterations as $iter => $_) {
//         if (!in_array($iter, $iterations, true)) {
//             $iterations[] = $iter;
//         }
//     }

//     sort($iterations);
// }

// If you need remarks:
$filteredRemarked = []; // load your remark array here if needed
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Print Logs</title>

<link rel="stylesheet" href="../css/bootstrap.min.css">

<style>
body {
    background: white;
    padding: 20px;
}

@media print {
    body {
        padding: 0;
    }
}
</style>

<script>
window.onload = function() {
    window.print();
};
</script>

</head>
<body>

<h4 class="mb-3">
    Program: <?= htmlspecialchars($program) ?><br>
    Session: <?= htmlspecialchars($session) ?><br>
    Printed by: <?= htmlspecialchars($_SESSION['user']['username']) ?><br>
    Printed at: <?= date('Y-m-d H:i:s') ?>
</h4>

<hr>

<div id="print-area">
<?php if (!empty($logsToShow)): ?>

<?php
if ($iteration === 'summary') {

    $logsByIteration = [];

    foreach ($logsToShow as $log) {
        $iter = $log['iteration'] ?? 0;

        if (!isset($logsByIteration[$iter])) {
            $logsByIteration[$iter] = [];
        }

        $logsByIteration[$iter][] = $log;
    }
    foreach ($logsByIteration as $iter => $logs) {

        echo '<h5 class="mt-4">Activity Log ' . htmlspecialchars($iter) . '</h5>';

        $logsToRender = group_error_logs($logs);

        foreach ($logsToRender as $log) {
            echo render_log_entry($log);
        }
    }

} else {

    $logsToRender = group_error_logs($logsToShow);
    foreach ($logsToRender as $log) {
        echo render_log_entry($log);
    }
}

?>

<?php endif; ?>

</div>

</body>
</html>
