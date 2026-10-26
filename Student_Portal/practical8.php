<?php
session_start();

if (empty($_SESSION['p8_csrf_token'])) {
    $_SESSION['p8_csrf_token'] = bin2hex(random_bytes(32));
}

function p8e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$notice = '';
$noticeType = 'success';
$students = [];
$events = [];
$registrations = [];
$search = trim((string)($_GET['search'] ?? ''));
$connectionError = '';

try {
    require __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['p8_csrf_token'], $token)) {
            $notice = 'Invalid security token. Refresh the page and try again.';
            $noticeType = 'error';
        } else {
            $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

            if (!$studentId || !$eventId) {
                $notice = 'Please select a valid student and event.';
                $noticeType = 'error';
            } else {
                // Prepared statement prevents user input from becoming SQL code.
                $insert = $pdo->prepare(
                    'INSERT INTO registrations (student_id, event_id) VALUES (:student_id, :event_id)'
                );
                try {
                    $insert->execute([
                        ':student_id' => $studentId,
                        ':event_id' => $eventId,
                    ]);
                    $notice = 'Registration saved successfully in MySQL.';
                    $_SESSION['p8_csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $notice = 'This student may already be registered for that event, or the selected record is invalid.';
                    } else {
                        error_log('Practical 8 insert failed: ' . $exception->getMessage());
                        $notice = 'Unable to save the registration. Check the database and try again.';
                    }
                    $noticeType = 'error';
                }
            }
        }
    }

    $students = $pdo->query('SELECT student_id, student_code, full_name, email, course FROM students ORDER BY full_name')->fetchAll();
    $events = $pdo->query('SELECT event_id, title, event_date, venue FROM events ORDER BY event_date')->fetchAll();

    // Search is parameterized using a prepared statement.
    if ($search !== '') {
        $stmt = $pdo->prepare(
            'SELECT r.registration_id, s.student_code, s.full_name, s.email,
                    e.title AS event_title, e.event_date, r.registration_date, r.status
             FROM registrations r
             INNER JOIN students s ON s.student_id = r.student_id
             INNER JOIN events e ON e.event_id = r.event_id
             WHERE s.full_name LIKE :search_name OR s.student_code LIKE :search_code OR e.title LIKE :search_event
             ORDER BY r.registration_date DESC'
        );
        $term = '%' . $search . '%';
        $stmt->execute([
            ':search_name' => $term,
            ':search_code' => $term,
            ':search_event' => $term,
        ]);
        $registrations = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT r.registration_id, s.student_code, s.full_name, s.email,
                    e.title AS event_title, e.event_date, r.registration_date, r.status
             FROM registrations r
             INNER JOIN students s ON s.student_id = r.student_id
             INNER JOIN events e ON e.event_id = r.event_id
             ORDER BY r.registration_date DESC'
        );
        $stmt->execute();
        $registrations = $stmt->fetchAll();
    }
} catch (Throwable $exception) {
    $connectionError = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Unable to load StudentHub data. Check the database configuration and imported SQL schema.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Practical 8 | StudentHub Portal</title>
<style>
:root{--blue:#174ea6;--bg:#f3f6fb;--text:#1f2937;--card:#fff;--line:#dce3ed;--muted:#64748b}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 Arial,sans-serif}
header{background:var(--blue);color:white;padding:20px max(18px,calc((100% - 1100px)/2))}header h1{margin:0;font-size:1.5rem}
nav{background:#123d82;padding:10px max(18px,calc((100% - 1100px)/2));display:flex;gap:16px;flex-wrap:wrap}nav a{color:#fff;text-decoration:none}nav a:hover{text-decoration:underline}
main{max-width:1100px;margin:25px auto;padding:0 16px}.intro{margin-bottom:20px}.intro h2{margin-bottom:5px;color:var(--blue)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 3px 10px #0f172a0a}
h3{margin-top:0;color:var(--blue)}label{display:block;font-weight:bold;margin:12px 0 5px}input,select{width:100%;padding:10px;border:1px solid #b9c5d5;border-radius:7px;font:inherit;background:#fff;color:#222}
button,.button{display:inline-block;margin-top:14px;background:var(--blue);color:#fff;padding:10px 16px;border:0;border-radius:7px;text-decoration:none;cursor:pointer;font:inherit}button:hover,.button:hover{background:#103878}
.alert{padding:12px 14px;border-radius:8px;margin:14px 0}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:.93rem}th,td{padding:10px;border:1px solid var(--line);text-align:left;vertical-align:top}th{background:#e8effb;color:#173b75}tbody tr:nth-child(even){background:#f8fafc}.muted{color:var(--muted)}.status{font-weight:bold;text-transform:capitalize}footer{text-align:center;padding:20px;color:var(--muted)}.search{display:flex;gap:10px;align-items:end}.search>div{flex:1}@media(max-width:520px){.search{display:block}.search button{width:100%}}
</style>
</head>
<body>
<header><h1>StudentHub Portal</h1></header>
<nav aria-label="Main navigation">
<a href="index.html">Home</a><a href="events.html">Events</a><a href="students.html">Students</a><a href="practical7.php">Practical 7</a><a href="practical8.php" aria-current="page">Practical 8</a>
</nav>
<main>
<section class="intro"><h2>Practical 8: MySQL Schema Design, PDO Connectivity &amp; Prepared Statements</h2><p>StudentHub database demonstration with students, events, and registrations.</p></section>
<?php if ($connectionError !== ''): ?>
<div class="alert error"><strong>Database not ready:</strong> <?=p8e($connectionError)?><br>Start MySQL in XAMPP, import <code>sql/studenthub_practical8.sql</code> in phpMyAdmin, and verify the settings in <code>db.php</code>.</div>
<?php else: ?>
<?php if ($notice !== ''): ?><div class="alert <?=p8e($noticeType)?>" role="status" aria-live="polite"><?=p8e($notice)?></div><?php endif; ?>
<div class="grid">
<section class="card"><h3>Database connection</h3><p><strong>Status:</strong> Connected successfully</p><p class="muted">Connection uses PHP PDO, exceptions, UTF-8, and native prepared statements.</p><p><strong>Database:</strong> studenthub</p></section>
<section class="card"><h3>Database summary</h3><p><strong>Students:</strong> <?=count($students)?></p><p><strong>Events:</strong> <?=count($events)?></p><p><strong>Registrations:</strong> <?=count($registrations)?></p></section>
</div>
<section class="card"><h3>Create event registration</h3><p class="muted">The form uses a POST request and a PDO prepared INSERT statement.</p>
<form method="post" action="practical8.php">
<input type="hidden" name="csrf_token" value="<?=p8e($_SESSION['p8_csrf_token'])?>">
<label for="student_id">Student</label><select name="student_id" id="student_id" required><option value="">Select a student</option><?php foreach($students as $student): ?><option value="<?=p8e($student['student_id'])?>"><?=p8e($student['student_code'].' — '.$student['full_name'])?></option><?php endforeach; ?></select>
<label for="event_id">Event</label><select name="event_id" id="event_id" required><option value="">Select an event</option><?php foreach($events as $event): ?><option value="<?=p8e($event['event_id'])?>"><?=p8e($event['title'].' — '.$event['event_date'])?></option><?php endforeach; ?></select>
<button type="submit">Save Registration</button>
</form></section>
<section class="card"><h3>Students</h3><div class="table-wrap"><table><thead><tr><th>Student Code</th><th>Name</th><th>Email</th><th>Course</th></tr></thead><tbody><?php foreach($students as $student): ?><tr><td><?=p8e($student['student_code'])?></td><td><?=p8e($student['full_name'])?></td><td><?=p8e($student['email'])?></td><td><?=p8e($student['course'])?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h3>Events</h3><div class="table-wrap"><table><thead><tr><th>Event</th><th>Date</th><th>Venue</th></tr></thead><tbody><?php foreach($events as $event): ?><tr><td><?=p8e($event['title'])?></td><td><?=p8e($event['event_date'])?></td><td><?=p8e($event['venue'])?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h3>Registrations</h3><form class="search" method="get" action="practical8.php"><div><label for="search">Search student, student code, or event</label><input id="search" name="search" value="<?=p8e($search)?>" placeholder="e.g. Kush or Workshop"></div><button type="submit">Search</button><?php if($search!==''): ?><a class="button" href="practical8.php">Clear</a><?php endif; ?></form>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Student</th><th>Email</th><th>Event</th><th>Event Date</th><th>Registered At</th><th>Status</th></tr></thead><tbody>
<?php if (!$registrations): ?><tr><td colspan="7">No matching registrations found.</td></tr><?php else: foreach($registrations as $reg): ?><tr><td><?=p8e($reg['registration_id'])?></td><td><?=p8e($reg['student_code'].' — '.$reg['full_name'])?></td><td><?=p8e($reg['email'])?></td><td><?=p8e($reg['event_title'])?></td><td><?=p8e($reg['event_date'])?></td><td><?=p8e($reg['registration_date'])?></td><td class="status"><?=p8e($reg['status'])?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<section class="card"><h3>ER model (relationship summary)</h3><ul><li><strong>Students (1) → (many) Registrations:</strong> each registration belongs to one student.</li><li><strong>Events (1) → (many) Registrations:</strong> each registration belongs to one event.</li><li><strong>Students ↔ Events:</strong> many-to-many relationship resolved through the Registrations table.</li></ul><p class="muted">Primary keys: student_id, event_id, registration_id. Foreign keys: registrations.student_id and registrations.event_id. A unique constraint prevents duplicate registrations for the same student and event.</p></section>
</main><footer>CHARUSAT StudentHub Portal · Practical 8</footer>
</body></html>
