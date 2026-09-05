<?php
/**
 * Copy this file to config.php and fill in your real cPanel MySQL credentials.
 * config.php is gitignored — never commit real credentials.
 */

// cPanel > MySQL Databases. Host is almost always "localhost" on shared hosting.
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_cpanel_db_name');
define('DB_USER', 'your_cpanel_db_user');
define('DB_PASS', 'your_cpanel_db_password');

// Fixed admin sign-in — logging into the regular student login form with this
// exact email + password goes to the admin panel instead of the tutorial.
define('ADMIN_EMAIL', 'Raju.ahamedruet07@gmail.com');
define('ADMIN_PASSWORD_HASH', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm');

// Random long string used to sign session cookies. Change this to your own random value.
define('APP_SECRET', 'mcen2003-secret-key-123456789');
