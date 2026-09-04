<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$tutorialId = (int)($_POST['tutorial_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $stmt = db()->prepare('DELETE FROM questions WHERE id = ?');
    $stmt->execute([(int)$_POST['id']]);
}

header('Location: questions.php?tutorial_id=' . $tutorialId);
