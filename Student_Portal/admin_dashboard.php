<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

$totalFaqs = 0;
$faqFile = __DIR__ . '/data/faqs.json';
if (is_readable($faqFile)) {
    $faqData = json_decode((string) file_get_contents($faqFile), true);
    $totalFaqs = is_array($faqData) ? count($faqData) : 0;
}

$totalMessages = 0;
foreach (['contact_submissions.csv', 'feedback_submissions.csv'] as $csvName) {
    $csvPath = __DIR__ . '/data/' . $csvName;
    if (is_readable($csvPath)) {
        $rows = file($csvPath, FILE_SKIP_EMPTY_LINES);
        $totalMessages += max(0, count($rows) - 1);
    }
}

admin_shell_start('Overview', 'overview', 'Welcome, ' . ($_SESSION['username'] ?? 'Admin') . '. Here is what is happening on StudentHub.');
?>
<div class="cards">
    <div class="card"><h3>Total Users</h3><div class="number"><?= $totalUsers ?></div><a href="admin_users.php">Manage users</a></div>
    <div class="card"><h3>Active Users</h3><div class="number"><?= $activeUsers ?></div></div>
    <div class="card"><h3>Active Students</h3><div class="number"><?= $totalStudents ?></div><a href="student_management.php">Manage students</a></div>
    <div class="card"><h3>Total Events</h3><div class="number"><?= $totalEvents ?></div><a href="event_management.php">Manage events</a></div>
    <div class="card"><h3>Registered Entries</h3><div class="number"><?= $totalRegistrations ?></div></div>
    <div class="card"><h3>FAQs</h3><div class="number"><?= $totalFaqs ?></div><a href="admin_faqs.php">Manage FAQs</a></div>
    <div class="card"><h3>Messages</h3><div class="number"><?= $totalMessages ?></div><a href="admin_messages.php">Open inbox</a></div>
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
                    <td><span class="badge <?= $r['status'] === 'registered' ? 'badge-primary' : ($r['status'] === 'attended' ? 'badge-success' : 'badge-danger') ?>"><?= h(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_shell_end(); ?>
