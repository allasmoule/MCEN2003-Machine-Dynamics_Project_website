<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$tutorialId = isset($_GET['tutorial']) ? (int)$_GET['tutorial'] : 0;
if (!$tutorialId) {
    $tutorialId = (int)db()->query('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1')->fetchColumn();
}

$stmt = db()->prepare('SELECT title, url, description FROM videos WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$videos = $stmt->fetchAll();

json_response(['videos' => $videos]);
