
<?php

require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Database and configuration
|--------------------------------------------------------------------------
*/

$mysqli = sh_connect_mysqli();
$mysqli->set_charset('utf8mb4');

$uploadDir = __DIR__ . '/uploads/event_posters/';
$uploadUrl = 'uploads/event_posters/';
$maxFileSize = 2 * 1024 * 1024;

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        die('Unable to create the poster upload directory.');
    }
}

if (empty($_SESSION['event_csrf'])) {
    $_SESSION['event_csrf'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function eventFlash(string $message, string $type = 'success'): void
{
    $_SESSION['event_flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

function eventRedirect(array $params = []): never
{
    $url = 'event_management.php';

    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}

function eventAudit(string $action, string $details): void
{
    global $pdo;

    if (function_exists('sh_audit') && isset($pdo) && $pdo instanceof PDO) {
        try {
            sh_audit($pdo, $action, $details);
        } catch (Throwable $exception) {
            error_log('Event audit failed: ' . $exception->getMessage());
        }
    }
}

function removePoster(?string $filename, string $directory): void
{
    if (!$filename) {
        return;
    }

    // Only allow a filename, never a path supplied by a request.
    if (basename($filename) !== $filename) {
        return;
    }

    $path = $directory . $filename;

    if (is_file($path)) {
        @unlink($path);
    }
}

function uploadPoster(
    string $field,
    string $directory,
    int $maxSize
): ?string {
    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Poster upload failed. Please try again.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        throw new RuntimeException('Poster must be smaller than 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP posters are allowed.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Upload directory is unavailable.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    $destination = $directory . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the poster.');
    }

    return $filename;
}

function eventPageUrl(array $overrides = []): string
{
    $params = $_GET;

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return 'event_management.php' .
        ($params ? '?' . http_build_query($params) : '');
}

/*
|--------------------------------------------------------------------------
| Flash message and POST processing
|--------------------------------------------------------------------------
*/

$flash = $_SESSION['event_flash'] ?? null;
unset($_SESSION['event_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['event_csrf'] ?? '');

    if (
        $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        eventFlash('Security validation failed. Please try again.', 'error');
        eventRedirect();
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        /*
        |------------------------------------------------------------------
        | ADD EVENT
        |------------------------------------------------------------------
        */

        if ($action === 'add') {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $eventDate = trim((string)($_POST['event_date'] ?? ''));
            $venue = trim((string)($_POST['venue'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $capacity = filter_var(
                $_POST['capacity'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $status = (string)($_POST['status'] ?? 'open');

            if (
                $title === '' ||
                $eventDate === '' ||
                $venue === '' ||
                $category === '' ||
                $capacity === false
            ) {
                throw new RuntimeException('Please fill in all required fields correctly.');
            }

            $date = DateTime::createFromFormat('Y-m-d\TH:i', $eventDate);

            if (!$date || $date->format('Y-m-d\TH:i') !== $eventDate) {
                throw new RuntimeException('Please enter a valid event date and time.');
            }

            if (!in_array($status, ['open', 'closed'], true)) {
                throw new RuntimeException('Invalid event status.');
            }

            $poster = uploadPoster('poster', $uploadDir, $maxFileSize);

            $stmt = $mysqli->prepare(
                'INSERT INTO events
                    (title, description, event_date, venue, capacity,
                     category, status, poster)
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

            if (!$stmt->execute()) {
                if ($poster !== null) {
                    removePoster($poster, $uploadDir);
                }

                throw new RuntimeException('Could not add the event.');
            }

            $eventId = $stmt->insert_id;
            $stmt->close();

            eventAudit('event_created', 'Created event ID ' . $eventId);
            eventFlash('Event added successfully.');
            eventRedirect();
        }

        /*
        |------------------------------------------------------------------
        | EDIT EVENT
        |------------------------------------------------------------------
        */

        if ($action === 'edit') {
            $id = filter_var(
                $_POST['event_id'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $eventDate = trim((string)($_POST['event_date'] ?? ''));
            $venue = trim((string)($_POST['venue'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $capacity = filter_var(
                $_POST['capacity'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $status = (string)($_POST['status'] ?? 'open');

            if (
                !$id ||
                $title === '' ||
                $eventDate === '' ||
                $venue === '' ||
                $category === '' ||
                $capacity === false
            ) {
                throw new RuntimeException('Please fill in all required fields correctly.');
            }

            $date = DateTime::createFromFormat('Y-m-d\TH:i', $eventDate);

            if (!$date || $date->format('Y-m-d\TH:i') !== $eventDate) {
                throw new RuntimeException('Please enter a valid event date and time.');
            }

            if (!in_array($status, ['open', 'closed'], true)) {
                throw new RuntimeException('Invalid event status.');
            }

            $stmt = $mysqli->prepare(
                "SELECT poster FROM events
                 WHERE event_id = ? AND status != 'deleted'"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                throw new RuntimeException('Event not found or already deleted.');
            }

            $oldPoster = $existing['poster'];
            $newPoster = uploadPoster('poster', $uploadDir, $maxFileSize);
            $poster = $newPoster ?? $oldPoster;

            $stmt = $mysqli->prepare(
                "UPDATE events
                 SET title = ?, description = ?, event_date = ?,
                     venue = ?, capacity = ?, category = ?, status = ?,
                     poster = ?
                 WHERE event_id = ? AND status != 'deleted'"
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

            if (!$stmt->execute()) {
                if ($newPoster !== null) {
                    removePoster($newPoster, $uploadDir);
                }

                throw new RuntimeException('Could not update the event.');
            }

            $stmt->close();

            if ($newPoster !== null && $oldPoster !== $newPoster) {
                removePoster($oldPoster, $uploadDir);
            }

            eventAudit('event_updated', 'Updated event ID ' . $id);
            eventFlash('Event updated successfully.');
            eventRedirect();
        }

        /*
        |------------------------------------------------------------------
        | SOFT DELETE EVENT
        |------------------------------------------------------------------
        */

        if ($action === 'delete') {
            $id = filter_var(
                $_POST['event_id'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id) {
                throw new RuntimeException('Invalid event ID.');
            }

            $stmt = $mysqli->prepare(
                "UPDATE events
                 SET status = 'deleted'
                 WHERE event_id = ? AND status IN ('open', 'closed')"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected < 1) {
                throw new RuntimeException('Event not found or already deleted.');
            }

            // Do not remove the poster. It is needed if the event is restored.
            eventAudit('event_deleted', 'Moved event ID ' . $id . ' to deleted events');

            eventFlash('Event moved to deleted events.');
            eventRedirect(['status' => 'deleted']);
        }

        /*
        |------------------------------------------------------------------
        | RESTORE EVENT
        |------------------------------------------------------------------
        */

        if ($action === 'restore') {
            $id = filter_var(
                $_POST['event_id'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id) {
                throw new RuntimeException('Invalid event ID.');
            }

            $stmt = $mysqli->prepare(
                "UPDATE events
                 SET status = 'open'
                 WHERE event_id = ? AND status = 'deleted'"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected !== 1) {
                throw new RuntimeException('Could not restore the event. Check that it is deleted.');
            }

            eventAudit('event_restored', 'Restored event ID ' . $id);

            eventFlash('Event restored successfully.');
            eventRedirect();
        }

        throw new RuntimeException('Invalid action.');

    } catch (Throwable $exception) {
        error_log('Event management error: ' . $exception->getMessage());

        eventFlash($exception->getMessage(), 'error');
        eventRedirect();
    }
}

/*
|--------------------------------------------------------------------------
| Load event for editing
|--------------------------------------------------------------------------
*/

$editEvent = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId) {
    $stmt = $mysqli->prepare(
        "SELECT * FROM events
         WHERE event_id = ? AND status != 'deleted'"
    );

    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editEvent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editEvent) {
        eventFlash('Event not found or already deleted.', 'error');
        eventRedirect();
    }
}

/*
|--------------------------------------------------------------------------
| Search, filters and pagination
|--------------------------------------------------------------------------
*/

$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');

if (!in_array($statusFilter, ['all', 'open', 'closed', 'deleted'], true)) {
    $statusFilter = 'all';
}

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
) ?: 1;

$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(title LIKE ? OR description LIKE ? OR venue LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'sss';
}

if ($categoryFilter !== '') {
    $where[] = 'category = ?';
    $params[] = $categoryFilter;
    $types .= 's';
}

if ($statusFilter === 'all') {
    // "All" means all events, including deleted events, for administrators.
} else {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countSql = 'SELECT COUNT(*) AS total FROM events' . $whereSql;
$stmt = $mysqli->prepare($countSql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalEvents = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int)ceil($totalEvents / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = 'SELECT * FROM events' . $whereSql .
    ' ORDER BY event_date DESC LIMIT ? OFFSET ?';

$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$stmt = $mysqli->prepare($listSql);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$result = $stmt->get_result();

$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

$stmt->close();

$categoryResult = $mysqli->query(
    'SELECT DISTINCT category FROM events
     WHERE category IS NOT NULL AND category != ""
     ORDER BY category'
);

$categories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

$mysqli->close();
?>

<style>
.event-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    color: var(--text-color, #172033);
}

.event-page h1,
.event-page h2 {
    margin-top: 0;
}

.event-card {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #dce2ea);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 22px;
}

.event-form-grid,
.event-filters {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.event-page .full-width {
    grid-column: 1 / -1;
}

.event-page label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}

.event-page input,
.event-page textarea,
.event-page select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    color: #172033;
}

.event-page textarea {
    min-height: 100px;
    resize: vertical;
}

.event-page button,
.event-page .button {
    display: inline-block;
    border: 0;
    border-radius: 7px;
    padding: 9px 13px;
    color: #fff;
    background: #2457d6;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.event-page .green {
    background: #16803c;
}

.event-page .red {
    background: #c62828;
}

.event-page .gray {
    background: #64748b;
}

.event-page .actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
}

.event-page .actions form {
    margin: 0;
}

.event-page .message {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.event-page .message.success {
    background: #dcfce7;
    color: #166534;
}

.event-page .message.error {
    background: #fee2e2;
    color: #991b1b;
}

.event-page .table-wrap {
    overflow-x: auto;
}

.event-page table {
    width: 100%;
    border-collapse: collapse;
}

.event-page th,
.event-page td {
    padding: 12px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

.event-page th {
    background: #f1f5f9;
    color: #172033;
}

.event-page .poster-thumb {
    width: 76px;
    height: 58px;
    object-fit: cover;
    border-radius: 5px;
}

.event-page .status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.event-page .status-open {
    background: #dcfce7;
    color: #166534;
}

.event-page .status-closed {
    background: #fef3c7;
    color: #92400e;
}

.event-page .status-deleted {
    background: #fee2e2;
    color: #991b1b;
}

.event-page .pagination {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 18px;
    flex-wrap: wrap;
}

.event-page .muted {
    color: #64748b;
}

@media (max-width: 700px) {
    .event-page {
        padding: 12px;
    }

    .event-form-grid,
    .event-filters {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
admin_shell_start('Event Management', 'events', 'Manage university events');
?>

<div class="event-page">

    <h1>Event Management</h1>
    <p class="muted">
        Add, update, search, filter, delete, and restore university events.
    </p>

    <?php if ($flash): ?>
        <div class="message <?= e($flash['type'] ?? 'success') ?>">
            <?= e($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <section class="event-card">
        <h2><?= $editEvent ? 'Edit Event' : 'Add New Event' ?></h2>

        <form method="POST"
              action="event_management.php"
              enctype="multipart/form-data">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['event_csrf']) ?>">

            <input type="hidden"
                   name="action"
                   value="<?= $editEvent ? 'edit' : 'add' ?>">

            <?php if ($editEvent): ?>
                <input type="hidden"
                       name="event_id"
                       value="<?= (int)$editEvent['event_id'] ?>">
            <?php endif; ?>

            <div class="event-form-grid">

                <div>
                    <label for="title">Event Title *</label>
                    <input id="title"
                           name="title"
                           maxlength="200"
                           required
                           value="<?= e($editEvent['title'] ?? '') ?>">
                </div>

                <div>
                    <label for="event_date">Event Date and Time *</label>
                    <input id="event_date"
                           type="datetime-local"
                           name="event_date"
                           required
                           value="<?php
                           if ($editEvent) {
                               echo e(date(
                                   'Y-m-d\TH:i',
                                   strtotime($editEvent['event_date'])
                               ));
                           }
                           ?>">
                </div>

                <div>
                    <label for="venue">Venue *</label>
                    <input id="venue"
                           name="venue"
                           maxlength="200"
                           required
                           value="<?= e($editEvent['venue'] ?? '') ?>">
                </div>

                <div>
                    <label for="capacity">Capacity *</label>
                    <input id="capacity"
                           type="number"
                           name="capacity"
                           min="1"
                           required
                           value="<?= e($editEvent['capacity'] ?? '100') ?>">
                </div>

                <div>
                    <label for="category">Category *</label>
                    <input id="category"
                           name="category"
                           maxlength="100"
                           required
                           list="event-categories"
                           value="<?= e($editEvent['category'] ?? '') ?>">

                    <datalist id="event-categories">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="open"
                            <?= ($editEvent['status'] ?? 'open') === 'open'
                                ? 'selected' : '' ?>>
                            Open
                        </option>
                        <option value="closed"
                            <?= ($editEvent['status'] ?? '') === 'closed'
                                ? 'selected' : '' ?>>
                            Closed
                        </option>
                    </select>
                </div>

                <div class="full-width">
                    <label for="description">Description</label>
                    <textarea id="description"
                              name="description"
                              maxlength="10000"><?= e($editEvent['description'] ?? '') ?></textarea>
                </div>

                <div class="full-width">
                    <label for="poster">Poster (JPG, PNG, WEBP; max 2 MB)</label>
                    <input id="poster"
                           type="file"
                           name="poster"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                    <?php if (!empty($editEvent['poster'])): ?>
                        <p>Current poster:</p>
                        <img class="poster-thumb"
                             src="<?= e($uploadUrl . rawurlencode($editEvent['poster'])) ?>"
                             alt="Current event poster">
                        <p class="muted">Choose a new image only if you want to replace it.</p>
                    <?php endif; ?>
                </div>

                <div class="full-width">
                    <button type="submit">
                        <?= $editEvent ? 'Update Event' : 'Add Event' ?>
                    </button>

                    <?php if ($editEvent): ?>
                        <a class="button gray" href="event_management.php">
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </form>
    </section>

    <section class="event-card">
        <h2>Search and Filter Events</h2>

        <form method="GET" action="event_management.php">
            <div class="event-filters">

                <div>
                    <label for="search">Search</label>
                    <input id="search"
                           type="search"
                           name="search"
                           placeholder="Title, description, or venue"
                           value="<?= e($search) ?>">
                </div>

                <div>
                    <label for="category-filter">Category</label>
                    <select id="category-filter" name="category">
                        <option value="">All Categories</option>

                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>"
                                <?= $categoryFilter === $category
                                    ? 'selected' : '' ?>>
                                <?= e($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status-filter">Status</label>
                    <select id="status-filter" name="status">
                        <option value="all"
                            <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                            All Events
                        </option>

                        <option value="open"
                            <?= $statusFilter === 'open' ? 'selected' : '' ?>>
                            Open
                        </option>

                        <option value="closed"
                            <?= $statusFilter === 'closed' ? 'selected' : '' ?>>
                            Closed
                        </option>

                        <option value="deleted"
                            <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>
                            Deleted
                        </option>
                    </select>
                </div>

                <div style="align-self:end">
                    <button type="submit">Apply Filters</button>
                    <a class="button gray" href="event_management.php">
                        Reset
                    </a>
                </div>

            </div>
        </form>
    </section>

    <section class="event-card">
        <h2>Events List</h2>

        <p class="muted">
            <?= count($events) ?> shown on this page;
            <?= $totalEvents ?> matching event(s) in total.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Poster</th>
                        <th>Event Details</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$events): ?>
                    <tr>
                        <td colspan="7">No events found.</td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= (int)$event['event_id'] ?></td>

                            <td>
                                <?php if (!empty($event['poster'])): ?>
                                    <img class="poster-thumb"
                                         src="<?= e($uploadUrl . rawurlencode($event['poster'])) ?>"
                                         alt="Event poster">
                                <?php else: ?>
                                    <span class="muted">No poster</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong><?= e($event['title']) ?></strong>
                                <br>
                                <span class="muted">
                                    <?= e($event['venue']) ?>
                                </span>
                                <?php if (!empty($event['description'])): ?>
                                    <br>
                                    <?= e(mb_strimwidth(
                                        $event['description'],
                                        0,
                                        100,
                                        '...'
                                    )) ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e(date(
                                    'd M Y, h:i A',
                                    strtotime($event['event_date'])
                                )) ?>
                            </td>

                            <td><?= e($event['category']) ?></td>

                            <td>
                                <span class="status status-<?= e($event['status']) ?>">
                                    <?= e(ucfirst($event['status'])) ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">

                                    <?php if ($event['status'] === 'deleted'): ?>

                                        <form method="POST"
                                              action="event_management.php"
                                              onsubmit="return confirm('Restore this event?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($_SESSION['event_csrf']) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="restore">

                                            <input type="hidden"
                                                   name="event_id"
                                                   value="<?= (int)$event['event_id'] ?>">

                                            <button class="green" type="submit">
                                                Restore
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <a class="button"
                                           href="<?= e(eventPageUrl([
                                               'edit' => (int)$event['event_id']
                                           ])) ?>">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="event_management.php"
                                              onsubmit="return confirm('Move this event to Deleted Events?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($_SESSION['event_csrf']) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="delete">

                                            <input type="hidden"
                                                   name="event_id"
                                                   value="<?= (int)$event['event_id'] ?>">

                                            <button class="red" type="submit">
                                                Delete
                                            </button>
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
                    <a class="button gray"
                       href="<?= e(eventPageUrl(['page' => $page - 1])) ?>">
                        Previous
                    </a>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a class="button gray"
                       href="<?= e(eventPageUrl(['page' => $page + 1])) ?>">
                        Next
                    </a>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </section>

</div>

<?php
admin_shell_end();
?>
