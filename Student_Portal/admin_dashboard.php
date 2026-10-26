
<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logAdminAction(PDO $pdo, $userId, $action, $details) {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, details, ip_address)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $userId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$userId || $userId < 1) {
            $error = 'Invalid user ID.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT id, username, role, status
                     FROM users WHERE id = ? FOR UPDATE"
                );
                $stmt->execute([$userId]);
                $target = $stmt->fetch();

                if (!$target) {
                    throw new RuntimeException('User not found.');
                }

                if ((int)$target['id'] === (int)($_SESSION['user_id'] ?? 0)) {
                    throw new RuntimeException('You cannot change your own status.');
                }

                $newStatus = $target['status'] === 'active'
                    ? 'inactive'
                    : 'active';

                $stmt = $pdo->prepare(
                    "UPDATE users SET status = ? WHERE id = ?"
                );
                $stmt->execute([$newStatus, $userId]);

                logAdminAction(
                    $pdo,
                    $_SESSION['user_id'] ?? null,
                    'user_status_changed',
                    'Changed status of ' . $target['username'] .
                    ' (ID ' . $userId . ') to ' . $newStatus
                );

                $pdo->commit();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: admin_dashboard.php?updated=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Admin dashboard error: ' . $e->getMessage());
                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to update user status.';
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $message = 'User status updated and audit log saved.';
}

$totalUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM users"
)->fetchColumn();

$activeUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE status = 'active'"
)->fetchColumn();

$totalStudents = (int)$pdo->query(
    "SELECT COUNT(*) FROM students WHERE status = 'active'"
)->fetchColumn();

$totalEvents = (int)$pdo->query(
    "SELECT COUNT(*) FROM events"
)->fetchColumn();

$totalRegistrations = (int)$pdo->query(
    "SELECT COUNT(*) FROM registrations WHERE status = 'registered'"
)->fetchColumn();

$latestStmt = $pdo->query(
    "SELECT r.registration_id, r.registration_date, r.status,
            s.full_name, s.student_code, e.title
     FROM registrations r
     LEFT JOIN students s ON r.student_id = s.student_id
     LEFT JOIN events e ON r.event_id = e.event_id
     ORDER BY r.registration_date DESC
     LIMIT 10"
);
$latestRegistrations = $latestStmt->fetchAll();

$chartStmt = $pdo->query(
    "SELECT DATE_FORMAT(registration_date, '%Y-%m') AS month_key,
            COUNT(*) AS total
     FROM registrations
     WHERE registration_date >= DATE_FORMAT(
         DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01'
     )
     AND status <> 'cancelled'
     GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
     ORDER BY month_key"
);
$chartRows = $chartStmt->fetchAll();

$monthlyData = [];
foreach ($chartRows as $row) {
    $monthlyData[$row['month_key']] = (int)$row['total'];
}

$months = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
    $months[] = [
        'label' => date('M Y', strtotime($key . '-01')),
        'total' => $monthlyData[$key] ?? 0
    ];
}

$maxRegistrations = 1;
foreach ($months as $month) {
    $maxRegistrations = max($maxRegistrations, $month['total']);
}

$users = $pdo->query(
    "SELECT id, username, email, role, status, created_at
     FROM users ORDER BY created_at DESC LIMIT 20"
)->fetchAll();

$auditLogs = $pdo->query(
    "SELECT a.action, a.details, a.ip_address, a.created_at, u.username
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC LIMIT 10"
)->fetchAll();

$currentRole = $_SESSION['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | StudentHub</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #243047;
        }
        header {
            background: #123a83;
            color: white;
            padding: 20px 5%;
        }
        header h1 { margin: 0 0 8px; }
        nav { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 16px; }
        nav a { color: white; text-decoration: none; }
        main { max-width: 1250px; margin: 25px auto; padding: 0 18px; }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        .card, .panel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0000000d;
            margin-bottom: 20px;
        }
        .card h3 { font-size: 15px; color: #5c6b82; margin-top: 0; }
        .number { font-size: 30px; font-weight: bold; color: #174ea6; }
        h2 { margin-top: 0; font-size: 21px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e7ebf1;
            font-size: 14px;
        }
        th { background: #f6f8fc; }
        button, .btn {
            border: 0;
            padding: 9px 12px;
            border-radius: 5px;
            cursor: pointer;
            background: #174ea6;
            color: white;
        }
        .inactive { background: #a33b3b; }
        .success, .error {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
        }
        .success { background: #e2f7e8; color: #176333; }
        .error { background: #fde8e8; color: #8a2020; }
        .bar-row {
            display: grid;
            grid-template-columns: 75px 1fr 35px;
            align-items: center;
            gap: 10px;
            margin: 12px 0;
        }
        .bar-bg { background: #edf1f7; height: 18px; border-radius: 4px; overflow: hidden; }
        .bar { height: 100%; background: #2875d7; }
        .muted { color: #68758a; font-size: 13px; }
        footer { text-align: center; padding: 20px; color: #68758a; }
    </style>
</head>
<body>
<header>
    <h1>StudentHub Admin Dashboard</h1>
    <p>Welcome, <?= h($_SESSION['username'] ?? 'Admin') ?>!</p>
    <nav>
        <a href="index.php">Home</a>
        <a href="dashboard.php">My Dashboard</a>
        <a href="student_management.php">Manage Students</a>
        <a href="event_management.php">Manage Events</a>
        <a href="student_ajax.php">Student AJAX CRUD</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <?php if ($message): ?>
        <div class="success"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <h2>Dashboard Overview</h2>
    <div class="cards">
        <div class="card"><h3>Total Users</h3><div class="number"><?= $totalUsers ?></div></div>
        <div class="card"><h3>Active Users</h3><div class="number"><?= $activeUsers ?></div></div>
        <div class="card"><h3>Active Students</h3><div class="number"><?= $totalStudents ?></div></div>
        <div class="card"><h3>Total Events</h3><div class="number"><?= $totalEvents ?></div></div>
        <div class="card"><h3>Registered Entries</h3><div class="number"><?= $totalRegistrations ?></div></div>
    </div>

    <section class="panel">
        <h2>Event Registrations by Month</h2>
        <p class="muted">Last 12 months; cancelled registrations are excluded.</p>
        <?php foreach ($months as $month): ?>
            <div class="bar-row">
                <span><?= h($month['label']) ?></span>
                <div class="bar-bg">
                    <div class="bar" style="width: <?= ($month['total'] / $maxRegistrations) * 100 ?>%"></div>
                </div>
                <strong><?= $month['total'] ?></strong>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <h2>Latest Event Registrations</h2>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>ID</th><th>Student</th><th>Event</th><th>Date</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php if (!$latestRegistrations): ?>
                    <tr><td colspan="5">No registrations found.</td></tr>
                <?php else: foreach ($latestRegistrations as $r): ?>
                    <tr>
                        <td><?= (int)$r['registration_id'] ?></td>
                        <td><?= h($r['full_name'] ?? 'Student unavailable') ?>
                            <br><span class="muted"><?= h($r['student_code'] ?? '') ?></span></td>
                        <td><?= h($r['title'] ?? 'Event unavailable') ?></td>
                        <td><?= h($r['registration_date']) ?></td>
                        <td><?= h(ucfirst($r['status'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>User Management</h2>
        <p class="muted">Showing the 20 most recently registered users.</p>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= h($user['username']) ?></td>
                        <td><?= h($user['email']) ?></td>
                        <td><?= h($user['role']) ?></td>
                        <td><?= h($user['status']) ?></td>
                        <td><?= h($user['created_at']) ?></td>
                        <td>
                        <?php if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
                            <span class="muted">Current account</span>
                        <?php else: ?>
                            <form method="post" onsubmit="return confirm('Change this user status?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button type="submit" class="<?= $user['status'] === 'active' ? 'inactive' : '' ?>">
                                    <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Recent Audit Logs</h2>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Admin</th><th>Action</th><th>Details</th><th>IP Address</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php if (!$auditLogs): ?>
                    <tr><td colspan="5">No audit logs recorded yet. Changing a user's status will create the first log.</td></tr>
                <?php else: foreach ($auditLogs as $log): ?>
                    <tr>
                        <td><?= h($log['username'] ?? 'User unavailable') ?></td>
                        <td><?= h($log['action']) ?></td>
                        <td><?= h($log['details'] ?? '') ?></td>
                        <td><?= h($log['ip_address'] ?? '') ?></td>
                        <td><?= h($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<footer>StudentHub Portal — Practical 14</footer>
</body>
</html>
