<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tutorialId = (int)($_GET['tutorial'] ?? 0);
if (!$tutorialId) {
    $tutorialId = (int)db()->query('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1')->fetchColumn();
}

$stmt = db()->prepare('SELECT * FROM notes WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$notes = $stmt->fetchAll();

if (empty($notes)) {
    $notes = [
        [
            'title' => 'Summary of Rectilinear & Curvilinear Motion',
            'description' => 'Core Kinematics Summary',
            'content' => "Key principles:\n1) Rectilinear motion: v = ds/dt, a = dv/dt. Constant acceleration: v = u + at, s = ut + 0.5*a*t².\n2) Curvilinear motion in Cartesian and Polar components: v_r = ṙ, v_θ = rθ̇, a_r = r̈ - rθ̇², a_θ = rθ̈ + 2ṙθ̇."
        ],
        [
            'title' => 'Relative Motion Vector Analysis Guide',
            'description' => 'Vector Derivations Guide',
            'content' => "For two points A and B on a rigid body:\nv_B = v_A + v_B/A where v_B/A = ω × r_B/A.\nRemember to separate into i and j vector components before solving for unknown scalar magnitudes."
        ]
    ];
}

json_response(['notes' => $notes]);
