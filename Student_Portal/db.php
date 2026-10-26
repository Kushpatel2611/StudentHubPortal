<?php
/**
 * StudentHub Practical 8 - PDO database connection.
 * XAMPP defaults: MySQL user=root and an empty password.
 * Change these values if your local MySQL credentials are different.
 */
$DB_HOST = '127.0.0.1';
$DB_NAME = 'studenthub';
$DB_USER = 'root';
$DB_PASS = '';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $exception) {
    // Log technical details privately; do not expose credentials or server details.
    error_log('StudentHub database connection failed: ' . $exception->getMessage());
    throw new RuntimeException('Database connection failed. Check MySQL status and db.php settings. Import sql/studenthub_practical8.sql in phpMyAdmin.');
}
