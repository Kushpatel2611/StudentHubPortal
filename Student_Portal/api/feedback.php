<?php
/**
 * Public endpoint used by feedback.html. Stores feedback in data/feedback_submissions.csv.
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

if (!empty($_POST['website'])) {
    reply(200, ['success' => true, 'message' => 'Thank you for your feedback.']);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$rating = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT);
$feedback = trim((string) ($_POST['message'] ?? ''));

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Name must contain 2-100 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = 'Enter a valid email address.';
}
if ($rating === false || $rating < 1 || $rating > 5) {
    $errors[] = 'Choose a rating.';
}
if (mb_strlen($feedback) < 5 || mb_strlen($feedback) > 2000) {
    $errors[] = 'Feedback must contain 5-2000 characters.';
}
if ($errors) {
    reply(422, ['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
}

$saved = sh_csv_append(
    'feedback_submissions.csv',
    ['ID', 'Name', 'Email', 'Rating', 'Feedback', 'Submitted At'],
    [bin2hex(random_bytes(8)), sh_csv_safe($name), sh_csv_safe($email), (string) $rating, sh_csv_safe($feedback), date('Y-m-d H:i:s')]
);

if (!$saved) {
    error_log('StudentHub: could not write data/feedback_submissions.csv');
    reply(500, ['success' => false, 'message' => 'Your feedback could not be saved. Please try again later.']);
}

reply(201, ['success' => true, 'message' => 'Thank you for your feedback.']);
