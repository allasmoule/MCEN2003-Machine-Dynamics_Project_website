<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $tutorialId = (int)($_POST['tutorial_id'] ?? 0);
    if ($id && $tutorialId) {
        $stmt = db()->prepare('DELETE FROM notes WHERE id = ? AND tutorial_id = ?');
        $stmt->execute([$id, $tutorialId]);
    }
    header('Location: notes.php?tutorial_id=' . $tutorialId);
    exit;
}
header('Location: subjects.php');
exit;
