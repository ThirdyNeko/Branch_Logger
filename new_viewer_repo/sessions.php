<?php

function loadSessionNamesForViewer(
    PDO $db,
    ?string $program      = null,
    ?string $fromDateTime = null,
    ?string $toDateTime   = null,
    ?string $branch       = null,
    ?string $userId       = null,
    ?string $clientIP     = null
): array {

    if ($program === '') {
        return [];
    }

    $sql = "
        SELECT
            program_name,
            session_id,
            MAX(branch_id) AS branch_id,
            MAX(user_id)   AS user_id,
            MAX(client_ip) AS client_ip,
            MIN(created_at) AS started_at
        FROM qa_logs
        WHERE 1=1
    ";

    $params = [];

    if ($program) {
        $sql .= " AND program_name = :program ";
        $params[':program'] = $program;
    }

    if ($fromDateTime) {
        $sql .= " AND created_at >= :fromDate ";
        $params[':fromDate'] = $fromDateTime;
    }

    if ($toDateTime) {
        $sql .= " AND created_at <= :toDate ";
        $params[':toDate'] = $toDateTime;
    }

    if ($branch) {
        $sql .= " AND branch_id = :branch_id ";
        $params[':branch_id'] = $branch;
    }

    if ($userId) {
        $sql .= " AND user_id = :userId ";
        $params[':userId'] = $userId;
    }

    if ($clientIP) {
        $sql .= " AND client_ip = :clientIP ";
        $params[':clientIP'] = $clientIP;
    }

    $sql .= "
        GROUP BY session_id, program_name
        ORDER BY started_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
