<?php
/**
 * StudentHub - PDO database connection (shared by every page).
 * XAMPP defaults: MySQL user=root and an empty password.
 * Change these values if your local MySQL credentials are different.
 */
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_NAME = 'studenthub';
$DB_USER = 'root';
$DB_PASS = '';

// Optional overrides (so you never have to edit this file):
//  1) config.php   - copy config.example.php to config.php and change the values, or
//  2) environment variables STUDENTHUB_DB_HOST / _PORT / _NAME / _USER / _PASS.
if (is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}
foreach (['HOST' => 'DB_HOST', 'PORT' => 'DB_PORT', 'NAME' => 'DB_NAME', 'USER' => 'DB_USER', 'PASS' => 'DB_PASS'] as $suffix => $var) {
    $envValue = getenv('STUDENTHUB_DB_' . $suffix);
    if ($envValue !== false && $envValue !== '') {
        $$var = $envValue;
    }
}
$DB_PORT = (int) $DB_PORT;

/**
 * mysqli connection used by the Practical 11/12 admin pages.
 * PHP 8.1+ makes mysqli throw exceptions by default, which turned a duplicate
 * student code into a fatal error instead of the friendly message those pages
 * were written to show. Reporting is switched off so $stmt->errno / execute()
 * work as the original code expects.
 */
function sh_connect_mysqli(): mysqli
{
    global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    if ($mysqli->connect_error) {
        error_log('StudentHub mysqli connection failed: ' . $mysqli->connect_error);
        http_response_code(503);
        exit('Database connection failed. Check MySQL and the settings in db.php / config.php.');
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

// setup.php defines this so it can read the settings above and connect itself.
if (defined('STUDENTHUB_NO_CONNECT')) {
    return;
}

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $exception) {
    // Log technical details privately; never show credentials or server details.
    error_log('StudentHub database connection failed: ' . $exception->getMessage());
    $driverCode = (int) ($exception->errorInfo[1] ?? 0);

    http_response_code(503);

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database is not available.']);
        exit;
    }

    $hint = $driverCode === 1049
        ? 'The "studenthub" database does not exist yet. Open <a href="setup.php">setup.php</a> once to create it.'
        : 'Start <strong>MySQL</strong> in the XAMPP Control Panel, then check the settings in <code>db.php</code> (or <code>config.php</code>).';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Database unavailable | StudentHub</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#f1f5f9;color:#1e293b;padding:40px 15px">'
        . '<div style="max-width:560px;margin:40px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 5px 20px #00000015">'
        . '<h1 style="color:#b91c1c;margin-top:0">Database unavailable</h1>'
        . '<p>' . $hint . '</p>'
        . '<p><a href="index.html">Back to StudentHub</a></p></div></body></html>';
    exit;
}
