<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = read_json_body();
$name = trim((string)($body['name'] ?? ''));
$email = trim((string)($body['email'] ?? ''));
$phone = trim((string)($body['phone'] ?? ''));
$batch = trim((string)($body['batch'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($name === '' || $email === '' || $phone === '' || $batch === '' || $password === '') {
    json_response(['error' => 'All fields are required.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Please enter a valid email address.'], 422);
}
if (strlen($password) < 6) {
    json_response(['error' => 'Password must be at least 6 characters.'], 422);
}
if (mb_strlen($name) > 150 || mb_strlen($phone) > 30 || mb_strlen($batch) > 60) {
    json_response(['error' => 'One of the fields is too long.'], 422);
}
if (strcasecmp($email, ADMIN_EMAIL) === 0) {
    json_response(['error' => 'This email is reserved.'], 409);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_response(['error' => 'An account with this email already exists.'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (name, email, phone, batch, password_hash) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$name, $email, $phone, $batch, $hash]);

$userId = (int)$pdo->lastInsertId();

$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['user_email'] = $email;

json_response(['ok' => true, 'user' => ['id' => $userId, 'name' => $name, 'email' => $email]]);
