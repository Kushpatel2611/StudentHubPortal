<?php
/**
 * Tiny JSON endpoint: is somebody logged in? js/auth_nav.js uses it so the
 * static .html pages can show Login/Register or Dashboard/Logout.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/auth.php';

$active = !empty($_SESSION['user_id'])
    && (time() - ($_SESSION['last_activity'] ?? 0)) <= SESSION_TIMEOUT;

echo json_encode([
    'loggedIn' => $active,
    'role' => $active ? ($_SESSION['role'] ?? null) : null,
    'username' => $active ? ($_SESSION['username'] ?? null) : null,
]);
