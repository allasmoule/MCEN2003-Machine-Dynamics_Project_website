<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = read_json_body();
$name = trim((string)($body['name'] ?? ''));
$email = trim((string)($body['email'] ?? ''));
$phone = trim((string)($body['phone'] ?? ''));
$message = trim((string)($body['message'] ?? ''));
$type = trim((string)($body['type'] ?? 'General Inquiry'));

if ($name === '' || $email === '' || $phone === '' || $message === '') {
    json_response(['error' => 'All fields (Name, Email, Phone, Message) are required.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Please provide a valid email address.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('INSERT INTO contact_messages (type, name, email, phone, message) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$type, $name, $email, $phone, $message]);

json_response(['ok' => true, 'message' => 'Thank you! Your message has been sent successfully.']);
