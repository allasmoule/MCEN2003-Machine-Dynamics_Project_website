<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

const DEMO_EMAIL = 'demo@mcen2003.local';
const DEMO_NAME = 'Demo Student';
const DEMO_PHONE = '0000000000';
const DEMO_BATCH = 'Demo';

$pdo = db();

$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
$stmt->execute([DEMO_EMAIL]);
$user = $stmt->fetch();

if (!$user) {
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, batch, password_hash) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([DEMO_NAME, DEMO_EMAIL, DEMO_PHONE, DEMO_BATCH, $hash]);
    $user = ['id' => (int)$pdo->lastInsertId(), 'name' => DEMO_NAME, 'email' => DEMO_EMAIL];
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];

json_response(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
