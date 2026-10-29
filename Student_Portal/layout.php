<?php
/**
 * Shared layout helpers for the PHP pages (login, register, dashboard, admin).
 * Public pages reuse css/style.css so they match the static pages.
 * Admin pages share ONE sidebar + css/admin.css (admin_shell_start / admin_shell_end).
 */

function sh_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sh_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Where a signed-in user belongs after login. */
function sh_home_for_role(?string $role): string
{
    return $role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php';
}

function sh_public_header(string $title, string $heading, string $active = ''): void
{
    $loggedIn = !empty($_SESSION['user_id']);
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';

    $items = [
        'index.html'    => 'Home',
        'about.html'    => 'About',
        'events.html'   => 'Events',
        'students.html' => 'Students',
        'profile.php'   => 'Profile',
        'contact.html'  => 'Contact',
        'faq.html'      => 'FAQ',
        'feedback.html' => 'Feedback',
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('charusat-theme');
                document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }());
    </script>
    <title><?= sh_e($title) ?> | StudentHub Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
    <div class="nav-controls">
        <button class="theme-toggle" type="button" aria-label="Switch theme">Dark Theme</button>
        <button class="hamburger-menu" type="button" aria-expanded="false" aria-label="Open navigation menu" aria-controls="main-nav">&#9776;</button>
    </div>
    <h1><?= sh_e($heading) ?></h1>
</header>
<nav id="main-nav">
    <div class="nav-wrap">
        <ul>
            <?php foreach ($items as $href => $label): ?>
                <li><a href="<?= sh_e($href) ?>"<?= $active === $href ? ' aria-current="page"' : '' ?>><?= sh_e($label) ?></a></li>
            <?php endforeach; ?>
            <?php if (!$loggedIn): ?>
                <li><a href="practical9.php"<?= $active === 'practical9.php' ? ' aria-current="page"' : '' ?>>Register</a></li>
                <li><a href="login.php"<?= $active === 'login.php' ? ' aria-current="page"' : '' ?>>Login</a></li>
            <?php elseif ($isAdmin): ?>
                <li><a href="admin_dashboard.php">Admin Panel</a></li>
                <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="dashboard.php"<?= $active === 'dashboard.php' ? ' aria-current="page"' : '' ?>>Dashboard</a></li>
                <li><a href="logout.php">Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<main>
<?php
}

function sh_public_footer(array $extraScripts = []): void
{
    ?>
</main>
<footer><p>&copy; 2026 StudentHub Portal | CHARUSAT</p></footer>
<script src="js/theme.js" defer></script>
<script src="js/hamburger.js" defer></script>
<?php foreach ($extraScripts as $src): ?>
<script src="<?= sh_e($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/**
 * Records an admin action in audit_logs (never breaks the page if logging fails).
 */
function sh_audit(PDO $pdo, string $action, string $details): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            $action,
            mb_substr($details, 0, 1000),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('StudentHub audit log failed: ' . $e->getMessage());
    }
}

/**
 * Admin panel shell: ONE sidebar, shared by every admin page.
 * $active: overview | students | events | faqs | messages | users | audit
 * Close the page with admin_shell_end().
 */
function admin_shell_start(string $title, string $active, string $subtitle = ''): void
{
    $groups = [
        'Dashboard' => [
            'overview' => ['admin_dashboard.php', 'Overview'],
        ],
        'Academic' => [
            'students' => ['student_management.php', 'Students'],
            'events'   => ['event_management.php', 'Events'],
        ],
        'User Management' => [
            'users'    => ['admin_users.php', 'Users'],
        ],
        'Communications' => [
            'messages' => ['admin_messages.php', 'Messages & Feedback'],
            'faqs'     => ['admin_faqs.php', 'FAQ Management'],
        ],
        'System' => [
            'audit'    => ['admin_audit.php', 'Audit Log'],
        ],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('charusat-theme');
                document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }());
    </script>
    <title><?= sh_e($title) ?> | StudentHub Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell" id="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <div class="admin-brand"><strong>StudentHub</strong><span>Admin panel</span></div>
        <div class="admin-menu">
            <?php foreach ($groups as $groupLabel => $links): ?>
                <h4><?= $groupLabel ?></h4>
                <?php foreach ($links as $key => [$href, $label]): ?>
                    <a href="<?= sh_e($href) ?>"<?= $key === $active ? ' class="is-active" aria-current="page"' : '' ?>><?= sh_e($label) ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <div class="admin-sidebar-foot">
            <a href="index.html">&larr; View website</a>
            <a href="logout.php">Logout</a>
        </div>
    </aside>
    <div class="admin-main">
        <div class="admin-topbar">
            <button type="button" class="icon-btn admin-menu-toggle" id="admin-menu-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Toggle menu">&#9776;</button>
            <h1><?= sh_e($title) ?></h1>
            <span class="who">Signed in as <strong><?= sh_e($_SESSION['username'] ?? 'admin') ?></strong></span>
            <button type="button" class="icon-btn" id="admin-theme-toggle" aria-label="Switch theme">Theme</button>
        </div>
        <main class="admin-content">
<?php if ($subtitle !== ''): ?>
            <p class="page-lead"><?= sh_e($subtitle) ?></p>
<?php endif; ?>
<?php
}

function admin_shell_end(): void
{
    ?>
        </main>
        <div class="admin-footer">StudentHub Portal &mdash; Admin panel</div>
    </div>
</div>
<script>
(function () {
    var shell = document.getElementById('admin-shell');
    var toggle = document.getElementById('admin-menu-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var open = shell.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }
    var theme = document.getElementById('admin-theme-toggle');
    if (theme) {
        theme.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('charusat-theme', next); } catch (e) {}
        });
    }
}());
</script>
</body>
</html>
<?php
}

/** Tabs shown above the two Students screens (both live under one sidebar item). */
function admin_students_tabs(string $current): void
{
    $tabs = [
        'standard' => ['student_management.php', 'Standard view (forms, filters, restore)'],
        'ajax'     => ['student_ajax.php', 'Quick edit (AJAX, no page reload)'],
    ];
    echo '<div class="admin-tabs">';
    foreach ($tabs as $key => [$href, $label]) {
        echo '<a href="' . sh_e($href) . '"' . ($key === $current ? ' class="is-active" aria-current="page"' : '') . '>' . sh_e($label) . '</a>';
    }
    echo "</div>\n";
}
