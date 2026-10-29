#!/usr/bin/env bash
# macOS / Linux: start the site with PHP's built-in server.
# Needs PHP 8+ with the pdo_mysql and mysqli extensions, and a running MySQL/MariaDB.
cd "$(dirname "$0")" || exit 1
echo "StudentHub is running at  http://localhost:8000/"
echo "First time? Open        http://localhost:8000/setup.php"
exec php -S localhost:8000 -t . router.php
