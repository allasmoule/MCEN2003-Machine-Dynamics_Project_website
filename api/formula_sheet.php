<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!current_user()) {
    json_response(['error' => 'Not authenticated'], 401);
}

$pdo = db();
$tutorialId = isset($_GET['tutorial']) ? (int)$_GET['tutorial'] : 0;
$row = null;

if ($tutorialId) {
    $stmt = $pdo->prepare('SELECT value_json FROM content_blocks WHERE block_key = ?');
    $stmt->execute(['formula_sheet_t' . $tutorialId]);
    $row = $stmt->fetch();
}

if (!$row) {
    $stmt = $pdo->prepare('SELECT value_json FROM content_blocks WHERE block_key = ?');
    $stmt->execute(['formula_sheet']);
    $row = $stmt->fetch();
}

json_response(['formula_sheet' => $row ? json_decode($row['value_json'], true) : null]);
