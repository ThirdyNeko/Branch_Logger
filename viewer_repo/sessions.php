<?php
function loadSessionNamesForViewer(
    PDO $db,
    ?string $program      = null,
    ?string $fromDateTime = null,
    ?string $toDateTime   = null,
    ?string $branch       = null,
    ?string $userId       = null,
    ?string $clientIP     = null,
    int $limit = 50,
    int $offset = 0
): array {

    if ($program === '') {
        return ['sessions' => [], 'total' => 0, 'baseQuery' => ''];
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
        $where .= " AND branch_id = :branch_id ";
        $params[':branch_id'] = $branch;
    }

    if ($userId) {
        $where .= " AND user_id = :userId ";
        $params[':userId'] = $userId;
    }

    if ($clientIP) {
        $where .= " AND client_ip = :clientIP ";
        $params[':clientIP'] = $clientIP;
    }

    // Total sessions for pagination
    $countStmt = $db->prepare("SELECT COUNT(DISTINCT session_id) FROM qa_logs $where");
    $countStmt->execute($params);
    $totalSessions = (int)$countStmt->fetchColumn();

    // Base query for pagination links
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

    // Fetch paginated sessions using SQL Server syntax
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
                ROW_NUMBER() OVER (ORDER BY MAX(created_at) DESC) AS row_num
            FROM qa_logs
            $where
            GROUP BY session_id, program_name
        ) AS sub
        WHERE row_num BETWEEN :startRow AND :endRow
        ORDER BY last_updated DESC
    ";

    $stmt = $db->prepare($sql);

    // Bind filters
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }

    // Bind pagination rows
    $stmt->bindValue(':startRow', $offset + 1, PDO::PARAM_INT);
    $stmt->bindValue(':endRow', $offset + $limit, PDO::PARAM_INT);

    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'sessions' => $sessions,
        'total'    => $totalSessions,
        'baseQuery'=> $baseQuery
    ];
}
