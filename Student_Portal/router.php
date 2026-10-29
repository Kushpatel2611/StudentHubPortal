<?php
/**
 * Router for PHP's built-in web server (START_SERVER.bat / start_server.sh).
 * The built-in server ignores .htaccess, so this file applies the same rules:
 *  - data/ stays private except the public .json files
 *  - nothing in uploads/ may run as PHP
 *  - sql/, config and lock files are not downloadable
 * Apache/XAMPP does not use this file.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (strpos($path, '..') !== false) {
    http_response_code(400);
    exit('Bad request');
}

$blocked =
    preg_match('#^/data/.+\.(csv|lock|tmp.*)$#i', $path) ||
    preg_match('#^/uploads/.+\.(php|phtml|phar|php[0-9]*)$#i', $path) ||
    preg_match('#^/sql/#i', $path) ||
    preg_match('#(^|/)(\.env.*|config\.php|.*\.lock)$#i', $path);

if ($blocked) {
    http_response_code(403);
    exit('Forbidden');
}

return false; // let the built-in server serve the file / run the PHP script as usual
