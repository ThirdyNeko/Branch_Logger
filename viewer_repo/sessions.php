<?php
function loadSessionNamesForViewer(
    PDO $db,
    int $start = 0,
    int $length = 25,
    ?string $program = null,
    ?string $fromDateTime = null,
    ?string $toDateTime = null,
    ?string $branch = null,
    ?string $userId = null,
    ?string $clientIP = null
): array {

    $params = [];
    $where = " WHERE 1=1 ";

    if ($program) {
        $where .= " AND program_name = :program ";
        $params[':program'] = $program;
    }
    if ($fromDateTime) {
        $where .= " AND created_at >= :fromDate ";
        $params[':fromDate'] = $fromDateTime;
    }
    if ($toDateTime) {
        $where .= " AND created_at <= :toDate ";
        $params[':toDate'] = $toDateTime;
    }
    if ($branch) {
        $where .= " AND branch_id LIKE :branch_id ";
        $params[':branch_id'] = "%$branch%";
    }
    if ($userId) {
        $where .= " AND user_id LIKE :userId ";
        $params[':userId'] = "%$userId%";
    }
    if ($clientIP) {
        $where .= " AND client_ip LIKE :clientIP ";
        $params[':clientIP'] = "%$clientIP%";
    }

    // -----------------------------
    // 1️⃣ Total filtered count
    // -----------------------------
    $countSql = "
        SELECT COUNT(*) AS totalCount
        FROM (
            SELECT session_id, program_name
            FROM qa_logs
            $where
            GROUP BY session_id, program_name
        ) AS grouped_sessions
    ";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $totalFiltered = (int)$stmt->fetchColumn();

    // -----------------------------
    // 2️⃣ Main query with pagination (SQL Server compatible)
    // -----------------------------
    $sql = "
        SELECT *
        FROM (
            SELECT
                program_name,
                session_id,
                MAX(branch_id) AS branch_id,
                MAX(user_id)   AS user_id,
                MAX(client_ip) AS client_ip,
                MIN(created_at) AS started_at,
                MAX(created_at) AS last_updated,
                ROW_NUMBER() OVER (ORDER BY MAX(created_at) DESC) AS rn
            FROM qa_logs
            $where
            GROUP BY session_id, program_name
        ) AS numbered
        WHERE rn BETWEEN :start_plus_one AND :end
        ORDER BY rn
    ";

    $stmt = $db->prepare($sql);

    // Bind filter params
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    // SQL Server pagination
    $stmt->bindValue(':start_plus_one', $start + 1, PDO::PARAM_INT);
    $stmt->bindValue(':end', $start + $length, PDO::PARAM_INT);

    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'sessions' => $sessions,
        'recordsFiltered' => $totalFiltered
    ];
}