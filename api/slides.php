<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tutorialId = (int)($_GET['tutorial'] ?? 0);
if (!$tutorialId) {
    $tutorialId = (int)db()->query('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1')->fetchColumn();
}

$stmt = db()->prepare('SELECT * FROM slides WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);

json_response(['slides' => $stmt->fetchAll()]);
