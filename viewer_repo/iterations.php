<?php

/**
 * Get all iterations that have backend errors/fatals for a given program and session.
 *
 * @param PDO    $db
 * @param string $program
 * @param string $session
 *
 * @return array<int, bool> iterations as keys, value always true
 */

/**
 * Get all iterations for a session, optionally filtered by date + client IP.
 *
 * @return int[]
 */
function getAllIterations(
    PDO $db,
    string $program,
    string $session,
    ?string $fromDateTime = null,
    ?string $toDateTime = null,
    ?string $branchID = null,
    ?string $userId = null,
    ?string $clientIP = null
): array {
    $params = [
        ':program' => $program,
        ':session' => $session
    ];

    $sql = "
        SELECT DISTINCT iteration
        FROM qa_logs
        WHERE program_name = :program
          AND session_id = :session
    ";

    if ($branchID) {
        $sql .= " AND branch_id = :branch_id";
        $params[':branch_id'] = $branchID;
    }

    if ($userId) {
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    if ($clientIP) {
        $sql .= " AND client_ip = :client_ip";
        $params[':client_ip'] = $clientIP;
    }

    if ($fromDateTime && $toDateTime) {
        $sql .= " AND created_at BETWEEN :from_dt AND :to_dt";
        $params[':from_dt'] = $fromDateTime;
        $params[':to_dt']   = $toDateTime;
    }

    $sql .= " ORDER BY iteration ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return array_map(
        fn($r) => (int)$r['iteration'],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

/**
 * Get iterations that contain backend errors/fatals.
 *
 * @return array<int,bool> iteration => true
 */
function getErrorIterations(
    PDO $db,
    string $program,
    string $session,
    ?string $branchID = null,
    ?string $userId = null,
    ?string $clientIP = null
): array {
    $params = [
        ':program' => $program,
        ':session' => $session
    ];

    $sql = "
        SELECT DISTINCT iteration
        FROM qa_logs
        WHERE program_name = :program
          AND session_id = :session
          AND type IN ('backend-error', 'backend-fatal')
    ";

    if ($branchID) {
        $sql .= " AND branch_id = :branch_id";
        $params[':branch_id'] = $branchID;
    }

    if ($userId) {
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    if ($clientIP) {
        $sql .= " AND client_ip = :client_ip";
        $params[':client_ip'] = $clientIP;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $errors = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $errors[(int)$row['iteration']] = true;
    }

    return $errors;
}
