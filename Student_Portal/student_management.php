<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['student_csrf'])) {
    $_SESSION['student_csrf'] = bin2hex(random_bytes(32));
}

$mysqli = sh_connect_mysqli();

$message = '';
$messageType = 'success';

function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($message, $type = 'success') {
    $_SESSION['student_message'] = $message;
    $_SESSION['student_message_type'] = $type;
    header('Location: student_management.php');
    exit;
}

if (isset($_SESSION['student_message'])) {
    $message = $_SESSION['student_message'];
    $messageType = $_SESSION['student_message_type'] ?? 'success';

    unset($_SESSION['student_message'], $_SESSION['student_message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['student_csrf'], $token)) {
        redirectWithMessage('Invalid request. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $studentCode = trim($_POST['student_code'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course = trim($_POST['course'] ?? '');

        if (
            $studentCode === '' ||
            $fullName === '' ||
            $email === '' ||
            $course === ''
        ) {
            redirectWithMessage('Please fill in all fields.', 'error');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('Please enter a valid email address.', 'error');
        }

        $stmt = $mysqli->prepare(
            "INSERT INTO students (student_code, full_name, email, course)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param('ssss', $studentCode, $fullName, $email, $course);

        if ($stmt->execute()) {
            $stmt->close();
            sh_audit($pdo, 'student_added', 'Added student ' . $studentCode . ' (' . $fullName . ')');
            redirectWithMessage('Student added successfully.');
        }

        $errorNumber = $stmt->errno;
        $stmt->close();

        if ($errorNumber === 1062) {
            redirectWithMessage('Student code or email already exists.', 'error');
        }

        redirectWithMessage('Unable to add student.', 'error');
    }

    if ($action === 'edit') {
        $studentId = filter_var(
            $_POST['student_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $studentCode = trim($_POST['student_code'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course = trim($_POST['course'] ?? '');

        if (
            !$studentId ||
            $studentCode === '' ||
            $fullName === '' ||
            $email === '' ||
            $course === ''
        ) {
            redirectWithMessage('Please provide valid student details.', 'error');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('Please enter a valid email address.', 'error');
        }

        $stmt = $mysqli->prepare(
            "UPDATE students
             SET student_code = ?, full_name = ?, email = ?, course = ?
             WHERE student_id = ? AND status = 'active'"
        );

        $stmt->bind_param(
            'ssssi',
            $studentCode,
            $fullName,
            $email,
            $course,
            $studentId
        );

        if ($stmt->execute()) {
            $stmt->close();
            sh_audit($pdo, 'student_updated', 'Updated student ID ' . $studentId . ' (' . $studentCode . ')');
            redirectWithMessage('Student updated successfully.');
        }

        $errorNumber = $stmt->errno;
        $stmt->close();

        if ($errorNumber === 1062) {
            redirectWithMessage('Student code or email already exists.', 'error');
        }

        redirectWithMessage('Unable to update student.', 'error');
    }

    if ($action === 'delete' || $action === 'restore') {
        $studentId = filter_var(
            $_POST['student_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$studentId) {
            redirectWithMessage('Invalid student ID.', 'error');
        }

        if ($action === 'delete') {
            $stmt = $mysqli->prepare(
                "UPDATE students
                 SET status = 'deleted'
                 WHERE student_id = ? AND status = 'active'"
            );
        } else {
            $stmt = $mysqli->prepare(
                "UPDATE students
                 SET status = 'active'
                 WHERE student_id = ? AND status = 'deleted'"
            );
        }

        $stmt->bind_param('i', $studentId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();

            sh_audit($pdo, $action === 'delete' ? 'student_deleted' : 'student_restored', 'Student ID ' . $studentId);

            if ($action === 'delete') {
                redirectWithMessage('Student moved to deleted records.');
            }

            redirectWithMessage('Student restored successfully.');
        }

        $stmt->close();
        redirectWithMessage('No matching student was found.', 'error');
    }

    redirectWithMessage('Invalid action.', 'error');
}

$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = $_GET['status'] ?? 'active';

if (!in_array($statusFilter, ['active', 'deleted', 'all'], true)) {
    $statusFilter = 'active';
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR course LIKE ? OR student_code LIKE ?)';
    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}

if ($courseFilter !== '') {
    $where[] = 'course = ?';
    $params[] = $courseFilter;
    $types .= 's';
}

$whereSql = '';

if (count($where) > 0) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

$countSql = 'SELECT COUNT(*) AS total FROM students' . $whereSql;
$countStmt = $mysqli->prepare($countSql);

if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = (int)$countResult->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalStudents / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$listSql = "SELECT student_id, student_code, full_name, email, course,
                   status, created_at
            FROM students" . $whereSql . "
            ORDER BY student_id DESC
            LIMIT ? OFFSET ?";

$listStmt = $mysqli->prepare($listSql);

$listParams = $params;
$listTypes = $types . 'ii';

$listParams[] = $limit;
$listParams[] = $offset;

$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();

$result = $listStmt->get_result();

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$listStmt->close();

$courseResult = $mysqli->query(
    "SELECT DISTINCT course
     FROM students
     WHERE course <> ''
     ORDER BY course"
);

$courses = [];

while ($courseRow = $courseResult->fetch_assoc()) {
    $courses[] = $courseRow['course'];
}

$editStudent = null;

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId) {
        $editStmt = $mysqli->prepare(
            "SELECT student_id, student_code, full_name, email, course
             FROM students
             WHERE student_id = ? AND status = 'active'"
        );

        $editStmt->bind_param('i', $editId);
        $editStmt->execute();

        $editResult = $editStmt->get_result();
        $editStudent = $editResult->fetch_assoc();

        $editStmt->close();
    }
}

function pageUrl($pageNumber) {
    $query = $_GET;
    $query['page'] = $pageNumber;

    return '?' . http_build_query($query);
}
admin_shell_start('Students', 'students', 'Add, edit, search, delete and restore student records (server-side form version).');
admin_students_tabs('standard');
?>

    <?php if ($message !== ''): ?>
        <div class="message <?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= escape($message) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2><?= $editStudent ? 'Edit Student' : 'Add New Student' ?></h2>

        <form method="POST" action="student_management.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['student_csrf']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= $editStudent ? 'edit' : 'add' ?>"
            >

            <?php if ($editStudent): ?>
                <input
                    type="hidden"
                    name="student_id"
                    value="<?= (int)$editStudent['student_id'] ?>"
                >
            <?php endif; ?>

            <div class="form-grid">

                <div>
                    <label for="student_code">Student Code</label>
                    <input
                        id="student_code"
                        type="text"
                        name="student_code"
                        maxlength="20"
                        required
                        value="<?= escape($editStudent['student_code'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="full_name">Full Name</label>
                    <input
                        id="full_name"
                        type="text"
                        name="full_name"
                        maxlength="100"
                        required
                        value="<?= escape($editStudent['full_name'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        maxlength="254"
                        required
                        value="<?= escape($editStudent['email'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="course">Course</label>
                    <input
                        id="course"
                        type="text"
                        name="course"
                        maxlength="100"
                        required
                        list="course-options"
                        value="<?= escape($editStudent['course'] ?? '') ?>"
                    >

                    <datalist id="course-options">
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= escape($course) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

            </div>

            <br>

            <button type="submit" class="green">
                <?= $editStudent ? 'Update Student' : 'Add Student' ?>
            </button>

            <?php if ($editStudent): ?>
                <a class="button gray" href="student_management.php">
                    Cancel Edit
                </a>
            <?php endif; ?>
        </form>
    </section>

    <section class="card">
        <h2>Search and Filter Students</h2>

        <form method="GET" action="student_management.php">
            <div class="search-grid">

                <div>
                    <label for="search">Search</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        placeholder="Name, email, course or student code"
                        value="<?= escape($search) ?>"
                    >
                </div>

                <div>
                    <label for="course-filter">Course</label>
                    <select id="course-filter" name="course">
                        <option value="">All Courses</option>

                        <?php foreach ($courses as $course): ?>
                            <option
                                value="<?= escape($course) ?>"
                                <?= $courseFilter === $course ? 'selected' : '' ?>
                            >
                                <?= escape($course) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Record Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>
                            Deleted
                        </option>

                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                            All Records
                        </option>
                    </select>
                </div>

                <div>
                    <button type="submit">Search</button>
                    <a class="button gray" href="student_management.php">Reset</a>
                </div>

            </div>
        </form>
    </section>

    <section class="card">
        <h2>Student Records</h2>

        <p class="muted">
            Matching records: <?= $totalStudents ?> |
            Page <?= $page ?> of <?= $totalPages ?>
        </p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Code</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($students) === 0): ?>
                        <tr>
                            <td colspan="8">No students found.</td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= (int)$student['student_id'] ?></td>
                                <td><?= escape($student['student_code']) ?></td>
                                <td><?= escape($student['full_name']) ?></td>
                                <td><?= escape($student['email']) ?></td>
                                <td><?= escape($student['course']) ?></td>

                                <td>
                                    <span class="status <?= $student['status'] === 'active' ? 'active-status' : 'deleted-status' ?>">
                                        <?= escape(ucfirst($student['status'])) ?>
                                    </span>
                                </td>

                                <td><?= escape($student['created_at']) ?></td>

                                <td>
                                    <div class="actions">

                                        <?php if ($student['status'] === 'active'): ?>

                                            <a
                                                class="button"
                                                href="?<?= escape(http_build_query(array_merge($_GET, ['edit' => $student['student_id']]))) ?>"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="student_management.php"
                                                onsubmit="return confirm('Move this student to deleted records?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['student_csrf']) ?>"
                                                >

                                                <input type="hidden" name="action" value="delete">

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?= (int)$student['student_id'] ?>"
                                                >

                                                <button type="submit" class="red">Delete</button>
                                            </form>

                                        <?php else: ?>

                                            <form
                                                method="POST"
                                                action="student_management.php"
                                                onsubmit="return confirm('Restore this student?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escape($_SESSION['student_csrf']) ?>"
                                                >

                                                <input type="hidden" name="action" value="restore">

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?= (int)$student['student_id'] ?>"
                                                >

                                                <button type="submit" class="green">Restore</button>
                                            </form>

                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <?php if ($page > 1): ?>
                    <a href="<?= escape(pageUrl($page - 1)) ?>">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a
                        class="<?= $i === $page ? 'current' : '' ?>"
                        href="<?= escape(pageUrl($i)) ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= escape(pageUrl($page + 1)) ?>">Next</a>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </section>

<?php admin_shell_end(); ?>


<?php
$mysqli->close();
?>