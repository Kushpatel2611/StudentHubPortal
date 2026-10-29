<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['event_csrf'])) {
    $_SESSION['event_csrf'] = bin2hex(random_bytes(32));
}

$mysqli = sh_connect_mysqli();

$uploadDir = __DIR__ . '/uploads/event_posters/';
$uploadUrl = 'uploads/event_posters/';
$maxFileSize = 2 * 1024 * 1024;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function flashMessage($message, $type = 'success') {
    $_SESSION['event_message'] = $message;
    $_SESSION['event_message_type'] = $type;
    header('Location: event_management.php');
    exit;
}

function uploadPoster($field, $uploadDir, $maxFileSize) {
    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return ['name' => null, 'error' => null];
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['name' => null, 'error' => 'Poster upload failed.'];
    }

    if ($file['size'] > $maxFileSize) {
        return ['name' => null, 'error' => 'Poster must be 2 MB or smaller.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['name' => null, 'error' => 'Invalid uploaded file.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        return [
            'name' => null,
            'error' => 'Only JPG, PNG, and WEBP posters are allowed.'
        ];
    }

    $imageInfo = getimagesize($file['tmp_name']);

    if ($imageInfo === false || $imageInfo['mime'] !== $mime) {
        return ['name' => null, 'error' => 'The uploaded file is not a valid image.'];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['name' => null, 'error' => 'Upload directory could not be created.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['name' => null, 'error' => 'Could not save the poster.'];
    }

    return ['name' => $filename, 'error' => null];
}

function removePoster($filename, $uploadDir) {
    if (
        $filename !== null &&
        $filename !== '' &&
        basename($filename) === $filename
    ) {
        $path = $uploadDir . $filename;

        if (is_file($path)) {
            unlink($path);
        }
    }
}

$message = $_SESSION['event_message'] ?? '';
$messageType = $_SESSION['event_message_type'] ?? 'success';

unset($_SESSION['event_message'], $_SESSION['event_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['event_csrf'], $token)) {
        flashMessage('Invalid request. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = filter_var($_POST['event_id'] ?? '', FILTER_VALIDATE_INT);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate = trim($_POST['event_date'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $capacity = filter_var($_POST['capacity'] ?? '', FILTER_VALIDATE_INT);
        $category = trim($_POST['category'] ?? '');
        $status = $_POST['status'] ?? 'open';

        if (
            $title === '' ||
            $eventDate === '' ||
            $venue === '' ||
            $category === '' ||
            $capacity === false ||
            $capacity < 1 ||
            !in_array($status, ['open', 'closed'], true)
        ) {
            flashMessage('Please enter valid values in all required fields.', 'error');
        }

        $date = DateTime::createFromFormat('!Y-m-d', $eventDate);

        if (!$date || $date->format('Y-m-d') !== $eventDate) {
            flashMessage('Please select a valid event date.', 'error');
        }

        if (
            mb_strlen($title) > 150 ||
            mb_strlen($venue) > 150 ||
            mb_strlen($category) > 100
        ) {
            flashMessage('Title, venue, or category is too long.', 'error');
        }

        if ($action === 'edit' && !$id) {
            flashMessage('Invalid event ID.', 'error');
        }

        $oldPoster = null;

        if ($action === 'edit') {
            $check = $mysqli->prepare(
                'SELECT poster FROM events WHERE event_id = ?'
            );
            $check->bind_param('i', $id);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$existing) {
                flashMessage('Event not found.', 'error');
            }

            $oldPoster = $existing['poster'];
        }

        $upload = uploadPoster('poster', $uploadDir, $maxFileSize);

        if ($upload['error'] !== null) {
            flashMessage($upload['error'], 'error');
        }

        $poster = $upload['name'] ?? $oldPoster;

        if ($action === 'add') {
            $stmt = $mysqli->prepare(
                'INSERT INTO events
                (title, description, event_date, venue, capacity, category, status, poster)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'ssssisss',
                $title,
                $description,
                $eventDate,
                $venue,
                $capacity,
                $category,
                $status,
                $poster
            );

            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                if ($upload['name'] !== null) {
                    removePoster($upload['name'], $uploadDir);
                }

                flashMessage('Could not add event.', 'error');
            }

            sh_audit($pdo, 'event_added', 'Added event: ' . $title);
            flashMessage('Event added successfully.');
        }

        $stmt = $mysqli->prepare(
            'UPDATE events SET
                title = ?, description = ?, event_date = ?, venue = ?,
                capacity = ?, category = ?, status = ?, poster = ?
             WHERE event_id = ?'
        );

        $stmt->bind_param(
            'ssssisssi',
            $title,
            $description,
            $eventDate,
            $venue,
            $capacity,
            $category,
            $status,
            $poster,
            $id
        );

        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            if ($upload['name'] !== null) {
                removePoster($upload['name'], $uploadDir);
            }

            flashMessage('Could not update event.', 'error');
        }

        if ($upload['name'] !== null && $oldPoster !== $upload['name']) {
            removePoster($oldPoster, $uploadDir);
        }

        sh_audit($pdo, 'event_updated', 'Updated event ID ' . $id . ': ' . $title);
        flashMessage('Event updated successfully.');
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['event_id'] ?? '', FILTER_VALIDATE_INT);

        if (!$id) {
            flashMessage('Invalid event ID.', 'error');
        }

        $stmt = $mysqli->prepare(
            'SELECT poster FROM events WHERE event_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$event) {
            flashMessage('Event not found.', 'error');
        }

        $stmt = $mysqli->prepare(
            'DELETE FROM events WHERE event_id = ?'
        );
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            flashMessage('Could not delete event.', 'error');
        }

        removePoster($event['poster'], $uploadDir);

        sh_audit($pdo, 'event_deleted', 'Deleted event ID ' . $id);
        flashMessage('Event deleted successfully.');
    }

    flashMessage('Invalid action.', 'error');
}

$editEvent = null;

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId) {
        $stmt = $mysqli->prepare(
            'SELECT * FROM events WHERE event_id = ?'
        );
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editEvent = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

if (!in_array($statusFilter, ['all', 'open', 'closed'], true)) {
    $statusFilter = 'all';
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(title LIKE ? OR description LIKE ? OR venue LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
    $types .= 'sss';
}

if ($categoryFilter !== '') {
    $where[] = 'category = ?';
    $params[] = $categoryFilter;
    $types .= 's';
}

if ($statusFilter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $mysqli->prepare(
    'SELECT COUNT(*) AS total FROM events' . $whereSql
);

if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$perPage = 10;
$totalPages = max(1, (int)ceil($total / $perPage));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $mysqli->prepare(
    'SELECT * FROM events' . $whereSql .
    ' ORDER BY event_date DESC, event_id DESC LIMIT ? OFFSET ?'
);

$listParams = $params;
$listTypes = $types . 'ii';
$listParams[] = $perPage;
$listParams[] = $offset;

$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$eventsResult = $listStmt->get_result();

$events = [];

while ($row = $eventsResult->fetch_assoc()) {
    $events[] = $row;
}

$listStmt->close();

$categoryResult = $mysqli->query(
    'SELECT DISTINCT category FROM events ORDER BY category'
);

$categories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

function eventPageUrl($pageNumber) {
    $query = $_GET;
    $query['page'] = $pageNumber;
    return '?' . http_build_query($query);
}
admin_shell_start('Events', 'events', 'Create, edit and delete campus events. Events saved here appear on the public Events page.');
?>

<?php if ($message !== ''): ?>
<div class="message <?= $messageType === 'error' ? 'error' : 'success' ?>">
    <?= e($message) ?>
</div>
<?php endif; ?>

<section class="card">
<h2><?= $editEvent ? 'Edit Event' : 'Add New Event' ?></h2>

<form method="POST" action="event_management.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['event_csrf']) ?>">
    <input type="hidden" name="action" value="<?= $editEvent ? 'edit' : 'add' ?>">

    <?php if ($editEvent): ?>
        <input type="hidden" name="event_id" value="<?= (int)$editEvent['event_id'] ?>">
    <?php endif; ?>

    <div class="grid">
        <div>
            <label for="title">Event Title *</label>
            <input id="title" name="title" maxlength="150" required
                   value="<?= e($editEvent['title'] ?? '') ?>">
        </div>

        <div>
            <label for="category">Category *</label>
            <input id="category" name="category" maxlength="100" required
                   list="category-options"
                   value="<?= e($editEvent['category'] ?? 'General') ?>">
            <datalist id="category-options">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div>
            <label for="event_date">Event Date *</label>
            <input id="event_date" type="date" name="event_date" required
                   value="<?= e($editEvent['event_date'] ?? '') ?>">
        </div>

        <div>
            <label for="venue">Venue *</label>
            <input id="venue" name="venue" maxlength="150" required
                   value="<?= e($editEvent['venue'] ?? '') ?>">
        </div>

        <div>
            <label for="capacity">Capacity *</label>
            <input id="capacity" type="number" name="capacity"
                   min="1" max="4294967295" required
                   value="<?= e($editEvent['capacity'] ?? '100') ?>">
        </div>

        <div>
            <label for="status">Status *</label>
            <select id="status" name="status">
                <option value="open"
                    <?= ($editEvent['status'] ?? 'open') === 'open' ? 'selected' : '' ?>>
                    Open
                </option>
                <option value="closed"
                    <?= ($editEvent['status'] ?? '') === 'closed' ? 'selected' : '' ?>>
                    Closed
                </option>
            </select>
        </div>

        <div>
            <label for="poster">Poster (JPG, PNG, WEBP; max 2 MB)</label>
            <input id="poster" type="file" name="poster"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <?php if (!empty($editEvent['poster'])): ?>
                <p>Current poster:</p>
                <img class="poster"
                     src="<?= e($uploadUrl . $editEvent['poster']) ?>"
                     alt="Current event poster">
            <?php endif; ?>
        </div>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= e($editEvent['description'] ?? '') ?></textarea>
        </div>
    </div>

    <p class="muted">Poster uploads: JPG, PNG, or WEBP. Maximum size: 2 MB.</p>

    <button type="submit" class="green">
        <?= $editEvent ? 'Update Event' : 'Add Event' ?>
    </button>

    <?php if ($editEvent): ?>
        <a class="button gray" href="event_management.php">Cancel</a>
    <?php endif; ?>
</form>
</section>

<section class="card">
<h2>Search and Filter Events</h2>

<form method="GET" action="event_management.php">
    <div class="filters">
        <div>
            <label for="search">Search</label>
            <input id="search" name="search"
                   placeholder="Title, description or venue"
                   value="<?= e($search) ?>">
        </div>

        <div>
            <label for="category-filter">Category</label>
            <select id="category-filter" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"
                        <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                        <?= e($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="status-filter">Status</label>
            <select id="status-filter" name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <div>
            <button type="submit">Search</button>
            <a class="button gray" href="event_management.php">Reset</a>
        </div>
    </div>
</form>
</section>

<section class="card">
<h2>Events</h2>
<p class="muted">Matching events: <?= $total ?> | Page <?= $page ?> of <?= $totalPages ?></p>

<div class="table-wrap">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Poster</th>
    <th>Title</th>
    <th>Category</th>
    <th>Date</th>
    <th>Venue</th>
    <th>Capacity</th>
    <th>Status</th>
    <th>Description</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$events): ?>
<tr><td colspan="10">No events found.</td></tr>
<?php else: ?>
    <?php foreach ($events as $event): ?>
    <tr>
        <td><?= (int)$event['event_id'] ?></td>
        <td>
            <?php if (!empty($event['poster'])): ?>
                <img class="poster"
                     src="<?= e($uploadUrl . $event['poster']) ?>"
                     alt="Event poster">
            <?php else: ?>
                <span class="muted">No poster</span>
            <?php endif; ?>
        </td>
        <td><?= e($event['title']) ?></td>
        <td><?= e($event['category']) ?></td>
        <td><?= e($event['event_date']) ?></td>
        <td><?= e($event['venue']) ?></td>
        <td><?= (int)$event['capacity'] ?></td>
        <td>
            <span class="status <?= $event['status'] === 'closed' ? 'closed' : '' ?>">
                <?= e(ucfirst($event['status'])) ?>
            </span>
        </td>
        <td><?= e($event['description']) ?></td>
        <td>
            <div class="actions">
                <a class="button"
                   href="?<?= e(http_build_query(array_merge($_GET, ['edit' => $event['event_id']]))) ?>">
                    Edit
                </a>

                <form method="POST" action="event_management.php"
                      onsubmit="return confirm('Delete this event and its poster?');">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['event_csrf']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="event_id" value="<?= (int)$event['event_id'] ?>">
                    <button class="red" type="submit">Delete</button>
                </form>
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
        <a href="<?= e(eventPageUrl($page - 1)) ?>">Previous</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="<?= $i === $page ? 'current' : '' ?>"
           href="<?= e(eventPageUrl($i)) ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= e(eventPageUrl($page + 1)) ?>">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</section>
<?php admin_shell_end(); ?>


<?php
$mysqli->close();
?>
