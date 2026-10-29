<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Not signed in: nothing to log out of.
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// A plain link (GET) shows a confirmation page; only a POST with a valid
// CSRF token actually logs out, so other sites cannot force a logout.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) ||
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid logout request. Refresh the page and try again.');
    }

    logoutUser($pdo);
    header('Location: login.php?logged_out=1');
    exit;
}

$home = sh_home_for_role($_SESSION['role'] ?? null);

sh_public_header('Logout', 'Log out');
?>
    <div class="container page-container">
        <section class="card" style="max-width:480px;margin:2rem auto;text-align:center;">
            <h2>Log out of StudentHub?</h2>
            <p>You are signed in as <strong><?= sh_e($_SESSION['username'] ?? '') ?></strong>.</p>
            <form method="POST" action="logout.php" style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= sh_e(sh_csrf_token()) ?>">
                <button type="submit" class="btn">Yes, log me out</button>
                <a class="btn btn-secondary" href="<?= sh_e($home) ?>">Cancel</a>
            </form>
        </section>
    </div>
<?php sh_public_footer(); ?>
