<?php
/**
 * Admin > Users: list/search accounts, activate/deactivate, promote/demote.
 * (Moved out of admin_dashboard.php so the overview stays short.)
 */
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? 'toggle_status';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid security token. Please refresh the page.';
    } elseif (!$userId || $userId < 1) {
        $error = 'Invalid user ID.';
    } elseif (!in_array($action, ['toggle_status', 'toggle_role'], true)) {
        $error = 'Unknown action.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id, username, role, status FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $target = $stmt->fetch();

            if (!$target) {
                throw new RuntimeException('User not found.');
            }
            if ((int)$target['id'] === (int)($_SESSION['user_id'] ?? 0)) {
                throw new RuntimeException('You cannot change your own account here.');
            }

            if ($action === 'toggle_status') {
                $new = $target['status'] === 'active' ? 'inactive' : 'active';
                $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $userId]);
                $logAction = 'user_status_changed';
                $details = 'Changed status of ' . $target['username'] . ' (ID ' . $userId . ') to ' . $new;
            } else {
                $new = $target['role'] === 'admin' ? 'student' : 'admin';
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$new, $userId]);
                $logAction = 'user_role_changed';
                $details = 'Changed role of ' . $target['username'] . ' (ID ' . $userId . ') to ' . $new;
            }

            sh_audit($pdo, $logAction, $details);
            $pdo->commit();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: admin_users.php?updated=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin users error: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to update the user.';
        }
    }
}

if (isset($_GET['updated'])) {
    $message = 'User updated and audit log saved.';
}

$search = trim((string)($_GET['search'] ?? ''));
$roleFilter = $_GET['role'] ?? '';
if (!in_array($roleFilter, ['', 'student', 'admin'], true)) {
    $roleFilter = '';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(username LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($roleFilter !== '') {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$perPage = 15;
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = min(max(1, $page ?: 1), $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    'SELECT id, username, email, role, status, last_login, created_at FROM users'
    . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
);
$listStmt->execute($params);
$users = $listStmt->fetchAll();

function userPageUrl(int $p): string {
    $q = $_GET;
    unset($q['updated']);
    $q['page'] = $p;
    return '?' . http_build_query($q);
}

admin_shell_start('Users', 'users', 'Activate or deactivate accounts and choose who is an admin. Every change is written to the audit log.');
?>
<?php if ($message): ?><div class="message success"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>

<section class="card">
    <h2>Search users</h2>
    <form method="GET" action="admin_users.php">
        <div class="search-grid">
            <div>
                <label for="search">Username or email</label>
                <input id="search" name="search" value="<?= h($search) ?>" placeholder="Search users">
            </div>
            <div>
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="">All roles</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div></div>
            <div>
                <button type="submit">Search</button>
                <a class="button gray" href="admin_users.php">Reset</a>
            </div>
        </div>
    </form>
</section>

<section class="card">
    <h2>User accounts</h2>
    <p class="muted">Matching users: <?= $total ?> | Page <?= $page ?> of <?= $totalPages ?></p>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Joined</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="7">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): $isSelf = (int)$user['id'] === (int)($_SESSION['user_id'] ?? 0); ?>
                <tr>
                    <td><?= h($user['username']) ?></td>
                    <td><?= h($user['email']) ?></td>
                    <td><?= h(ucfirst($user['role'])) ?></td>
                    <td><span class="status <?= $user['status'] === 'active' ? '' : 'closed' ?>"><?= h(ucfirst($user['status'])) ?></span></td>
                    <td><?= h($user['last_login'] ?? 'Never') ?></td>
                    <td><?= h($user['created_at']) ?></td>
                    <td>
                    <?php if ($isSelf): ?>
                        <span class="muted">Current account</span>
                    <?php else: ?>
                        <div class="actions">
                            <form method="post" onsubmit="return confirm('Change this user\'s status?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit" class="<?= $user['status'] === 'active' ? 'red' : 'green' ?>">
                                    <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <form method="post" onsubmit="return confirm('Change this user\'s role?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="action" value="toggle_role">
                                <button type="submit" class="gray">
                                    <?= $user['role'] === 'admin' ? 'Make student' : 'Make admin' ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="<?= h(userPageUrl($page - 1)) ?>">Previous</a><?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= $i === $page ? 'current' : '' ?>" href="<?= h(userPageUrl($i)) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="<?= h(userPageUrl($page + 1)) ?>">Next</a><?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php admin_shell_end(); ?>
