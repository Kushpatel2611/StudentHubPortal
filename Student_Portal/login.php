<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Already signed in: go straight to the right dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . sh_home_for_role($_SESSION['role'] ?? null));
    exit;
}

sh_csrf_token();

$message = '';
$messageType = 'error';

if (isset($_GET['timeout'])) {
    $message = 'Your session expired. Please log in again.';
} elseif (isset($_GET['inactive'])) {
    $message = 'Your account is inactive or unavailable. Please contact an administrator.';
} elseif (isset($_GET['logged_out'])) {
    $message = 'You have been logged out.';
    $messageType = 'success';
} elseif (isset($_GET['registered'])) {
    $message = 'Account created. You can log in now.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Invalid request. Refresh the page and try again.';
        $messageType = 'error';
    } else {
        $loginInput = trim((string) ($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!is_string($password) || $loginInput === '') {
            $message = 'Please enter your email/username and password.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, username, email, password, role, status
                 FROM users WHERE email = ? OR username = ? LIMIT 1'
            );
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $message = 'Invalid email or password.';
            } elseif (($user['status'] ?? 'active') !== 'active') {
                $message = 'This account is inactive. Please contact an administrator.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $update->execute([$user['id']]);

                if ($remember) {
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiry = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);

                    $save = $pdo->prepare(
                        'UPDATE users
                         SET remember_token_hash = ?, remember_token_expires = ?
                         WHERE id = ?'
                    );
                    $save->execute([$tokenHash, $expiry, $user['id']]);

                    setcookie('remember_token', $rawToken, [
                        'expires' => time() + 30 * 24 * 60 * 60,
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } else {
                    clearRememberCookie();
                    clearRememberToken($pdo, (int) $user['id']);
                }

                header('Location: ' . sh_home_for_role($user['role']));
                exit;
            }
        }
    }
}

sh_public_header('Login', 'Login', 'login.php');
?>
    <div class="container page-container">
        <section class="card auth-card">
            <h2>Welcome back</h2>
            <p>Log in to your StudentHub account.</p>

            <?php if ($message !== ''): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>" role="alert">
                    <?= sh_e($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= sh_e($_SESSION['csrf_token']) ?>">

                <div class="field-group">
                    <label for="email">Email or Username</label>
                    <input type="text" id="email" name="email" placeholder="you@charusat.edu.in or username"
                           autocomplete="username" required>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password"
                           autocomplete="current-password" required>
                </div>

                <label>
                    <input type="checkbox" name="remember" value="1">Remember me for 30 days
                </label>

                <button type="submit" class="btn">Login</button>
            </form>

            <p style="margin-top:1rem;">No account yet? <a href="practical9.php">Register here</a></p>
        </section>
    </div>
<?php sh_public_footer(); ?>
