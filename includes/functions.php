<?php

function start_app_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data) && !empty($data)) {
        return $data;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return [];
}

function current_user(): ?array {
    start_app_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
    ];
}

function require_admin(): void {
    start_app_session();
    if (empty($_SESSION['is_admin'])) {
        header('Location: ../login.html');
        exit;
    }
}

/** Delete a tutorial and everything attached to it (SQLite has no FK cascade here). */
function delete_tutorial_cascade(PDO $pdo, int $tutorialId): void {
    foreach (['questions', 'videos', 'pdfs', 'slides', 'notes'] as $t) {
        $pdo->prepare("DELETE FROM $t WHERE tutorial_id = ?")->execute([$tutorialId]);
    }
    $pdo->prepare('DELETE FROM content_blocks WHERE block_key = ?')->execute(['formula_sheet_t' . $tutorialId]);
    $pdo->prepare('DELETE FROM tutorials WHERE id = ?')->execute([$tutorialId]);
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
