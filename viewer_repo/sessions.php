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
        return ['sessions' => [], 'baseQuery' => ''];
    }

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
        $params[':branch_id'] = '%' . $branch . '%';
    }

    if ($userId) {
        $where .= " AND user_id LIKE :userId ";
        $params[':userId'] = '%' . $userId . '%';
    }

    if ($clientIP) {
        $where .= " AND client_ip LIKE :clientIP ";
        $params[':clientIP'] = '%' . $clientIP . '%';
    }

    // Base query for preserving filters
    $baseQuery = http_build_query([
        'user'      => $program ?? '',
        'from_date' => $fromDateTime ? substr($fromDateTime, 0, 10) : '',
        'from_time' => $fromDateTime ? substr($fromDateTime, 11, 8) : '',
        'to_date'   => $toDateTime ? substr($toDateTime, 0, 10) : '',
        'to_time'   => $toDateTime ? substr($toDateTime, 11, 8) : '',
        'branch'    => $branch ?? '',
        'user_id'   => $userId ?? '',
        'client_ip' => $clientIP ?? ''
    ]);

    // Simple grouped query without pagination
    $sql = "
        SELECT
            program_name,
            session_id,
            MAX(branch_id) AS branch_id,
            MAX(user_id)   AS user_id,
            MAX(client_ip) AS client_ip,
            MIN(created_at) AS started_at,
            MAX(created_at) AS last_updated
        FROM qa_logs
        $where
        GROUP BY session_id, program_name
        ORDER BY MAX(created_at) DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'sessions' => $sessions,
        'baseQuery'=> $baseQuery
    ];
}