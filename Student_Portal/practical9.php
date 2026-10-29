<?php
/**
 * StudentHub Practical 9 - Registration with validation, password hashing, and CSRF protection.
 * Automatically provisions matching student directory records in MySQL.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Already signed in: no need to register again.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . sh_home_for_role($_SESSION['role'] ?? null));
    exit;
}

sh_csrf_token();

$message = '';
$old = [
    'username'     => '',
    'full_name'    => '',
    'student_code' => '',
    'email'        => '',
    'course'       => 'B.Tech Computer Engineering'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $username = trim((string) ($_POST['username'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $studentCode = strtoupper(trim((string) ($_POST['student_code'] ?? '')));
    $email = trim((string) ($_POST['email'] ?? ''));
    $course = trim((string) ($_POST['course'] ?? 'B.Tech Computer Engineering'));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $old = [
        'username'     => $username,
        'full_name'    => $fullName,
        'student_code' => $studentCode,
        'email'        => $email,
        'course'       => $course
    ];

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please refresh the page and try again.';
    } elseif (!is_string($password) || !is_string($confirmPassword)) {
        $message = 'Invalid password input.';
    } elseif (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $message = 'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } else {
        // Defaults if student details were not explicitly typed
        if ($fullName === '') {
            $fullName = ucwords(str_replace(['.', '_'], ' ', $username));
        }
        if ($studentCode === '') {
            $studentCode = '25CE' . str_pad((string) mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        }

        try {
            $pdo->beginTransaction();

            // Check duplicate user in users table
            $checkUser = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $checkUser->execute([$username, $email]);

            if ($checkUser->fetch()) {
                $pdo->rollBack();
                $message = 'Username or email already exists.';
            } else {
                // Check duplicate student_code in students table
                $checkStudent = $pdo->prepare('SELECT student_id FROM students WHERE student_code = ? OR email = ? LIMIT 1');
                $checkStudent->execute([$studentCode, $email]);

                if ($checkStudent->fetch()) {
                    $pdo->rollBack();
                    $message = 'Student code or email already exists in the student directory.';
                } else {
                    // Insert into users
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $insertUser = $pdo->prepare(
                        "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'student', 'active')"
                    );
                    $insertUser->execute([$username, $email, $hash]);
                    $newUserId = (int) $pdo->lastInsertId();

                    // Insert into students
                    $insertStudent = $pdo->prepare(
                        "INSERT INTO students (student_code, full_name, email, course, status) VALUES (?, ?, ?, ?, 'active')"
                    );
                    $insertStudent->execute([$studentCode, $fullName, $email, $course]);

                    // Audit log
                    sh_audit($pdo, 'user_registered', "New student account created: {$username} ({$studentCode})");

                    $pdo->commit();

                    // Post/Redirect/Get: stops a refresh from re-submitting the form.
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: login.php?registered=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student registration error: ' . $e->getMessage());
            $code = (int) ($e->errorInfo[1] ?? 0);
            $message = ($code === 1062)
                ? 'Username, student code, or email already exists.'
                : 'Registration failed. Check that the database tables exist (open setup.php).';
        }
    }
}

sh_public_header('Register', 'Student Registration', 'practical9.php');
?>
    <div class="container page-container">
        <section class="card auth-card" style="max-width: 540px;">
            <div class="section-heading" style="text-align: center; margin-bottom: 1.25rem;">
                <p class="eyebrow">JOIN STUDENTHUB</p>
                <h2>Create Your Student Account</h2>
                <p class="muted">Access campus events, academic directory, and personal dashboard.</p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-error" role="alert"><?= sh_e($message) ?></div>
            <?php endif; ?>

            <form method="POST" action="practical9.php" class="form-vertical" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= sh_e($_SESSION['csrf_token']) ?>">

                <div class="form-row">
                    <div class="field-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" value="<?= sh_e($old['username']) ?>"
                               minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+"
                               placeholder="e.g. kush_patel"
                               title="Use letters, numbers, and underscores only."
                               autocomplete="username" required>
                    </div>

                    <div class="field-group">
                        <label for="student_code">Enrollment / Code *</label>
                        <input type="text" id="student_code" name="student_code" value="<?= sh_e($old['student_code']) ?>"
                               minlength="3" maxlength="20" placeholder="e.g. 25CE045"
                               autocomplete="off" required>
                    </div>
                </div>

                <div class="field-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" value="<?= sh_e($old['full_name']) ?>"
                           minlength="2" maxlength="100" placeholder="e.g. Kush Patel"
                           autocomplete="name" required>
                </div>

                <div class="field-group">
                    <label for="email">CHARUSAT or Personal Email *</label>
                    <input type="email" id="email" name="email" value="<?= sh_e($old['email']) ?>"
                           maxlength="100" placeholder="you@charusat.edu.in" autocomplete="email" required>
                </div>

                <div class="field-group">
                    <label for="course">Degree / Course *</label>
                    <select id="course" name="course" required>
                        <option value="B.Tech Computer Engineering" <?= $old['course'] === 'B.Tech Computer Engineering' ? 'selected' : '' ?>>B.Tech Computer Engineering</option>
                        <option value="B.Tech Information Technology" <?= $old['course'] === 'B.Tech Information Technology' ? 'selected' : '' ?>>B.Tech Information Technology</option>
                        <option value="B.Tech Artificial Intelligence" <?= $old['course'] === 'B.Tech Artificial Intelligence' ? 'selected' : '' ?>>B.Tech Artificial Intelligence</option>
                        <option value="B.Tech Electronics &amp; Communication" <?= $old['course'] === 'B.Tech Electronics &amp; Communication' ? 'selected' : '' ?>>B.Tech Electronics &amp; Communication</option>
                        <option value="BCA / MCA" <?= $old['course'] === 'BCA / MCA' ? 'selected' : '' ?>>BCA / MCA</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="field-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password"
                               minlength="8" placeholder="At least 8 chars" autocomplete="new-password" required>
                    </div>

                    <div class="field-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               minlength="8" placeholder="Re-enter password" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Register Account</button>
            </form>

            <p style="margin-top:1.25rem;text-align:center;">Already have an account? <a href="login.php"><strong>Log in here</strong></a></p>
        </section>
    </div>
<?php sh_public_footer(['js/auth_forms.js']); ?>
