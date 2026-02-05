<?php
// 🚫 Never log PHP deprecations
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../iteration_logic/qa_iteration_helper.php';
require_once __DIR__ . '/get_ip.php'; // ✅ SERVER-SIDE IP (authoritative)

/* ==========================
   Read backend payload
========================== */
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['timestamp'])) {
    http_response_code(400);
    exit;
}


$device_name  = $data['device_name'] ?? 'guest';
$user_id = $device_name;


$GLOBALS['__QA_USER_ID__']  = $user_id;
$GLOBALS['__QA_PROGRAM__'] = $data['program_name'] ?? '';

/* ==========================
   Assign iteration & session
========================== */

// Assign iteration (returns int)
$iteration = qa_assign_iteration_id($data['timestamp']);
if ($iteration === null) {
    http_response_code(204);
    exit;
}

// Fetch session state to get session_id
$state = qa_get_session_state();
$session_id = $state['session_id'];

/* ==========================
   Extract log data
========================== */
$type         = $data['type'] ?? 'backend-response';
$program_name = $data['program_name'] ?? 'UNKNOWN_APP';

$endpoint     = $data['endpoint'] ?? null;
$method       = $data['method'] ?? null;
$requestBody  = isset($data['request']) ? json_encode($data['request']) : null;
$responseBody = isset($data['response']) ? json_encode($data['response']) : null;
$statusCode   = (int)($data['status'] ?? 200);

/* ==========================
   SERVER-SIDE IP (OVERRIDE)
========================== */
// ❌ DO NOT trust payload IP
// ✅ Always use server detected IP
$client_ip = $data['client_ip'] ?? qa_get_client_ip();


/* ==========================
   Insert backend log
========================== */
$db = qa_db();

$stmt = $db->prepare("
    INSERT INTO qa_logs
    (
        user_id,
        session_id,
        iteration,
        device_name,
        program_name,
        client_ip,
        type,
        endpoint,
        method,
        request_body,
        response_body,
        status_code,
        created_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    'ssissssssssi',
    $user_id,
    $session_id,
    $iteration,
    $device_name,
    $program_name,
    $client_ip,
    $type,
    $endpoint,
    $method,
    $requestBody,
    $responseBody,
    $statusCode
);

$stmt->execute();
$stmt->close();

http_response_code(204);
