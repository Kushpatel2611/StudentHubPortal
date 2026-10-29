<?php
/** Admin > Audit log: every admin action recorded by the panel. */
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$perPage = 25;
$total = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = min(max(1, $page ?: 1), $totalPages);
$offset = ($page - 1) * $perPage;

$logs = $pdo->query(
    'SELECT a.action, a.details, a.ip_address, a.created_at, u.username
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
)->fetchAll();

admin_shell_start('Audit log', 'audit', 'A record of changes made from the admin panel.');
?>
<section class="card">
    <h2>Recent activity</h2>
    <p class="muted">Total entries: <?= $total ?> | Page <?= $page ?> of <?= $totalPages ?></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="5">No audit logs recorded yet. Changing a student, event, FAQ or user creates the first entry.</td></tr>
            <?php else: foreach ($logs as $log): ?>
                <tr>
                    <td><?= h($log['created_at']) ?></td>
                    <td><?= h($log['username'] ?? 'User unavailable') ?></td>
                    <td><?= h($log['action']) ?></td>
                    <td><?= h($log['details'] ?? '') ?></td>
                    <td><?= h($log['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= $i === $page ? 'current' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php admin_shell_end(); ?>
