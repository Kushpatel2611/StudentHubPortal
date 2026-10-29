<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

requireLogin();

// Admins have their own dashboard; send them there instead of a 403.
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$csrfToken = sh_csrf_token();
$notice = '';
$noticeType = 'success';

$stmt = $pdo->prepare('SELECT id, username, email, created_at, last_login FROM users WHERE id = ? LIMIT 1');
$stmt->execute([(int) $_SESSION['user_id']]);
$account = $stmt->fetch() ?: [
    'id' => (int) $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'created_at' => null,
    'last_login' => null,
];

// If a student record uses the same email, link it; otherwise auto-create one
$lookup = $pdo->prepare(
    "SELECT student_id, student_code, full_name, course
     FROM students WHERE email = ? AND status = 'active' LIMIT 1"
);
$lookup->execute([$account['email']]);
$studentRecord = $lookup->fetch() ?: null;

if (!$studentRecord) {
    $defaultCode = '25CE' . str_pad((string) $account['id'], 3, '0', STR_PAD_LEFT);
    $defaultName = ucwords(str_replace(['.', '_'], ' ', $account['username']));
    $defaultCourse = 'B.Tech Computer Engineering';

    try {
        $insertStudent = $pdo->prepare('INSERT INTO students (student_code, full_name, email, course, status) VALUES (?, ?, ?, ?, "active")');
        $insertStudent->execute([$defaultCode, $defaultName, $account['email'], $defaultCourse]);

        $lookup->execute([$account['email']]);
        $studentRecord = $lookup->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('StudentHub auto-link student error: ' . $e->getMessage());
    }
}

// Handle Event Registration & Cancellation POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $notice = 'Invalid security token. Please refresh the page and try again.';
        $noticeType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // Register for an event
        if ($action === 'register_event') {
            $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

            if (!$eventId || !$studentRecord) {
                $notice = 'Please select a valid event.';
                $noticeType = 'error';
            } else {
                try {
                    $insertReg = $pdo->prepare(
                        "INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'registered')"
                    );
                    $insertReg->execute([(int) $studentRecord['student_id'], $eventId]);

                    sh_audit($pdo, 'event_registered', "Student {$account['username']} registered for event ID {$eventId}");
                    $notice = 'You have registered for the event successfully.';
                    $noticeType = 'success';
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        // If already registered, update status to registered if previously cancelled
                        $updateReg = $pdo->prepare(
                            "UPDATE registrations SET status = 'registered' WHERE student_id = ? AND event_id = ?"
                        );
                        $updateReg->execute([(int) $studentRecord['student_id'], $eventId]);

                        if ($updateReg->rowCount() > 0) {
                            $notice = 'Your registration for this event has been reactivated.';
                            $noticeType = 'success';
                        } else {
                            $notice = 'You are already registered for this event.';
                            $noticeType = 'error';
                        }
                    } else {
                        error_log('Registration error: ' . $e->getMessage());
                        $notice = 'Unable to register for the event. Please try again.';
                        $noticeType = 'error';
                    }
                }
            }
        }

        // Cancel an existing registration
        elseif ($action === 'cancel_registration') {
            $regId = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT);

            if (!$regId || !$studentRecord) {
                $notice = 'Invalid registration request.';
                $noticeType = 'error';
            } else {
                $cancelStmt = $pdo->prepare(
                    "UPDATE registrations SET status = 'cancelled' WHERE registration_id = ? AND student_id = ?"
                );
                $cancelStmt->execute([$regId, (int) $studentRecord['student_id']]);

                if ($cancelStmt->rowCount() > 0) {
                    sh_audit($pdo, 'event_registration_cancelled', "Student {$account['username']} cancelled registration ID {$regId}");
                    $notice = 'Registration cancelled successfully.';
                    $noticeType = 'success';
                } else {
                    $notice = 'Registration record not found or could not be cancelled.';
                    $noticeType = 'error';
                }
            }
        }
    }
}

// Fetch user's registrations
$registrations = [];
if ($studentRecord) {
    $reg = $pdo->prepare(
        "SELECT r.registration_id, e.event_id, e.title, e.event_date, e.venue, r.status
         FROM registrations r
         JOIN events e ON e.event_id = r.event_id
         WHERE r.student_id = ?
         ORDER BY e.event_date ASC"
    );
    $reg->execute([(int) $studentRecord['student_id']]);
    $registrations = $reg->fetchAll();
}

// Fetch open upcoming events for the registration dropdown
$openEvents = [];
try {
    $openEvents = $pdo->query(
        "SELECT event_id, title, event_date, venue 
         FROM events 
         WHERE status = 'open' 
         ORDER BY event_date ASC"
    )->fetchAll();
} catch (PDOException $e) {
    $openEvents = [];
}

sh_public_header('Dashboard', 'Student Dashboard', 'dashboard.php');
?>
    <div class="container page-container">
        <div class="page-intro dashboard-intro">
            <div>
                <p class="eyebrow">STUDENT PORTAL</p>
                <h2>Welcome back, <?= sh_e(!empty($studentRecord['full_name']) ? $studentRecord['full_name'] : $account['username']) ?></h2>
                <p>Manage your campus events, academic profile, and activities.</p>
            </div>
            <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <a class="btn btn-primary" href="profile.php">My Profile</a>
                <a class="btn btn-secondary" href="logout.php">Logout</a>
            </div>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="alert <?= $noticeType === 'success' ? 'alert-success' : 'alert-error' ?>" role="alert">
                <?= sh_e($notice) ?>
            </div>
        <?php endif; ?>

        <section class="dashboard-summary">
            <div class="summary-item">
                <span class="summary-label">Student Code</span>
                <strong><?= sh_e(!empty($studentRecord['student_code']) ? $studentRecord['student_code'] : '25CE' . $account['id']) ?></strong>
                <small><?= sh_e(!empty($studentRecord['course']) ? $studentRecord['course'] : 'B.Tech CE') ?></small>
            </div>
            <div class="summary-item">
                <span class="summary-label">Email</span>
                <strong><?= sh_e($account['email']) ?></strong>
                <small>Member since <?= sh_e($account['created_at'] ? date('d M Y', strtotime($account['created_at'])) : '-') ?></small>
            </div>
            <div class="summary-item">
                <span class="summary-label">Last Login</span>
                <strong><?= sh_e($account['last_login'] ? date('d M Y, H:i', strtotime($account['last_login'])) : 'First visit') ?></strong>
                <small>15-minute inactivity session timeout</small>
            </div>
        </section>

        <div class="grid dashboard-content">
            <!-- Left Column: Registrations & RSVP Form -->
            <div style="display:flex;flex-direction:column;gap:1.5rem;">
                <section class="card">
                    <div class="section-heading">
                        <h2>My Event Registrations</h2>
                    </div>
                    <?php if (!$registrations): ?>
                        <p class="small-note">You have not registered for any campus events yet. Select an event below to register!</p>
                    <?php else: ?>
                        <ul class="subject-list">
                            <?php foreach ($registrations as $r): ?>
                                <li style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                                    <div>
                                        <strong><?= sh_e($r['title']) ?></strong><br>
                                        <small class="small-note"><?= sh_e(date('d M Y', strtotime($r['event_date']))) ?> &middot; <?= sh_e($r['venue']) ?></small>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:0.75rem;">
                                        <span class="badge <?= $r['status'] === 'registered' ? 'badge-primary' : ($r['status'] === 'attended' ? 'badge-success' : 'badge-danger') ?>">
                                            <?= sh_e(ucfirst($r['status'])) ?>
                                        </span>
                                        <?php if ($r['status'] === 'registered'): ?>
                                            <form method="POST" action="dashboard.php" style="display:inline;" onsubmit="return confirm('Cancel registration for this event?');">
                                                <input type="hidden" name="csrf_token" value="<?= sh_e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="cancel_registration">
                                                <input type="hidden" name="registration_id" value="<?= (int) $r['registration_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <!-- Quick Event Registration Card -->
                <section class="card">
                    <div class="section-heading">
                        <h2>Register for an Upcoming Event</h2>
                    </div>
                    <?php if (empty($openEvents)): ?>
                        <p class="small-note">No open events currently available for registration.</p>
                    <?php else: ?>
                        <form method="POST" action="dashboard.php" class="form-vertical">
                            <input type="hidden" name="csrf_token" value="<?= sh_e($csrfToken) ?>">
                            <input type="hidden" name="action" value="register_event">

                            <div class="field-group">
                                <label for="event_id">Select Open Event</label>
                                <select id="event_id" name="event_id" required>
                                    <option value="">-- Choose an Event --</option>
                                    <?php foreach ($openEvents as $ev): ?>
                                        <option value="<?= (int) $ev['event_id'] ?>">
                                            <?= sh_e($ev['title']) ?> (<?= sh_e(date('d M Y', strtotime($ev['event_date']))) ?> &middot; <?= sh_e($ev['venue']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Confirm Registration</button>
                        </form>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Right Column: Quick Links -->
            <section class="card">
                <h2>Quick Links</h2>
                <div class="quick-links">
                    <a href="profile.php"><strong>My Profile</strong><span>View &amp; update personal details and password</span></a>
                    <a href="events.html"><strong>Campus Events</strong><span>Browse all upcoming event posters &amp; categories</span></a>
                    <a href="students.html"><strong>Student Directory</strong><span>Search and filter the university student list</span></a>
                    <a href="practical8.php"><strong>Practical 8 Demo</strong><span>Explore PDO MySQL queries and ER Model</span></a>
                    <a href="practical7.php"><strong>Practical 7 Demo</strong><span>PHP form processing and CSV storage</span></a>
                    <a href="feedback.html"><strong>Give Feedback</strong><span>Share your comments with the portal team</span></a>
                </div>
            </section>
        </div>
    </div>
<?php sh_public_footer(); ?>
