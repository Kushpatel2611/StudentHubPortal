<?php
/**
 * Public endpoint used by contact.html. Validates the message and stores it in
 * data/contact_submissions.csv (the same file practical7.php uses).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../storage.php';

function reply(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reply(405, ['success' => false, 'message' => 'Method not allowed.']);
}
sh_same_origin_or_fail();

// Honeypot: real visitors never fill this hidden field.
if (!empty($_POST['website'])) {
    reply(200, ['success' => true, 'message' => 'Thank you. Your message has been sent.']);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
if ($subject === '') {
    $subject = 'Website contact form';
}

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Name must contain 2-100 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = 'Enter a valid email address.';
}
if (mb_strlen($subject) > 120) {
    $errors[] = 'Subject must not exceed 120 characters.';
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
    $errors[] = 'Message must contain 10-2000 characters.';
}
if ($errors) {
    reply(422, ['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
}

$saved = sh_csv_append(
    'contact_submissions.csv',
    ['ID', 'Name', 'Email', 'Subject', 'Message', 'Submitted At'],
    [bin2hex(random_bytes(8)), sh_csv_safe($name), sh_csv_safe($email), sh_csv_safe($subject), sh_csv_safe($message), date('Y-m-d H:i:s')]
);

if (!$saved) {
    error_log('StudentHub: could not write data/contact_submissions.csv');
    reply(500, ['success' => false, 'message' => 'Your message could not be saved. Please try again later.']);
}

reply(201, ['success' => true, 'message' => 'Thank you. Your message has been sent.']);
