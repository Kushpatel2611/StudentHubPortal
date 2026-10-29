<?php
/**
 * Admin > Messages: read (and delete) what visitors sent through the
 * Contact form and the Feedback form.
 */
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/storage.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['msg_csrf'])) {
    $_SESSION['msg_csrf'] = bin2hex(random_bytes(32));
}

$boxes = [
    'contact'  => ['file' => 'contact_submissions.csv',  'label' => 'Contact messages'],
    'feedback' => ['file' => 'feedback_submissions.csv', 'label' => 'Feedback'],
];
$box = $_GET['box'] ?? 'contact';
if (!isset($boxes[$box])) {
    $box = 'contact';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postBox = $_POST['box'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    $id = (string) ($_POST['id'] ?? '');

    if (!is_string($token) || !hash_equals($_SESSION['msg_csrf'], $token)) {
        $_SESSION['msg_flash'] = ['error', 'Invalid request. Please try again.'];
    } elseif (!isset($boxes[$postBox]) || !preg_match('/^[a-f0-9]{16}$/', $id)) {
        $_SESSION['msg_flash'] = ['error', 'Invalid message.'];
    } elseif (sh_csv_delete($boxes[$postBox]['file'], $id)) {
        sh_audit($pdo, 'message_deleted', 'Deleted ' . $postBox . ' message ' . $id);
        $_SESSION['msg_flash'] = ['success', 'Message deleted.'];
    } else {
        $_SESSION['msg_flash'] = ['error', 'Message not found.'];
    }
    header('Location: admin_messages.php?box=' . urlencode($postBox !== '' && isset($boxes[$postBox]) ? $postBox : 'contact'));
    exit;
}

$flash = $_SESSION['msg_flash'] ?? null;
unset($_SESSION['msg_flash']);

[$headers, $rows] = sh_csv_read($boxes[$box]['file']);
$rows = array_reverse($rows); // newest first
$counts = [];
foreach ($boxes as $key => $info) {
    $counts[$key] = count(sh_csv_read($info['file'])[1]);
}

admin_shell_start('Messages', 'messages', 'Messages sent from the public Contact and Feedback pages.');
?>
<?php if ($flash): ?>
    <div class="message <?= $flash[0] === 'error' ? 'error' : 'success' ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="admin-tabs">
    <?php foreach ($boxes as $key => $info): ?>
        <a href="?box=<?= h($key) ?>"<?= $key === $box ? ' class="is-active" aria-current="page"' : '' ?>><?= h($info['label']) ?> (<?= (int) $counts[$key] ?>)</a>
    <?php endforeach; ?>
</div>

<section class="card">
    <h2><?= h($boxes[$box]['label']) ?></h2>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <?php foreach (array_slice($headers, 1) as $heading): ?><th><?= h($heading) ?></th><?php endforeach; ?>
                <?php if (!$headers): ?><th>Message</th><?php endif; ?>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= max(2, count($headers)) ?>">Nothing here yet.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <?php foreach (array_slice($row, 1) as $value): ?><td><?= h($value) ?></td><?php endforeach; ?>
                    <td>
                        <form method="POST" action="admin_messages.php" onsubmit="return confirm('Delete this message?');">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['msg_csrf']) ?>">
                            <input type="hidden" name="box" value="<?= h($box) ?>">
                            <input type="hidden" name="id" value="<?= h($row[0] ?? '') ?>">
                            <button type="submit" class="red">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_shell_end(); ?>
