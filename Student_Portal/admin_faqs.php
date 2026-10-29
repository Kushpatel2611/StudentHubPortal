<?php
/**
 * Admin > FAQs: add, edit and delete the questions shown on faq.html.
 * They are stored in data/faqs.json, the same file the public FAQ page loads.
 */
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/storage.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['faq_csrf'])) {
    $_SESSION['faq_csrf'] = bin2hex(random_bytes(32));
}

function faqFlash(string $message, string $type = 'success'): void
{
    $_SESSION['faq_message'] = $message;
    $_SESSION['faq_message_type'] = $type;
    header('Location: admin_faqs.php');
    exit;
}

$message = $_SESSION['faq_message'] ?? '';
$messageType = $_SESSION['faq_message_type'] ?? 'success';
unset($_SESSION['faq_message'], $_SESSION['faq_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['faq_csrf'], $token)) {
        faqFlash('Invalid request. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';
    $faqs = sh_json_read('faqs.json');

    if ($action === 'add' || $action === 'edit') {
        $id = filter_var($_POST['faq_id'] ?? '', FILTER_VALIDATE_INT);
        $category = trim((string) ($_POST['category'] ?? ''));
        $question = trim((string) ($_POST['question'] ?? ''));
        $answer = trim((string) ($_POST['answer'] ?? ''));

        if ($category === '' || $question === '' || $answer === '') {
            faqFlash('Please fill in the category, question and answer.', 'error');
        }
        if (mb_strlen($category) > 60 || mb_strlen($question) > 250 || mb_strlen($answer) > 2000) {
            faqFlash('Category (60), question (250) or answer (2000) is too long.', 'error');
        }

        if ($action === 'add') {
            $maxId = 0;
            foreach ($faqs as $f) {
                $maxId = max($maxId, (int) ($f['id'] ?? 0));
            }
            $faqs[] = ['id' => $maxId + 1, 'category' => $category, 'question' => $question, 'answer' => $answer];
            $okMessage = 'FAQ added.';
            $logAction = 'faq_added';
            $logDetails = 'Added FAQ: ' . $question;
        } else {
            $found = false;
            foreach ($faqs as &$f) {
                if ((int) ($f['id'] ?? 0) === $id) {
                    $f = ['id' => $id, 'category' => $category, 'question' => $question, 'answer' => $answer];
                    $found = true;
                }
            }
            unset($f);
            if (!$id || !$found) {
                faqFlash('FAQ not found.', 'error');
            }
            $okMessage = 'FAQ updated.';
            $logAction = 'faq_updated';
            $logDetails = 'Updated FAQ ID ' . $id;
        }

        if (!sh_json_write('faqs.json', $faqs)) {
            faqFlash('Could not save data/faqs.json. Check that the data folder is writable.', 'error');
        }
        sh_audit($pdo, $logAction, $logDetails);
        faqFlash($okMessage);
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['faq_id'] ?? '', FILTER_VALIDATE_INT);
        $before = count($faqs);
        $faqs = array_values(array_filter($faqs, static fn($f) => (int) ($f['id'] ?? 0) !== $id));
        if (!$id || count($faqs) === $before) {
            faqFlash('FAQ not found.', 'error');
        }
        if (!sh_json_write('faqs.json', $faqs)) {
            faqFlash('Could not save data/faqs.json. Check that the data folder is writable.', 'error');
        }
        sh_audit($pdo, 'faq_deleted', 'Deleted FAQ ID ' . $id);
        faqFlash('FAQ deleted.');
    }

    faqFlash('Invalid action.', 'error');
}

$faqs = sh_json_read('faqs.json');
$categories = array_values(array_unique(array_map(static fn($f) => (string) ($f['category'] ?? ''), $faqs)));
sort($categories);

$editFaq = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    foreach ($faqs as $f) {
        if ((int) ($f['id'] ?? 0) === $editId) {
            $editFaq = $f;
        }
    }
}

admin_shell_start('FAQs', 'faqs', 'These questions appear on the public FAQ page.');
?>
<?php if ($message !== ''): ?>
    <div class="message <?= $messageType === 'error' ? 'error' : 'success' ?>"><?= h($message) ?></div>
<?php endif; ?>

<section class="card">
    <h2><?= $editFaq ? 'Edit FAQ' : 'Add a FAQ' ?></h2>
    <form method="POST" action="admin_faqs.php">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['faq_csrf']) ?>">
        <input type="hidden" name="action" value="<?= $editFaq ? 'edit' : 'add' ?>">
        <?php if ($editFaq): ?><input type="hidden" name="faq_id" value="<?= (int) $editFaq['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div>
                <label for="category">Category *</label>
                <input id="category" name="category" maxlength="60" required list="faq-categories"
                       value="<?= h($editFaq['category'] ?? '') ?>">
                <datalist id="faq-categories">
                    <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label for="question">Question *</label>
                <input id="question" name="question" maxlength="250" required value="<?= h($editFaq['question'] ?? '') ?>">
            </div>
            <div class="full">
                <label for="answer">Answer *</label>
                <textarea id="answer" name="answer" maxlength="2000" required><?= h($editFaq['answer'] ?? '') ?></textarea>
            </div>
        </div>
        <p>
            <button type="submit" class="green"><?= $editFaq ? 'Update FAQ' : 'Add FAQ' ?></button>
            <?php if ($editFaq): ?><a class="button gray" href="admin_faqs.php">Cancel</a><?php endif; ?>
        </p>
    </form>
</section>

<section class="card">
    <h2>All FAQs</h2>
    <p class="muted">Total: <?= count($faqs) ?> &middot; <a href="faq.html" target="_blank" rel="noopener">Open the public FAQ page</a></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Category</th><th>Question</th><th>Answer</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$faqs): ?>
                <tr><td colspan="5">No FAQs yet.</td></tr>
            <?php else: foreach ($faqs as $f): ?>
                <tr>
                    <td><?= (int) ($f['id'] ?? 0) ?></td>
                    <td><?= h($f['category'] ?? '') ?></td>
                    <td><?= h($f['question'] ?? '') ?></td>
                    <td><?= h(mb_strimwidth((string) ($f['answer'] ?? ''), 0, 120, '...')) ?></td>
                    <td>
                        <div class="actions">
                            <a class="button" href="?edit=<?= (int) ($f['id'] ?? 0) ?>">Edit</a>
                            <form method="POST" action="admin_faqs.php" onsubmit="return confirm('Delete this FAQ?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['faq_csrf']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="faq_id" value="<?= (int) ($f['id'] ?? 0) ?>">
                                <button type="submit" class="red">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_shell_end(); ?>
