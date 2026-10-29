<?php
/**
 * StudentHub one-time setup (safe to run more than once).
 *  - creates the database and any missing tables
 *  - adds any missing columns to tables you already have (no data is deleted)
 *  - creates the first admin account, or promotes an existing user to admin
 * Only reachable from the same computer (localhost).
 */
define('STUDENTHUB_NO_CONNECT', true);
require_once __DIR__ . '/db.php'; // provides $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('setup.php can only be opened from the computer that runs the server.');
}

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

$log = [];
$errors = [];
$adminMessage = '';
$adminError = '';

$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('student','admin') NOT NULL DEFAULT 'student',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        last_login DATETIME NULL,
        remember_token_hash CHAR(64) NULL,
        remember_token_expires DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_users_remember (remember_token_hash)
    ) ENGINE=InnoDB",
    'students' => "CREATE TABLE IF NOT EXISTS students (
        student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_code VARCHAR(20) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(254) NOT NULL UNIQUE,
        course VARCHAR(100) NOT NULL,
        status ENUM('active','deleted') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    'events' => "CREATE TABLE IF NOT EXISTS events (
        event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        event_date DATE NOT NULL,
        venue VARCHAR(150) NOT NULL,
        capacity INT UNSIGNED NOT NULL DEFAULT 100,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        poster VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    'registrations' => "CREATE TABLE IF NOT EXISTS registrations (
        registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT UNSIGNED NOT NULL,
        event_id INT UNSIGNED NOT NULL,
        registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
        CONSTRAINT fk_registrations_student FOREIGN KEY (student_id)
            REFERENCES students(student_id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_registrations_event FOREIGN KEY (event_id)
            REFERENCES events(event_id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT uq_student_event UNIQUE (student_id, event_id)
    ) ENGINE=InnoDB",
    'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_user (user_id),
        INDEX idx_audit_created (created_at)
    ) ENGINE=InnoDB",
];

// Columns later practicals need, added only when a table already exists without them.
$columns = [
    'users' => [
        'role' => "ENUM('student','admin') NOT NULL DEFAULT 'student'",
        'status' => "ENUM('active','inactive') NOT NULL DEFAULT 'active'",
        'last_login' => 'DATETIME NULL',
        'remember_token_hash' => 'CHAR(64) NULL',
        'remember_token_expires' => 'DATETIME NULL',
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ],
    'students' => [
        'status' => "ENUM('active','deleted') NOT NULL DEFAULT 'active'",
    ],
    'events' => [
        'category' => "VARCHAR(100) NOT NULL DEFAULT 'General'",
        'status' => "ENUM('open','closed') NOT NULL DEFAULT 'open'",
        'poster' => 'VARCHAR(255) NULL',
    ],
];

$pdo = null;
try {
    $server = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $log[] = "Database '{$DB_NAME}' is ready.";

    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $hasColumn = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );

    foreach ($tables as $name => $sql) {
        $exists->execute([$DB_NAME, $name]);
        $already = (int) $exists->fetchColumn() > 0;
        $pdo->exec($sql);
        $log[] = $already ? "Table '{$name}' already exists." : "Table '{$name}' created.";
    }

    foreach ($columns as $table => $defs) {
        foreach ($defs as $column => $definition) {
            $hasColumn->execute([$DB_NAME, $table, $column]);
            if ((int) $hasColumn->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                $log[] = "Added missing column {$table}.{$column}.";
            }
        }
    }
} catch (PDOException $e) {
    error_log('StudentHub setup error: ' . $e->getMessage());
    $errors[] = 'Could not reach MySQL. Start MySQL in the XAMPP Control Panel and check the settings at the top of db.php.';
}

// Create or promote the admin account.
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['setup_csrf'], $token)) {
        $adminError = 'Invalid request. Reload the page and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $find = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $find->execute([$email]);
        $existing = $find->fetch();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $adminError = 'Enter a valid email address.';
        } elseif ($existing) {
            $pdo->prepare("UPDATE users SET role = 'admin', status = 'active' WHERE id = ?")
                ->execute([$existing['id']]);
            $adminMessage = "Existing account {$email} is now an admin. Log in with its normal password.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $adminError = 'Username must be 3-50 characters: letters, numbers, underscores.';
        } elseif (strlen($password) < 8) {
            $adminError = 'Password must contain at least 8 characters.';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')"
                )->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $adminMessage = "Admin account {$email} created.";
            } catch (PDOException $e) {
                $adminError = 'That username is already taken. Choose another one.';
            }
        }
    }
}

$adminCount = 0;
if ($pdo) {
    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Setup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 30px 15px; }
        .box { max-width: 620px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 12px; box-shadow: 0 5px 20px #00000015; }
        h1 { color: #0b40ef; margin-top: 0; } li { margin: 4px 0; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input { box-sizing: border-box; width: 100%; padding: 10px; margin-top: 6px; }
        button { margin-top: 16px; padding: 11px 20px; background: #0b40ef; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
        .ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; }
        .bad { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; }
    </style>
</head>
<body>
<div class="box">
    <h1>StudentHub Setup</h1>

    <?php foreach ($errors as $err): ?><p class="bad"><?= h($err) ?></p><?php endforeach; ?>

    <?php if ($log): ?>
        <h3>Database</h3>
        <ul><?php foreach ($log as $line): ?><li><?= h($line) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <?php if ($pdo): ?>
        <h3>Admin account</h3>
        <?php if ($adminMessage): ?><p class="ok"><?= h($adminMessage) ?></p><?php endif; ?>
        <?php if ($adminError): ?><p class="bad"><?= h($adminError) ?></p><?php endif; ?>

        <?php if ($adminCount > 0): ?>
            <p class="ok">An admin account exists. Setup is complete.</p>
            <p><a href="login.php">Go to login</a></p>
        <?php endif; ?>

        <p><?= $adminCount > 0 ? 'Need another admin? Use the form below.' : 'No admin yet. Create one below.' ?>
           If the email already belongs to a registered user, that user is promoted to admin instead.</p>
        <form method="POST" action="setup.php">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['setup_csrf']) ?>">
            <label for="username">Username (new accounts only)</label>
            <input id="username" name="username" placeholder="admin">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required>
            <label for="password">Password (new accounts only, 8+ characters)</label>
            <input id="password" name="password" type="password" autocomplete="new-password">
            <button type="submit">Create / promote admin</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
