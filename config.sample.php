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
// Generate the hash with:
//   php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
define('ADMIN_EMAIL', 'your-admin-email@example.com');
define('ADMIN_PASSWORD_HASH', '$2y$10$replace.with.a.real.hash.generated.above');

// Random long string used to sign session cookies. Change this to your own random value.
define('APP_SECRET', 'change-this-to-a-long-random-string');
