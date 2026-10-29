<?php
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) &&
               $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/db.php';

define('SESSION_TIMEOUT', 900); // 15 minutes

function clearRememberCookie(): void
{
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberToken(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE users
         SET remember_token_hash = NULL,
             remember_token_expires = NULL
         WHERE id = ?'
    );
    $stmt->execute([$userId]);
}

function logoutUser(PDO $pdo): void
{
    if (!empty($_SESSION['user_id'])) {
        clearRememberToken($pdo, (int) $_SESSION['user_id']);
    }

    clearRememberCookie();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax'
        ]);
    }

    session_destroy();
}

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    global $pdo;

    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logoutUser($pdo);
        header('Location: login.php?timeout=1');
        exit;
    }

    // Re-check account status on protected requests so deactivated users
    // cannot continue using an already-open session.
    $statusStmt = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $statusStmt->execute([(int) $_SESSION['user_id']]);
    $accountStatus = $statusStmt->fetchColumn();

    if ($accountStatus === false || $accountStatus !== 'active') {
        logoutUser($pdo);
        header('Location: login.php?inactive=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function requireRole(string $role): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        $home = (($_SESSION['role'] ?? '') === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Access denied | StudentHub</title></head>'
            . '<body style="font-family:Arial,sans-serif;background:#f1f5f9;color:#1e293b;padding:40px 15px">'
            . '<div style="max-width:520px;margin:40px auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 5px 20px #00000015">'
            . '<h1 style="color:#b91c1c;margin-top:0">403 - Access denied</h1>'
            . '<p>You are not authorized to open this page.</p>'
            . '<p><a href="' . $home . '">Go to my dashboard</a> &middot; <a href="index.html">Home</a></p>'
            . '</div></body></html>';
        exit;
    }
}

function restoreRememberedUser(PDO $pdo): void
{
    if (!empty($_SESSION['user_id']) ||
        empty($_COOKIE['remember_token'])) {
        return;
    }

    $token = $_COOKIE['remember_token'];

    if (!is_string($token) ||
        !preg_match('/^[a-f0-9]{64}$/', $token)) {
        clearRememberCookie();
        return;
    }

    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        "SELECT id, username, email, role, status
         FROM users
         WHERE remember_token_hash = ?
           AND remember_token_expires > NOW()
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if (!$user) {
        clearRememberCookie();
        return;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
}

restoreRememberedUser($pdo);
