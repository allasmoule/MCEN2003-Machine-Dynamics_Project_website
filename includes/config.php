<?php
/**
 * Loads config.php (gitignored, your real settings) if it exists, then fills
 * in safe defaults so the site also runs without it.
 *
 * Database: SQLite by default — one file, database/app.db, shared with the
 * Node server (server.js). Set DB_DRIVER to 'mysql' in config.php only if you
 * really want MySQL instead.
 */
if (is_file(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

if (!defined('DB_DRIVER'))   define('DB_DRIVER', 'sqlite');
if (!defined('SQLITE_PATH')) define('SQLITE_PATH', __DIR__ . '/../database/app.db');

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', '');
if (!defined('DB_USER')) define('DB_USER', '');
if (!defined('DB_PASS')) define('DB_PASS', '');

if (!defined('ADMIN_EMAIL'))         define('ADMIN_EMAIL', 'Raju.ahamedruet07@gmail.com');
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm');

if (!defined('APP_SECRET')) {
    // Generate a per-install secret once and keep it next to the database
    // (database/ is blocked from the web by database/.htaccess).
    $secretFile = __DIR__ . '/../database/.app_secret';
    $secret = is_file($secretFile) ? trim((string)file_get_contents($secretFile)) : '';
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        @file_put_contents($secretFile, $secret);
    }
    define('APP_SECRET', $secret);
}
