<?php

function getLatestSessionByProgram(PDO $db, string $program): ?string
{
    $sql = "
        SELECT TOP 1 session_id
        FROM qa_logs
        WHERE program_name = :program_name
        ORDER BY created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':program_name' => $program]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? $row['session_id'] : null;
}

function isActiveSession(
    ?string $selectedProgram,
    ?string $selectedSession,
    ?string $latestSession
): bool {
    return
        !empty($selectedProgram) &&
        !empty($selectedSession) &&
        $latestSession !== null &&
        $selectedSession === $latestSession;
}

function saveSessionName(
    PDO $db,
    string $program,
    string $sessionId,
    string $sessionName
): void {
    $sql = "
        IF EXISTS (
            SELECT 1
            FROM qa_session_names
            WHERE program_name = ?
              AND session_id = ?
        )
        BEGIN
            UPDATE qa_session_names
            SET session_name = ?
            WHERE program_name = ?
              AND session_id = ?
        END
        ELSE
        BEGIN
            INSERT INTO qa_session_names (program_name, session_id, session_name)
            VALUES (?, ?, ?)
        END
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $program,
        $sessionId,
        $sessionName,
        $program,
        $sessionId,
        $program,
        $sessionId,
        $sessionName
    ]);
}

/**
 * Load session names for a program.
 *
 * @return array<string, string> session_id => session_name
 */
function loadSessionNames(PDO $db, string $program): array
{
    $sql = "
        SELECT session_id, session_name
        FROM qa_session_names
        WHERE program_name = :program_name
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':program_name' => $program]);

    $sessionNames = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sessionNames[$row['session_id']] = $row['session_name'];
    }

    return $sessionNames;
}

/**
 * Get distinct session IDs for a program.
 * Optional date range + client IP filtering.
 *
 * @return string[]
 */
function getSessionsByProgram(
    PDO $db,
    string $program,
    ?string $fromDateTime = null,
    ?string $toDateTime = null,
    ?string $clientIp = null,
    ?string $userId = null
): array {
    $params = [
        ':program' => $program
    ];

    $sql = "
        SELECT DISTINCT session_id
        FROM qa_logs
        WHERE program_name = :program
    ";

    if ($fromDateTime && $toDateTime) {
        $sql .= " AND created_at BETWEEN :from_dt AND :to_dt";
        $params[':from_dt'] = $fromDateTime;
        $params[':to_dt']   = $toDateTime;
    }

    if ($clientIp) {
        $sql .= " AND branch_id = :branch_id";
        $params[':branch_id'] = $clientIp;
    }

    if ($userId) {
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    $sql .= " ORDER BY session_id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return array_column(
        $stmt->fetchAll(PDO::FETCH_ASSOC),
        'session_id'
    );
}