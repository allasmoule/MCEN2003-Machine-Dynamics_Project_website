<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tutorialId = (int)($_GET['tutorial'] ?? 0);
if (!$tutorialId) {
    $tutorialId = (int)db()->query('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1')->fetchColumn();
}

$stmt = db()->prepare('SELECT * FROM pdfs WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$pdfs = $stmt->fetchAll();

if (empty($pdfs)) {
    $pdfs = [
        [
            'title' => 'Tutorial 1 Kinematics - Lecture & Workbook Guide',
            'url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            'description' => 'Complete lecture notes on linear and rotational kinematics equations.'
        ],
        [
            'title' => 'Kinematics Formulas & Vector Derivations Sheet',
            'url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            'description' => 'Quick reference PDF with kinematic vector component definitions and unit conversions.'
        ]
    ];
}

json_response(['pdfs' => $pdfs]);
