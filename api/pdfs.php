<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tutorialId = (int)($_GET['tutorial'] ?? 0);
if (!$tutorialId) {
    json_response(['pdfs' => []]);
}

$stmt = db()->prepare('SELECT * FROM pdfs WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$pdfs = $stmt->fetchAll();

json_response(['pdfs' => $pdfs]);
