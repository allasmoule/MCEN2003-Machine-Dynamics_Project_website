<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

const DEMO_EMAIL = 'demo@mcen2003.local';
const DEMO_NAME = 'Demo Student';

$userId = 1;
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
    $stmt->execute([DEMO_EMAIL]);
    $user = $stmt->fetch();

    if (!$user) {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, batch, password_hash) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([DEMO_NAME, DEMO_EMAIL, '0000000000', 'Demo', $hash]);
        $user = ['id' => (int)$pdo->lastInsertId(), 'name' => DEMO_NAME, 'email' => DEMO_EMAIL];
    }
    $userId = (int)$user['id'];
} catch (Throwable $e) {
    // If DB is offline, continue with session ID 1
}

$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = DEMO_NAME;
$_SESSION['user_email'] = DEMO_EMAIL;

json_response(['ok' => true, 'user' => ['id' => $userId, 'name' => DEMO_NAME, 'email' => DEMO_EMAIL]]);
