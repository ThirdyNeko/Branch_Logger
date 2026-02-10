<?php
session_name('QA_LOGGER_SESSION');

require_once __DIR__ . '/auth/require_login.php';
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/repo/user_repo.php';
require_once __DIR__ . '/new_viewer_repo/sessions.php';
require_once __DIR__ . '/viewer_repo/users.php';
require_once __DIR__ . '/viewer_repo/programs.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userRepo = new UserRepository(qa_db()); // $pdo = PDO connection from config

/* --------------------------------------------------
   Fetch latest user state from DB
-------------------------------------------------- */
$userRow = $userRepo->findByUsername($_SESSION['user']['username']);

if (!$userRow) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['user']['first_login'] = (bool)$userRow['first_login'];

$db = qa_db();

/* ==========================
   INPUT STATE
========================== */
$selectedProgram     = $_GET['user'] ?? '';
$fromDate            = $_GET['from_date'] ?? '';
$toDate              = $_GET['to_date'] ?? '';
$fromTime            = $_GET['from_time'] ?? '';
$toTime              = $_GET['to_time'] ?? '';
$branch            = $_GET['branch'] ?? '';
$userId            = $_GET['user_id'] ?? '';
$clientIP            = $_GET['client_ip'] ?? '';

$fromDateTime        = '';
$toDateTime          = '';

if ($fromDate) {
    $fromDateTime = $fromDate . ' ' . ($fromTime ?: '00:00:00');
}

if ($toDate) {
    $toDateTime = $toDate . ' ' . ($toTime ?: '23:59:59');
}

/* ==========================
   LOAD SESSION NAMES
========================== */
$sessionNames = loadSessionNamesForViewer(
    $db,
    $selectedProgram ?: null,  // null = all programs
    $fromDate ? $fromDateTime : null,
    $toDate   ? $toDateTime   : null,
    $branch ?: null,
    $userId ?: null,
    $clientIP ?: null
);

/* ==========================
   PROGRAM LIST (FROM LOGS)
========================== */
$programs = loadPrograms($db);

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>QA Logger – Sessions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="bootstrap-icons/font/bootstrap-icons.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: #ffffff;
        }
        .sidebar .user-box {
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        tr.clickable-row {
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="d-flex vh-100">

    <!-- =====================
         SIDEBAR
    ====================== -->
    <aside class="sidebar d-flex flex-column border-end p-3" style="height: 100vh;">
        <div class="user-box mb-3">
            <div class="fw-bold text-center text-uppercase">
                Hello <?= htmlspecialchars($_SESSION['user']['username']) ?>
            </div>
        </div>

        <!-- Top buttons -->
        <div class="d-grid gap-2">
            <a href="profile.php" class="btn btn-outline-dark btn-sm">Profile</a>
        </div>

        <!-- Spacer pushes logout to the bottom -->
        <div class="mt-auto">
            <a href="auth/logger_logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
        </div>
    </aside>

    <!-- =====================
         MAIN CONTENT
    ====================== -->
    <main class="flex-fill p-4 overflow-auto" style="height:100%;">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Sessions</h4>

            <div class="d-flex gap-2">
                <!-- Filters Modal -->
                <button class="btn btn-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#filterModal">
                    Filters
                </button>

                <!-- Refresh Logs -->
                <form method="GET" class="m-0">
                    <!-- Preserve existing filter values as hidden inputs -->
                    <input type="hidden" name="user" value="<?= htmlspecialchars($selectedProgram) ?>">
                    <input type="hidden" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
                    <input type="hidden" name="from_time" value="<?= htmlspecialchars($fromTime) ?>">
                    <input type="hidden" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
                    <input type="hidden" name="to_time" value="<?= htmlspecialchars($toTime) ?>">
                    <input type="hidden" name="branch" value="<?= htmlspecialchars($branch) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                    <input type="hidden" name="client_ip" value="<?= htmlspecialchars($clientIP) ?>">

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- =====================
            SESSIONS TABLE
        ====================== -->
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Program</th>
                            <th>Session</th>
                            <th>Branch</th>
                            <th>User ID</th>
                            <th>Client IP</th>
                            <th>Last Updated</th> <!-- New column -->
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($sessionNames)): ?>
                        <?php foreach ($sessionNames as $session): ?>
                            <tr class="clickable-row"
                                onclick="window.location='iteration_viewer.php?user=<?= urlencode($session['program_name'] ?? '') ?>&session=<?= urlencode($session['session_id'] ?? '') ?>'">
                                <td><?= htmlspecialchars($session['program_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($session['session_id']) ?></td>
                                <td><?= htmlspecialchars($session['branch_id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($session['user_id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($session['client_ip'] ?? '-') ?></td>
                                <td>
                                    <?= !empty($session['last_updated'])
                                        ? date('Y-m-d H:i:s', strtotime($session['last_updated']))
                                        : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">
                                No sessions found
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- =====================
     FILTER MODAL
====================== -->
<form method="GET">
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Filter Sessions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body row g-3">

                <div class="col-md-6">
                    <label class="form-label">Program</label>
                    <input type="text" name="user" class="form-control"
                           value="<?= htmlspecialchars($selectedProgram) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control"
                           value="<?= htmlspecialchars($fromDate) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">From Time</label>
                    <input type="time" name="from_time" class="form-control"
                           value="<?= htmlspecialchars($fromTime) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control"
                           value="<?= htmlspecialchars($toDate) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Time</label>
                    <input type="time" name="to_time" class="form-control"
                           value="<?= htmlspecialchars($toTime) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <input type="text" name="branch" class="form-control"
                           value="<?= htmlspecialchars($branch) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">User ID</label>
                    <input type="text" name="user_id" class="form-control"
                           value="<?= htmlspecialchars($userId) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Client IP</label>
                    <input type="text" name="client_ip" class="form-control"
                           value="<?= htmlspecialchars($clientIP) ?>">
                </div>

            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="window.location='viewer.php'">
                    Clear Filters
                </button>
                <button type="submit" class="btn btn-primary">
                    Apply Filters
                </button>
            </div>

        </div>
    </div>
</div>
</form>

<!-- Bootstrap JS -->
<script src="scripts/bootstrap.bundle.min.js"></script>

</body>
</html>
