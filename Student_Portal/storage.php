<?php
/**
 * Small file-storage helpers (CSV messages + JSON lists) shared by
 * practical7.php, api/contact.php, api/feedback.php, admin_messages.php and admin_faqs.php.
 * Files live in data/ and are written with a lock so two requests cannot corrupt them.
 */

function sh_data_dir(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/** Stops spreadsheet programs from running user text as a formula. */
function sh_csv_safe(string $value): string
{
    return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
}

/** Appends one row (writes the header first when the file is new/empty). */
function sh_csv_append(string $file, array $headers, array $row): bool
{
    $handle = @fopen(sh_data_dir() . '/' . $file, 'c+');
    if ($handle === false) {
        return false;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }
    fseek($handle, 0, SEEK_END);
    if (ftell($handle) === 0) {
        fputcsv($handle, $headers);
    }
    $ok = fputcsv($handle, $row) !== false;
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $ok;
}

/** @return array{0: array, 1: array} [headers, rows] */
function sh_csv_read(string $file): array
{
    $path = sh_data_dir() . '/' . $file;
    $records = [];
    if (is_readable($path) && ($handle = fopen($path, 'r')) !== false) {
        if (flock($handle, LOCK_SH)) {
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null]) {
                    continue; // blank line
                }
                $records[] = $row;
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
    $headers = $records ? array_shift($records) : [];
    return [$headers, $records];
}

/** Removes the row whose first column equals $id. */
function sh_csv_delete(string $file, string $id): bool
{
    $path = sh_data_dir() . '/' . $file;
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return false;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }
    $rows = [];
    $found = false;
    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null]) {
            continue;
        }
        if ($rows && (string) ($row[0] ?? '') === $id) {
            $found = true;
            continue;
        }
        $rows[] = $row;
    }
    if ($found) {
        ftruncate($handle, 0);
        rewind($handle);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $found;
}

/** Reads a JSON list file; returns [] when missing or invalid. */
function sh_json_read(string $file): array
{
    $path = sh_data_dir() . '/' . $file;
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/** Writes a JSON list atomically (temp file + rename) under a lock. */
function sh_json_write(string $file, array $data): bool
{
    $path = sh_data_dir() . '/' . $file;
    $lock = @fopen($path . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        return false;
    }
    $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    $ok = $json !== false && file_put_contents($tmp, $json . "\n") !== false && rename($tmp, $path);
    if (!$ok && is_file($tmp)) {
        @unlink($tmp);
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

/** Rejects cross-site form posts to the public JSON endpoints. */
function sh_same_origin_or_fail(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }
    $originHost = parse_url($origin, PHP_URL_HOST);
    $originPort = parse_url($origin, PHP_URL_PORT);
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $originFull = $originHost . ($originPort ? ':' . $originPort : '');
    if (strcasecmp($originFull, $hostHeader) !== 0) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Cross-site requests are not allowed.']);
        exit;
    }
}
