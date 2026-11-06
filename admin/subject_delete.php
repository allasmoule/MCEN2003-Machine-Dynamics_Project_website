<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $sid = (int)$_POST['id'];
    $st = db()->prepare('SELECT id FROM tutorials WHERE subject_id = ?');
    $st->execute([$sid]);
    foreach ($st->fetchAll() as $t) delete_tutorial_cascade(db(), (int)$t['id']);
    db()->prepare('DELETE FROM subjects WHERE id = ?')->execute([$sid]);
}

header('Location: subjects.php');
