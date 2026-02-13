<?php
require '../config/db.php';

header('Content-Type: application/json');

session_name('QA_LOGGER_SESSION');
session_start();

if (
    !isset($_SESSION['user']) ||
    !isset($_SESSION['user']['role']) ||
    $_SESSION['user']['role'] !== 'admin'
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = qa_db(); // your PDO connection

$date = date('Y_m_d_His');
$newTable = "qa_logs_" . $date;

try {

    // 1️⃣ Rename current logs table
    $db->exec("EXEC sp_rename 'qa_logs', '$newTable'");

    // 2️⃣ Create new empty table with same structure
    $db->exec("SELECT TOP 0 * INTO qa_logs FROM $newTable");

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
