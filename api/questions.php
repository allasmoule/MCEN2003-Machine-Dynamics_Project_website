<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!current_user()) {
    json_response(['error' => 'Not authenticated'], 401);
}

$pdo = db();
$tutorialId = isset($_GET['tutorial']) ? (int)$_GET['tutorial'] : 0;

if (!$tutorialId) {
    // Backward-compatible default: the first tutorial ever created.
    $tutorialId = (int)$pdo->query('SELECT id FROM tutorials ORDER BY id ASC LIMIT 1')->fetchColumn();
}

$stmt = $pdo->prepare('SELECT t.id, t.title, t.description, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?');
$stmt->execute([$tutorialId]);
$tutorial = $stmt->fetch();
if (!$tutorial) {
    json_response(['error' => 'Tutorial not found'], 404);
}

$stmt = $pdo->prepare('SELECT * FROM questions WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$rows = $stmt->fetchAll();

$questions = array_map(function ($r) {
    $q = [
        'id' => $r['q_key'],
        'code' => $r['code'],
        'topic' => $r['topic'],
        'title' => $r['title'],
        'statement' => $r['statement'],
        'given' => json_decode($r['given_json'], true),
        'hint' => json_decode($r['hint_json'], true),
        'parts' => json_decode($r['parts_json'], true),
        'steps' => json_decode($r['steps_json'], true),
        'original' => $r['original'],
    ];
    if ($r['sketch'] !== null && $r['sketch'] !== '') $q['sketch'] = $r['sketch'];
    if ($r['fig'] !== null && $r['fig'] !== '') $q['fig'] = $r['fig'];
    if ($r['fig_caption'] !== null && $r['fig_caption'] !== '') $q['figCap'] = $r['fig_caption'];
    if ($r['seed'] !== null && $r['seed'] !== '') $q['seed'] = $r['seed'];
    return $q;
}, $rows);

json_response([
    'tutorial' => [
        'id' => (int)$tutorial['id'],
        'title' => $tutorial['title'],
        'description' => $tutorial['description'],
        'subject_name' => $tutorial['subject_name'],
    ],
    'questions' => $questions,
]);
