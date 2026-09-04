<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = read_json_body();
$email = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['error' => 'Email and password are required.'], 422);
}

if (strcasecmp($email, ADMIN_EMAIL) === 0 && password_verify($password, ADMIN_PASSWORD_HASH)) {
    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
    json_response(['ok' => true, 'admin' => true]);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['error' => 'Incorrect email or password.'], 401);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];

json_response(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
