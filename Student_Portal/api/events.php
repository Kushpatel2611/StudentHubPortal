<?php
/**
 * Public, read-only list of events (events created in the admin panel).
 * events.html loads this first and falls back to data/events.json.
 * Output uses the same field names as data/events.json.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../db.php';

try {
    $rows = $pdo->query(
        'SELECT event_id, title, description, event_date, venue, category, status, poster
         FROM events ORDER BY event_date ASC, event_id ASC'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('StudentHub events API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Events are not available.']);
    exit;
}

$events = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['event_id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'date' => $row['event_date'],
        'venue' => $row['venue'],
        'status' => $row['status'],
        'description' => (string) ($row['description'] ?? ''),
        'poster' => $row['poster'] ? 'uploads/event_posters/' . $row['poster'] : null,
    ];
}, $rows);

echo json_encode($events);
