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

if (empty($videos)) {
    $videos = [
        [
            'title' => 'Kinematics of Particles & Rigid Bodies - Concept Overview',
            'url' => 'https://www.youtube.com/watch?v=XTO8A2eB6C8',
            'description' => 'Step-by-step introduction to linear, angular, and relative velocity/acceleration equations.'
        ],
        [
            'title' => 'Relative Velocity & Acceleration Analysis in Planar Mechanisms',
            'url' => 'https://www.youtube.com/watch?v=0kF170-xTlg',
            'description' => 'Detailed walkthrough of relative motion equations for multi-link planar mechanisms.'
        ]
    ];
}

json_response(['videos' => $videos]);
