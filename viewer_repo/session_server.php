<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/sessions.php';

$db = qa_db();

$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 25);

// Build from/to datetime
$fromDateTime = !empty($_POST['from_date']) ? $_POST['from_date'] . ' ' . ($_POST['from_time'] ?? '00:00:00') : null;
$toDateTime   = !empty($_POST['to_date'])   ? $_POST['to_date']   . ' ' . ($_POST['to_time'] ?? '23:59:59') : null;

$result = loadSessionNamesForViewer(
    $db,
    $start,
    $length,
    $_POST['user'] ?? null,
    $fromDateTime,
    $toDateTime,
    $_POST['branch'] ?? null,
    $_POST['user_id'] ?? null,
    $_POST['client_ip'] ?? null
);

$data = [];
foreach ($result['sessions'] as $row) {
    $data[] = [
        $row['program_name'],
        $row['session_id'],
        $row['branch_id'],
        $row['user_id'],
        $row['client_ip'],
        date('Y-m-d H:i:s', strtotime($row['last_updated'])), // 👈 format to seconds
        '<a href="#" class="print-session text-decoration-none"><i class="bi bi-printer"></i></a>'
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $result['recordsFiltered'], // ideally total sessions without filters
    "recordsFiltered" => $result['recordsFiltered'],
    "data" => $data
]);