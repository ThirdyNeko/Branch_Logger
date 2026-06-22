<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/sessions.php';

$db = qa_db();

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);

$fromDateTime = null;
$toDateTime   = null;

if (!empty($_POST['from_date'])) {
    $fromDateTime = $_POST['from_date'] . ' ' . (!empty($_POST['from_time']) ? $_POST['from_time'] : '00:00:00');
}

if (!empty($_POST['to_date'])) {
    $toDateTime = $_POST['to_date'] . ' ' . (!empty($_POST['to_time']) ? $_POST['to_time'] : '23:59:59');
}

$result = loadSessionNamesForViewer(
    $db,
    $start,
    $length,
    $_POST['user'] ?? null,       // program filter
    $fromDateTime,
    $toDateTime,
    $_POST['branch'] ?? null,
    $_POST['user_id'] ?? null,
    $_POST['client_ip'] ?? null
);

$data = [];

foreach ($result['sessions'] as $row) {

    $errors = (int)($row['error_count'] ?? 0);

    $data[] = [
        $row['program_name'] ?? '',
        $row['session_id'] ?? '',
        $row['branch_id'] ?? '',
        $row['user_id'] ?? '',
        $row['client_ip'] ?? '',

        $errors > 0
            ? "<span class='text-danger fw-bold'>{$errors}</span>"
            : "0",

        !empty($row['last_updated'])
            ? date('Y-m-d H:i:s', strtotime($row['last_updated']))
            : '',

        '<a href="#" class="print-session text-decoration-none"><i class="bi bi-printer"></i></a>'
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $result['recordsFiltered'],
    "recordsFiltered" => $result['recordsFiltered'],
    "data" => $data
]);