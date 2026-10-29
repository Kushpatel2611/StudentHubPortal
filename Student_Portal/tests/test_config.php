<?php
/**
 * StudentHub Test Configuration
 * Configures the dedicated MySQL test database connection.
 * Isolates integration tests from production data.
 */

$TEST_DB_HOST = getenv('STUDENTHUB_TEST_DB_HOST') ?: '127.0.0.1';
$TEST_DB_PORT = (int) (getenv('STUDENTHUB_TEST_DB_PORT') ?: 3306);
$TEST_DB_NAME = getenv('STUDENTHUB_TEST_DB_NAME') ?: 'studenthub_test';
$TEST_DB_USER = getenv('STUDENTHUB_TEST_DB_USER') ?: 'root';
$TEST_DB_PASS = getenv('STUDENTHUB_TEST_DB_PASS') !== false ? getenv('STUDENTHUB_TEST_DB_PASS') : '';

// Function to establish connection to MySQL test server
function getTestPdo(bool $withDb = true): PDO {
    global $TEST_DB_HOST, $TEST_DB_PORT, $TEST_DB_NAME, $TEST_DB_USER, $TEST_DB_PASS;

    $dsn = $withDb 
        ? "mysql:host={$TEST_DB_HOST};port={$TEST_DB_PORT};dbname={$TEST_DB_NAME};charset=utf8mb4"
        : "mysql:host={$TEST_DB_HOST};port={$TEST_DB_PORT};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, $TEST_DB_USER, $TEST_DB_PASS, $options);
}
