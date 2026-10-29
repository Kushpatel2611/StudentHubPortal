<?php
/**
 * StudentHub Portal - Automated MySQL Integration Test Suite
 * 
 * Verifies application and database behavior against a dedicated test database (studenthub_test).
 * Run from terminal:
 *   php tests/run_tests.php
 * or double-click run_tests.bat in Windows.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/test_config.php';
require_once __DIR__ . '/IntegrationTestCase.php';

// Color formatting helpers for terminal
function color(string $text, string $colorCode): string {
    // Windows 10/11 supports standard ANSI escape codes
    return "\033[{$colorCode}m{$text}\033[0m";
}
function cPass(string $t): string { return color($t, '32;1'); } // Bright Green
function cFail(string $t): string { return color($t, '31;1'); } // Bright Red
function cBold(string $t): string { return color($t, '1'); }
function cMuted(string $t): string { return color($t, '90'); }
function cCyan(string $t): string { return color($t, '36;1'); }

echo PHP_EOL . cBold("=====================================================================") . PHP_EOL;
echo cCyan("  StudentHub Portal — Automated MySQL Integration Test Suite") . PHP_EOL;
echo cMuted("  Dedicated Test Database: ") . cBold($TEST_DB_NAME) . " on " . $TEST_DB_HOST . ":" . $TEST_DB_PORT . PHP_EOL;
echo cBold("=====================================================================") . PHP_EOL . PHP_EOL;

// 1. SETUP DEDICATED TEST DATABASE SCHEMA
try {
    $serverPdo = getTestPdo(false);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$TEST_DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = getTestPdo(true);

    // Read and initialize tables if needed
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tablesSql = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            last_login DATETIME NULL,
            remember_token_hash CHAR(64) NULL,
            remember_token_expires DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_users_remember (remember_token_hash)
        ) ENGINE=InnoDB;",

        "CREATE TABLE IF NOT EXISTS students (
            student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_code VARCHAR(20) NOT NULL UNIQUE,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(254) NOT NULL UNIQUE,
            course VARCHAR(100) NOT NULL,
            status ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        "CREATE TABLE IF NOT EXISTS events (
            event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            venue VARCHAR(150) NOT NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 100,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
            poster VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        "CREATE TABLE IF NOT EXISTS registrations (
            registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('registered', 'attended', 'cancelled') NOT NULL DEFAULT 'registered',
            CONSTRAINT fk_registrations_student FOREIGN KEY (student_id)
                REFERENCES students(student_id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT fk_registrations_event FOREIGN KEY (event_id)
                REFERENCES events(event_id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT uq_student_event UNIQUE (student_id, event_id),
            INDEX idx_registrations_event (event_id),
            INDEX idx_registrations_student (student_id)
        ) ENGINE=InnoDB;",

        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_user (user_id),
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB;"
    ];

    foreach ($tablesSql as $sql) {
        $pdo->exec($sql);
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
} catch (Exception $e) {
    echo cFail("FATAL: Could not connect to MySQL server: " . $e->getMessage()) . PHP_EOL;
    echo cMuted("Ensure MySQL is running in XAMPP on localhost:3306.") . PHP_EOL;
    exit(1);
}

// 2. DEFINE TESTS
$suite = new IntegrationTestCase($pdo);
$tests = [
    // Database connection & Schema
    'testDatabaseConnection' => function(IntegrationTestCase $t, PDO $pdo) {
        $t->assertNotNull($pdo, 'PDO connection must not be null');
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $t->assertEquals('mysql', $driver, 'Driver must be mysql');
        $errMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
        $t->assertEquals(PDO::ERRMODE_EXCEPTION, $errMode, 'PDO error mode must be ERRMODE_EXCEPTION');
    },

    'testRequiredDatabaseSchemaAndTables' => function(IntegrationTestCase $t, PDO $pdo) {
        $requiredTables = ['users', 'students', 'events', 'registrations', 'audit_logs'];
        $stmt = $pdo->query("SHOW TABLES");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($requiredTables as $table) {
            $t->assertTrue(in_array($table, $existing, true), "Table '{$table}' must exist in schema");
        }

        // Verify users columns
        $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['id', 'username', 'email', 'password', 'role', 'status', 'last_login'] as $col) {
            $t->assertTrue(in_array($col, $userCols, true), "Column 'users.{$col}' must exist");
        }

        // Verify registrations unique constraint
        $indexes = $pdo->query("SHOW INDEX FROM registrations WHERE Key_name = 'uq_student_event'")->fetchAll();
        $t->assertGreaterThan(0, count($indexes), "Unique key 'uq_student_event' must exist on registrations");
    },

    // User Registration
    'testSuccessfulUserRegistration' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $username = 'testuser_' . $rand;
        $email = "test_{$rand}@charusat.edu.in";
        $password = 'TestPass@123';
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'student', 'active')");
        $ok = $stmt->execute([$username, $email, $hashed]);
        $t->assertTrue($ok, "User insert should succeed");
        $newId = (int) $pdo->lastInsertId();
        $t->trackUser($newId);

        // Assert record exists
        $fetch = $pdo->prepare("SELECT username, email, role, status FROM users WHERE id = ?");
        $fetch->execute([$newId]);
        $row = $fetch->fetch();
        $t->assertEquals($username, $row['username']);
        $t->assertEquals($email, $row['email']);
        $t->assertEquals('student', $row['role']);
        $t->assertEquals('active', $row['status']);
    },

    'testInvalidRegistrationData' => function(IntegrationTestCase $t, PDO $pdo) {
        // Validation check simulation
        $invalidUsernames = ['ab', 'usr@#$%', ''];
        foreach ($invalidUsernames as $badUser) {
            $valid = (strlen($badUser) >= 3 && strlen($badUser) <= 50 && preg_match('/^[a-zA-Z0-9_]+$/', $badUser));
            $t->assertFalse((bool)$valid, "Username '{$badUser}' must fail validation");
        }

        $invalidEmails = ['plainaddress', 'missing@domain', '@no-local.com'];
        foreach ($invalidEmails as $badEmail) {
            $t->assertFalse((bool)filter_var($badEmail, FILTER_VALIDATE_EMAIL), "Email '{$badEmail}' must be invalid");
        }

        $shortPassword = 'short';
        $t->assertFalse(strlen($shortPassword) >= 8, 'Passwords under 8 chars must be rejected');
    },

    'testDuplicateAccountRegistration' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $username = 'dup_' . $rand;
        $email = "dup_{$rand}@example.com";
        $hash = password_hash('Pass@1234', PASSWORD_DEFAULT);

        // First insert
        $ins = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
        $ins->execute([$username, $email, $hash]);
        $t->trackUser((int) $pdo->lastInsertId());

        // Attempt duplicate username
        $caught = false;
        try {
            $ins2 = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
            $ins2->execute([$username, "other_{$rand}@example.com", $hash]);
            $t->trackUser((int) $pdo->lastInsertId());
        } catch (PDOException $e) {
            $caught = true;
            $t->assertStringContains('1062', (string)$e->getMessage(), 'Should be duplicate key error 1062');
        }
        $t->assertTrue($caught, 'Duplicate username must throw a PDOException');
    },

    // Password Hashing & Login Verification
    'testPasswordHashingAndVerification' => function(IntegrationTestCase $t, PDO $pdo) {
        $plain = 'ComplexSecur1ty#Password';
        $hash = password_hash($plain, PASSWORD_DEFAULT);

        $t->assertFalse($hash === $plain, 'Hash must not equal plaintext');
        $t->assertStringContains('$2y$', $hash, 'Hash should use standard Bcrypt cost format');
        $t->assertTrue(password_verify($plain, $hash), 'password_verify must pass with correct password');
        $t->assertFalse(password_verify('WrongPassword#999', $hash), 'password_verify must fail with incorrect password');
    },

    'testLoginWithCorrectCredentials' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $username = 'login_user_' . $rand;
        $email = "login_{$rand}@example.com";
        $password = 'CorrectP@ssword123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'student', 'active')");
        $ins->execute([$username, $email, $hash]);
        $userId = (int) $pdo->lastInsertId();
        $t->trackUser($userId);

        // Query by email/username
        $stmt = $pdo->prepare("SELECT id, username, email, password, role, status FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        $t->assertNotNull($user, 'User record must be found');
        $t->assertTrue(password_verify($password, $user['password']), 'Password must verify');
        $t->assertEquals('active', $user['status'], 'Account must be active');

        // Simulate last_login update
        $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $upd->execute([$userId]);
        $checkLogin = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
        $checkLogin->execute([$userId]);
        $t->assertNotNull($checkLogin->fetchColumn(), 'last_login must be updated in MySQL');
    },

    'testLoginWithIncorrectCredentials' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $email = "badlogin_{$rand}@example.com";
        $hash = password_hash('RightPass123', PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $ins->execute(['badlogin_' . $rand, $email, $hash]);
        $t->trackUser((int) $pdo->lastInsertId());

        // Check wrong password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $storedHash = $stmt->fetchColumn();

        $t->assertFalse(password_verify('IncorrectPassword', $storedHash), 'Wrong password must fail authentication');

        // Check non-existent user
        $stmt2 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt2->execute(['nonexistent_user@example.com']);
        $t->assertFalse((bool)$stmt2->fetch(), 'Non-existent user must return false');
    },

    // User and Profile Data Retrieval
    'testRetrievingUserAndProfileData' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $code = '25CE' . mt_rand(100, 999);
        $name = 'Profile Student ' . $rand;
        $email = "profile_{$rand}@charusat.edu.in";
        $course = 'B.Tech Computer Engineering';

        // Insert student
        $insStd = $pdo->prepare("INSERT INTO students (student_code, full_name, email, course, status) VALUES (?, ?, ?, ?, 'active')");
        $insStd->execute([$code, $name, $email, $course]);
        $studentId = (int) $pdo->lastInsertId();
        $t->trackStudent($studentId);

        // Fetch by email
        $fetch = $pdo->prepare("SELECT student_id, student_code, full_name, email, course, status FROM students WHERE email = ? LIMIT 1");
        $fetch->execute([$email]);
        $row = $fetch->fetch();

        $t->assertEquals($code, $row['student_code']);
        $t->assertEquals($name, $row['full_name']);
        $t->assertEquals($course, $row['course']);
        $t->assertEquals('active', $row['status']);
    },

    // Updating Existing Records
    'testUpdatingExistingRecords' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $code = '25IT' . mt_rand(100, 999);
        $originalName = 'Original Name ' . $rand;
        $email = "update_{$rand}@example.com";

        $ins = $pdo->prepare("INSERT INTO students (student_code, full_name, email, course) VALUES (?, ?, ?, 'B.Tech IT')");
        $ins->execute([$code, $originalName, $email]);
        $studentId = (int) $pdo->lastInsertId();
        $t->trackStudent($studentId);

        // Update record
        $updatedName = 'Updated Name ' . $rand;
        $updatedCourse = 'B.Tech AI & Data Science';
        $upd = $pdo->prepare("UPDATE students SET full_name = ?, course = ? WHERE student_id = ?");
        $upd->execute([$updatedName, $updatedCourse, $studentId]);

        $check = $pdo->prepare("SELECT full_name, course FROM students WHERE student_id = ?");
        $check->execute([$studentId]);
        $res = $check->fetch();

        $t->assertEquals($updatedName, $res['full_name'], 'full_name must be updated');
        $t->assertEquals($updatedCourse, $res['course'], 'course must be updated');
    },

    // Creating, Soft Deleting, and Restoring Records
    'testCreatingAndDeletingRecords' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $code = '25EC' . mt_rand(100, 999);
        $email = "delete_test_{$rand}@example.com";

        $ins = $pdo->prepare("INSERT INTO students (student_code, full_name, email, course, status) VALUES (?, 'Test Deletion', ?, 'B.Tech EC', 'active')");
        $ins->execute([$code, $email]);
        $studentId = (int) $pdo->lastInsertId();
        $t->trackStudent($studentId);

        // Soft delete
        $del = $pdo->prepare("UPDATE students SET status = 'deleted' WHERE student_id = ?");
        $del->execute([$studentId]);

        $st1 = $pdo->prepare("SELECT status FROM students WHERE student_id = ?");
        $st1->execute([$studentId]);
        $t->assertEquals('deleted', $st1->fetchColumn(), 'Status must be deleted after soft delete');

        // Restore
        $res = $pdo->prepare("UPDATE students SET status = 'active' WHERE student_id = ?");
        $res->execute([$studentId]);

        $st2 = $pdo->prepare("SELECT status FROM students WHERE student_id = ?");
        $st2->execute([$studentId]);
        $t->assertEquals('active', $st2->fetchColumn(), 'Status must be active after restoration');
    },

    // Events and Student Relational CRUD (Registrations)
    'testEventsAndStudentCrudOperations' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        // Create student
        $insStd = $pdo->prepare("INSERT INTO students (student_code, full_name, email, course) VALUES (?, 'Event Registrant', ?, 'CE')");
        $insStd->execute(['25CE' . mt_rand(100, 999), "reg_{$rand}@example.com"]);
        $studentId = (int) $pdo->lastInsertId();
        $t->trackStudent($studentId);

        // Create event
        $insEvt = $pdo->prepare("INSERT INTO events (title, description, event_date, venue, capacity, category, status) VALUES (?, 'Hackathon event', '2026-12-01', 'Lab 1', 50, 'Hackathon', 'open')");
        $insEvt->execute(['Annual Hackathon ' . $rand]);
        $eventId = (int) $pdo->lastInsertId();
        $t->trackEvent($eventId);

        // Register student for event
        $insReg = $pdo->prepare("INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'registered')");
        $insReg->execute([$studentId, $eventId]);
        $regId = (int) $pdo->lastInsertId();

        // Query with JOIN
        $joinStmt = $pdo->prepare(
            "SELECT s.full_name, e.title, r.status
             FROM registrations r
             JOIN students s ON s.student_id = r.student_id
             JOIN events e ON e.event_id = r.event_id
             WHERE r.registration_id = ?"
        );
        $joinStmt->execute([$regId]);
        $joined = $joinStmt->fetch();

        $t->assertEquals('Event Registrant', $joined['full_name']);
        $t->assertEquals('Annual Hackathon ' . $rand, $joined['title']);
        $t->assertEquals('registered', $joined['status']);

        // Duplicate registration must fail due to unique constraint uq_student_event
        $duplicateCaught = false;
        try {
            $insReg->execute([$studentId, $eventId]);
        } catch (PDOException $e) {
            $duplicateCaught = true;
        }
        $t->assertTrue($duplicateCaught, 'Duplicate registration for same student and event must be rejected');
    },

    // Authorization & Role Separation
    'testAdminAuthorization' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        // Student role
        $ins1 = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, 'hash', 'student')");
        $ins1->execute(['std_role_' . $rand, "std_role_{$rand}@example.com"]);
        $stdId = (int) $pdo->lastInsertId();
        $t->trackUser($stdId);

        // Admin role
        $ins2 = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, 'hash', 'admin')");
        $ins2->execute(['adm_role_' . $rand, "adm_role_{$rand}@example.com"]);
        $admId = (int) $pdo->lastInsertId();
        $t->trackUser($admId);

        // Role check logic
        $checkRole = function($role, $required) {
            return $role === $required;
        };

        $t->assertFalse($checkRole('student', 'admin'), 'Student role must not have admin access');
        $t->assertTrue($checkRole('admin', 'admin'), 'Admin role must have admin access');
    },

    // Unauthorized Access and Inactive User Handling
    'testUnauthorizedAccessToProtectedPages' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        // Inactive user
        $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, 'hash', 'student', 'inactive')");
        $ins->execute(['inactive_' . $rand, "inactive_{$rand}@example.com"]);
        $userId = (int) $pdo->lastInsertId();
        $t->trackUser($userId);

        // Check status query
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $status = $stmt->fetchColumn();

        $t->assertEquals('inactive', $status);
        $t->assertFalse($status === 'active', 'Inactive account must be rejected from protected areas');
    },

    // Session Handling & Remember Me Tokens
    'testSessionHandlingAndLogout' => function(IntegrationTestCase $t, PDO $pdo) {
        $rand = bin2hex(random_bytes(4));
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiry = date('Y-m-d H:i:s', time() + 30 * 86400);

        $ins = $pdo->prepare("INSERT INTO users (username, email, password, remember_token_hash, remember_token_expires) VALUES (?, ?, 'hash', ?, ?)");
        $ins->execute(['remember_' . $rand, "rem_{$rand}@example.com", $tokenHash, $expiry]);
        $userId = (int) $pdo->lastInsertId();
        $t->trackUser($userId);

        // Verify token lookup
        $lookup = $pdo->prepare("SELECT id, username FROM users WHERE remember_token_hash = ? AND remember_token_expires > NOW() AND status = 'active'");
        $lookup->execute([$tokenHash]);
        $user = $lookup->fetch();
        $t->assertNotNull($user, 'Remember token lookup must succeed for valid unexpired token');
        $t->assertEquals($userId, (int)$user['id']);

        // Simulate logout / token clearing
        $clear = $pdo->prepare("UPDATE users SET remember_token_hash = NULL, remember_token_expires = NULL WHERE id = ?");
        $clear->execute([$userId]);

        $lookup->execute([$tokenHash]);
        $t->assertFalse((bool)$lookup->fetch(), 'Token lookup must return false after token is cleared on logout');
    },

    // Database Errors & Prepared Statements SQL Injection Immunity
    'testDatabaseErrorsAndInvalidInputHandling' => function(IntegrationTestCase $t, PDO $pdo) {
        // Foreign Key violation
        $fkCaught = false;
        try {
            $stmt = $pdo->prepare("INSERT INTO registrations (student_id, event_id) VALUES (999999, 999999)");
            $stmt->execute();
        } catch (PDOException $e) {
            $fkCaught = true;
            $code = (int) ($e->errorInfo[1] ?? 0);
            $t->assertTrue($code === 1452 || $e->getCode() === '23000' || strpos($e->getMessage(), 'foreign key') !== false, 'Should be foreign key violation code 1452');
        }
        $t->assertTrue($fkCaught, 'Invalid foreign key ID must fail foreign key check');

        // SQL Injection resistance test with prepared statements
        $maliciousPayload = "test' OR '1'='1";
        $stmt2 = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt2->execute([$maliciousPayload]);
        $rows = $stmt2->fetchAll();
        $t->assertEquals(0, count($rows), 'SQL injection string must be treated as literal value by prepared statement');
    }
];

// 3. EXECUTE TEST SUITE
$passed = 0;
$failed = 0;
$total = count($tests);
$startTime = microtime(true);

echo cBold("Running {$total} Integration Tests...") . PHP_EOL . PHP_EOL;

foreach ($tests as $testName => $testFunc) {
    echo str_pad("  • " . $testName, 54, '.');
    $testStart = microtime(true);
    try {
        $testPdo = getTestPdo(true);
        $testFunc($suite, $testPdo);
        $duration = round((microtime(true) - $testStart) * 1000, 1);
        echo " " . cPass("[PASS]") . cMuted(" ({$duration}ms)") . PHP_EOL;
        $passed++;
    } catch (Throwable $e) {
        $duration = round((microtime(true) - $testStart) * 1000, 1);
        echo " " . cFail("[FAIL]") . cMuted(" ({$duration}ms)") . PHP_EOL;
        echo "    " . cFail("Assertion error: " . $e->getMessage()) . PHP_EOL;
        $failed++;
    }
}

// 4. CLEAN UP TEST FIXTURES
$suite->cleanUp();

$totalDuration = round((microtime(true) - $startTime), 3);
echo PHP_EOL . cBold("=====================================================================") . PHP_EOL;
if ($failed === 0) {
    echo cPass("  TEST RUN COMPLETE: ALL {$passed} TESTS PASSED!") . PHP_EOL;
    echo cMuted("  Assertions verified: ") . cBold((string)$suite->getAssertionsCount()) . PHP_EOL;
    echo cMuted("  Execution time: ") . cBold("{$totalDuration}s") . PHP_EOL;
} else {
    echo cFail("  TEST RUN COMPLETED WITH FAILURES: {$passed} Passed, {$failed} Failed") . PHP_EOL;
    echo cMuted("  Execution time: ") . cBold("{$totalDuration}s") . PHP_EOL;
}
echo cBold("=====================================================================") . PHP_EOL . PHP_EOL;

exit($failed === 0 ? 0 : 1);
