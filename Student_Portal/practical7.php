<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$dataDir = __DIR__ . '/data';
$csvFile = $dataDir . '/contact_submissions.csv';
if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
    http_response_code(500);
    exit('Unable to create the data directory.');
}
$message = '';
$messageType = '';
$errors = [];
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Your form session is invalid. Refresh the page and try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $messageText = trim((string)($_POST['message'] ?? ''));
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must contain 2–100 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $errors[] = 'Enter a valid email address.';
        if ($subject === '' || mb_strlen($subject) > 120) $errors[] = 'Subject is required and must not exceed 120 characters.';
        if ($messageText === '' || mb_strlen($messageText) < 10 || mb_strlen($messageText) > 2000) $errors[] = 'Message must contain 10–2000 characters.';
        if (!$errors) {
            $handle = fopen($csvFile, 'c+');
            if ($handle === false) {
                $errors[] = 'Unable to open the storage file. Check folder permissions.';
            } elseif (!flock($handle, LOCK_EX)) {
                fclose($handle);
                $errors[] = 'Unable to lock the storage file. Please try again.';
            } else {
                fseek($handle, 0, SEEK_END);
                if (ftell($handle) === 0) {
                    fputcsv($handle, ['ID', 'Name', 'Email', 'Subject', 'Message', 'Submitted At']);
                }
                // Keep spreadsheet applications from interpreting user data as formulas.
                $safeCsv = static function ($value) {
                    return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
                };
                $written = fputcsv($handle, [bin2hex(random_bytes(8)), $safeCsv($name), $safeCsv($email), $safeCsv($subject), $safeCsv($messageText), date('Y-m-d H:i:s')]);
                fflush($handle);
                flock($handle, LOCK_UN);
                fclose($handle);
                if ($written !== false) {
                    $message = 'Your message has been submitted and saved successfully.';
                    $messageType = 'success';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } else $errors[] = 'The message could not be saved. Please try again.';
            }
        }
    }
    if ($errors) { $message = implode(' ', $errors); $messageType = 'error'; }
}
$records = [];
if (is_readable($csvFile) && ($handle = fopen($csvFile, 'r')) !== false) {
    if (flock($handle, LOCK_SH)) {
        while (($row = fgetcsv($handle)) !== false) $records[] = $row;
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>try{document.documentElement.setAttribute('data-theme',localStorage.getItem('charusat-theme')==='dark'?'dark':'light')}catch(e){document.documentElement.setAttribute('data-theme','light')}</script>
<title>Practical 7 | StudentHub Portal</title><link rel="stylesheet" href="css/style.css">
<style>
.p7-layout{max-width:1000px;margin:2rem auto;padding:0 1rem}.p7-card{padding:1.5rem;margin-bottom:1.5rem}.p7-form{display:grid;gap:1rem}.p7-form label{display:block;font-weight:600;margin-bottom:.35rem}.p7-form input,.p7-form textarea{width:100%;padding:.75rem;border:1px solid var(--border-color,#bbb);border-radius:8px;font:inherit}.p7-form textarea{min-height:130px;resize:vertical}.p7-alert{padding:1rem;border-radius:8px;margin:1rem 0}.p7-success{background:#d9f7e5;color:#14532d}.p7-error{background:#fee2e2;color:#991b1b}.p7-table-wrap{overflow-x:auto}.p7-table{width:100%;border-collapse:collapse}.p7-table th,.p7-table td{border:1px solid #bbb;padding:.7rem;text-align:left;overflow-wrap:anywhere}.p7-table th{background:#174ea6;color:white}.p7-note{font-size:.95rem;opacity:.85}
</style></head>
<body>
<header><h1>StudentHub Portal</h1><div class="nav-controls"><button class="theme-toggle" type="button" aria-label="Switch theme">Dark Theme</button><button class="hamburger-menu" type="button" aria-expanded="false" aria-label="Open navigation menu" aria-controls="main-nav">☰</button></div></header>
<nav id="main-nav"><div class="nav-wrap"><ul>
<li><a href="index.html">Home</a></li><li><a href="about.html">About</a></li><li><a href="register.html">Register</a></li><li><a href="login.html">Login</a></li><li><a href="dashboard.html">Dashboard</a></li><li><a href="events.html">Events</a></li><li><a href="students.html">Students</a></li><li><a href="profile.html">Profile</a></li><li><a href="contact.html">Contact</a></li><li><a href="admin.html">Admin</a></li><li><a href="faq.html">FAQ</a></li><li><a href="feedback.html">Feedback</a></li><li><a href="practical7.php" aria-current="page">Practical 7</a></li>
</ul></div></nav>
<main class="p7-layout">
<section class="card p7-card"><h2>Practical 7: Contact Form Processing</h2><p>Submit a message using PHP POST handling, server-side validation, CSRF protection, and CSV file storage.</p>
<?php if ($message !== ''): ?><div class="p7-alert p7-<?=e($messageType)?>" role="status" aria-live="polite"><?=e($message)?></div><?php endif; ?>
<form class="p7-form" method="POST" action="practical7.php">
<input type="hidden" name="csrf_token" value="<?=e($_SESSION['csrf_token'])?>">
<div><label for="name">Full name</label><input id="name" name="name" type="text" minlength="2" maxlength="100" autocomplete="name" required></div>
<div><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required></div>
<div><label for="subject">Subject</label><input id="subject" name="subject" type="text" maxlength="120" required></div>
<div><label for="message">Message</label><textarea id="message" name="message" minlength="10" maxlength="2000" required></textarea></div>
<button class="btn" type="submit">Submit Message</button>
</form><p class="p7-note">Demo only: do not submit passwords or sensitive personal information.</p></section>
<section class="card p7-card"><h2>Stored Messages</h2><p>Records are loaded from the server-side CSV file.</p>
<?php if (count($records) > 1): ?><div class="p7-table-wrap"><table class="p7-table"><thead><tr><?php foreach ($records[0] as $heading): ?><th><?=e($heading)?></th><?php endforeach; ?></tr></thead><tbody><?php foreach (array_slice($records, 1) as $record): ?><tr><?php foreach ($record as $value): ?><td><?=e($value)?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>No messages have been submitted yet.</p><?php endif; ?>
</section>
</main><footer><p>&copy; 2026 CHARUSAT StudentHub Portal</p></footer>
<script src="js/theme.js" defer></script><script src="js/hamburger.js" defer></script>
</body></html>
