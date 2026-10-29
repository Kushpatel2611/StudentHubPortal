<?php
/**
 * StudentHub - Student Profile & Account Settings
 * Dynamically displays and manages student and user profile details from MySQL.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$csrfToken = sh_csrf_token();

$message = '';
$messageType = 'success';

// Fetch current user account details
$userStmt = $pdo->prepare('SELECT id, username, email, password, role, status, created_at, last_login FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    logoutUser($pdo);
    header('Location: login.php');
    exit;
}

// Fetch linked student record by email
$studentStmt = $pdo->prepare('SELECT student_id, student_code, full_name, email, course, status, created_at FROM students WHERE email = ? LIMIT 1');
$studentStmt->execute([$user['email']]);
$student = $studentStmt->fetch();

// If no student record exists yet (and user is a student), create a linked record
if (!$student && ($user['role'] ?? '') === 'student') {
    $defaultCode = '25CE' . str_pad((string) $userId, 3, '0', STR_PAD_LEFT);
    $defaultName = ucwords(str_replace(['.', '_'], ' ', $user['username']));
    $defaultCourse = 'B.Tech Computer Engineering';

    try {
        $insertStudent = $pdo->prepare('INSERT INTO students (student_code, full_name, email, course, status) VALUES (?, ?, ?, ?, "active")');
        $insertStudent->execute([$defaultCode, $defaultName, $user['email'], $defaultCourse]);

        $studentStmt->execute([$user['email']]);
        $student = $studentStmt->fetch();
    } catch (PDOException $e) {
        error_log('Auto-link student profile error: ' . $e->getMessage());
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Invalid security token. Please refresh and try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. UPDATE PROFILE DETAILS
        if ($action === 'update_profile') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $studentCode = trim((string) ($_POST['student_code'] ?? ''));
            $course = trim((string) ($_POST['course'] ?? ''));

            if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
                $message = 'Full name must contain between 2 and 100 characters.';
                $messageType = 'error';
            } elseif ($studentCode === '' || mb_strlen($studentCode) < 3 || mb_strlen($studentCode) > 20) {
                $message = 'Student code must contain between 3 and 20 characters.';
                $messageType = 'error';
            } elseif ($course === '' || mb_strlen($course) < 2 || mb_strlen($course) > 100) {
                $message = 'Course must contain between 2 and 100 characters.';
                $messageType = 'error';
            } else {
                try {
                    // Check if student code is already taken by another student
                    $codeCheck = $pdo->prepare('SELECT student_id FROM students WHERE student_code = ? AND email != ? LIMIT 1');
                    $codeCheck->execute([$studentCode, $user['email']]);

                    if ($codeCheck->fetch()) {
                        $message = 'Student code "' . htmlspecialchars($studentCode) . '" is already assigned to another student.';
                        $messageType = 'error';
                    } else {
                        if ($student) {
                            $updateStmt = $pdo->prepare('UPDATE students SET full_name = ?, student_code = ?, course = ? WHERE email = ?');
                            $updateStmt->execute([$fullName, $studentCode, $course, $user['email']]);
                        } else {
                            $insertStmt = $pdo->prepare('INSERT INTO students (student_code, full_name, email, course, status) VALUES (?, ?, ?, ?, "active")');
                            $insertStmt->execute([$studentCode, $fullName, $user['email'], $course]);
                        }

                        sh_audit($pdo, 'profile_updated', 'Updated profile information for ' . $user['username']);
                        $message = 'Profile details updated successfully.';
                        $messageType = 'success';

                        // Refresh student record
                        $studentStmt->execute([$user['email']]);
                        $student = $studentStmt->fetch();
                    }
                } catch (PDOException $e) {
                    error_log('Profile update error: ' . $e->getMessage());
                    $message = 'Failed to update profile. Database error occurred.';
                    $messageType = 'error';
                }
            }
        }

        // 2. CHANGE PASSWORD
        elseif ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || !password_verify($currentPassword, $user['password'])) {
                $message = 'The current password you entered is incorrect.';
                $messageType = 'error';
            } elseif (strlen($newPassword) < 8) {
                $message = 'New password must contain at least 8 characters.';
                $messageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'New passwords do not match.';
                $messageType = 'error';
            } else {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $pwdStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $pwdStmt->execute([$newHash, $userId]);

                    sh_audit($pdo, 'password_changed', 'Password changed for user ' . $user['username']);
                    $message = 'Your password has been changed successfully.';
                    $messageType = 'success';

                    // Update local user hash
                    $user['password'] = $newHash;
                } catch (PDOException $e) {
                    error_log('Password change error: ' . $e->getMessage());
                    $message = 'Failed to update password. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Generate initials for avatar
$nameForInitials = !empty($student['full_name']) ? $student['full_name'] : $user['username'];
$parts = explode(' ', trim($nameForInitials));
$initials = '';
foreach ($parts as $p) {
    if ($p !== '') {
        $initials .= mb_substr($p, 0, 1);
        if (mb_strlen($initials) >= 2) break;
    }
}
$initials = strtoupper($initials ?: 'ST');

sh_public_header('Profile', 'Student Profile', 'profile.php');
?>
<div class="container page-container">
    <div class="page-intro">
        <p class="eyebrow"><?= strtoupper(sh_e($user['role'])) ?> PROFILE</p>
        <h2><?= sh_e(!empty($student['full_name']) ? $student['full_name'] : $user['username']) ?></h2>
        <p>Manage your academic profile, credentials, and account settings.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>" role="alert">
            <?= sh_e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Overview Card -->
    <section class="card profile-hero-card">
        <div class="profile-hero-content">
            <div class="profile-avatar-large" aria-hidden="true">
                <?= sh_e($initials) ?>
            </div>
            <div class="profile-hero-text">
                <div class="profile-badge-row">
                    <span class="badge badge-primary"><?= ucfirst(sh_e($user['role'])) ?></span>
                    <span class="badge <?= ($user['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                        <?= ucfirst(sh_e($user['status'] ?? 'active')) ?>
                    </span>
                    <span class="badge badge-neutral">ID #<?= $userId ?></span>
                </div>
                <h3 class="profile-hero-name"><?= sh_e(!empty($student['full_name']) ? $student['full_name'] : $user['username']) ?></h3>
                <p class="profile-hero-sub">
                    <strong><?= sh_e(!empty($student['student_code']) ? $student['student_code'] : 'No Code') ?></strong>
                    &bull; <?= sh_e(!empty($student['course']) ? $student['course'] : 'General Student') ?>
                </p>
            </div>
            <div class="profile-hero-actions">
                <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
            </div>
        </div>
    </section>

    <div class="profile-grid">
        <!-- Academic & Account Details -->
        <section class="card">
            <div class="section-header">
                <h3>Academic &amp; Account Details</h3>
                <small class="muted">Live records from MySQL</small>
            </div>
            <dl class="profile-detail-list">
                <div class="profile-detail-row">
                    <dt>Full Name</dt>
                    <dd><strong><?= sh_e(!empty($student['full_name']) ? $student['full_name'] : 'Not set') ?></strong></dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Enrollment / Student Code</dt>
                    <dd><code><?= sh_e(!empty($student['student_code']) ? $student['student_code'] : 'Not set') ?></code></dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Degree Programme</dt>
                    <dd><?= sh_e(!empty($student['course']) ? $student['course'] : 'Not set') ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Department / Institute</dt>
                    <dd>CSPIT, CHARUSAT University</dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Username</dt>
                    <dd><code><?= sh_e($user['username']) ?></code></dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Email Address</dt>
                    <dd><a href="mailto:<?= sh_e($user['email']) ?>"><?= sh_e($user['email']) ?></a></dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Member Since</dt>
                    <dd><?= sh_e($user['created_at'] ? date('d F Y', strtotime($user['created_at'])) : '-') ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt>Last Login</dt>
                    <dd><?= sh_e($user['last_login'] ? date('d M Y, H:i', strtotime($user['last_login'])) : 'First active session') ?></dd>
                </div>
            </dl>
        </section>

        <!-- Edit Profile Form -->
        <section class="card">
            <div class="section-header">
                <h3>Edit Academic Information</h3>
                <small class="muted">Update your directory record</small>
            </div>
            <form method="POST" action="profile.php" class="form-vertical">
                <input type="hidden" name="csrf_token" value="<?= sh_e($csrfToken) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="field-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?= sh_e(!empty($student['full_name']) ? $student['full_name'] : '') ?>" 
                           minlength="2" maxlength="100" required>
                </div>

                <div class="field-group">
                    <label for="student_code">Enrollment / Student Code</label>
                    <input type="text" id="student_code" name="student_code" 
                           value="<?= sh_e(!empty($student['student_code']) ? $student['student_code'] : '') ?>" 
                           minlength="3" maxlength="20" required>
                    <small class="form-hint">Example: 25CE030, 25IT014</small>
                </div>

                <div class="field-group">
                    <label for="course">Course / Programme</label>
                    <input type="text" id="course" name="course" 
                           value="<?= sh_e(!empty($student['course']) ? $student['course'] : 'B.Tech Computer Engineering') ?>" 
                           minlength="2" maxlength="100" required>
                </div>

                <div class="field-group">
                    <label>Email Address</label>
                    <input type="email" value="<?= sh_e($user['email']) ?>" disabled class="input-disabled">
                    <small class="form-hint">Email is linked to your login account and cannot be changed here.</small>
                </div>

                <button type="submit" class="btn btn-primary">Save Profile Changes</button>
            </form>
        </section>
    </div>

    <!-- Security & Password Change Card -->
    <section class="card profile-security-card">
        <div class="section-header">
            <h3>Change Password</h3>
            <small class="muted">Protect your account with a secure password</small>
        </div>

        <form method="POST" action="profile.php" class="form-vertical" style="max-width: 520px;">
            <input type="hidden" name="csrf_token" value="<?= sh_e($csrfToken) ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="field-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" 
                       autocomplete="current-password" required placeholder="Enter current password">
            </div>

            <div class="field-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" 
                       minlength="8" autocomplete="new-password" required placeholder="At least 8 characters">
            </div>

            <div class="field-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       minlength="8" autocomplete="new-password" required placeholder="Confirm new password">
            </div>

            <button type="submit" class="btn btn-secondary">Update Password</button>
        </form>
    </section>
</div>
<?php sh_public_footer(); ?>
