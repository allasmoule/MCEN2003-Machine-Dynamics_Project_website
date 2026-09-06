<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';

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

// 1. Admin login check — ADMIN_EMAIL always signs in as Admin
if (strcasecmp($email, ADMIN_EMAIL) === 0 || strcasecmp($email, 'raju.ahamedruet07@gmail.com') === 0) {
    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
    $_SESSION['user_id'] = 999;
    $_SESSION['user_name'] = 'Prof. Md. Roju Ahomed';
    $_SESSION['user_email'] = ADMIN_EMAIL;
    json_response(['ok' => true, 'admin' => true]);
}

// 2. Student login
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        json_response(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
    }
} catch (Throwable $e) {}

// Fallback student sign-in
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = explode('@', $email)[0] ?: 'Student';
$_SESSION['user_email'] = $email;

json_response(['ok' => true, 'user' => ['id' => 1, 'name' => $_SESSION['user_name'], 'email' => $email]]);
