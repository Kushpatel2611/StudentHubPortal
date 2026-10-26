
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth.php';

function sendJson(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Check login and session timeout.
if (empty($_SESSION['user_id'])) {
    sendJson(401, ['success' => false, 'message' => 'Please log in first.']);
}

if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
    logoutUser($pdo);
    sendJson(401, ['success' => false, 'message' => 'Session expired. Please log in again.']);
}

$_SESSION['last_activity'] = time();

// Only admins can use this API.
if (($_SESSION['role'] ?? '') !== 'admin') {
    sendJson(403, ['success' => false, 'message' => 'Admin access required.']);
}

function validText($value, int $maxLength): bool
{
    return is_string($value)
        && trim($value) !== ''
        && mb_strlen(trim($value)) <= $maxLength;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET: list students and search.
    if ($method === 'GET') {
        $search = trim($_GET['search'] ?? '');

        $sql = "SELECT student_id, student_code, full_name, email, course
                FROM students
                WHERE status = 'active'";

        $params = [];

        if ($search !== '') {
            $sql .= " AND (full_name LIKE ?
                       OR email LIKE ?
                       OR course LIKE ?
                       OR student_code LIKE ?)";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term, $term];
        }

        $sql .= " ORDER BY student_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendJson(200, [
            'success' => true,
            'students' => $stmt->fetchAll()
        ]);
    }

    // Only POST is allowed for changes.
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        sendJson(400, ['success' => false, 'message' => 'Invalid JSON request.']);
    }

    // Validate CSRF token.
    $token = $data['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        sendJson(403, ['success' => false, 'message' => 'Invalid security token. Refresh the page.']);
    }

    $action = $data['action'] ?? '';

    // ADD student.
    if ($action === 'create') {
        $code = trim($data['student_code'] ?? '');
        $name = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $course = trim($data['course'] ?? '');

        if (!validText($code, 20) ||
            !validText($name, 100) ||
            !validText($course, 100) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            mb_strlen($email) > 254) {
            sendJson(422, ['success' => false, 'message' => 'Enter a valid student code, name, email, and course.']);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO students (student_code, full_name, email, course)
             VALUES (?, ?, ?, ?)"
        );

        try {
            $stmt->execute([$code, $name, $email, $course]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                sendJson(409, ['success' => false, 'message' => 'Student code or email already exists.']);
            }
            throw $e;
        }

        sendJson(201, ['success' => true, 'message' => 'Student added successfully.']);
    }

    // UPDATE student.
    if ($action === 'update') {
        $id = filter_var($data['student_id'] ?? null, FILTER_VALIDATE_INT);
        $code = trim($data['student_code'] ?? '');
        $name = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $course = trim($data['course'] ?? '');

        if (!$id || $id < 1 ||
            !validText($code, 20) ||
            !validText($name, 100) ||
            !validText($course, 100) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            mb_strlen($email) > 254) {
            sendJson(422, ['success' => false, 'message' => 'Enter valid student details.']);
        }

        $stmt = $pdo->prepare(
            "UPDATE students
             SET student_code = ?, full_name = ?, email = ?, course = ?
             WHERE student_id = ? AND status = 'active'"
        );

        try {
            $stmt->execute([$code, $name, $email, $course, $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                sendJson(409, ['success' => false, 'message' => 'Student code or email already exists.']);
            }
            throw $e;
        }

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare(
                "SELECT student_id FROM students
                 WHERE student_id = ? AND status = 'active'"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                sendJson(404, ['success' => false, 'message' => 'Student not found.']);
            }
        }

        sendJson(200, ['success' => true, 'message' => 'Student updated successfully.']);
    }

    // DELETE student using soft delete.
    if ($action === 'delete') {
        $id = filter_var($data['student_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            sendJson(422, ['success' => false, 'message' => 'Invalid student ID.']);
        }

        $stmt = $pdo->prepare(
            "UPDATE students SET status = 'deleted'
             WHERE student_id = ? AND status = 'active'"
        );
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            sendJson(404, ['success' => false, 'message' => 'Student not found.']);
        }

        sendJson(200, ['success' => true, 'message' => 'Student deleted successfully.']);
    }

    sendJson(400, ['success' => false, 'message' => 'Unknown action.']);

} catch (Throwable $e) {
    error_log('Student API error: ' . $e->getMessage());
    sendJson(500, ['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
