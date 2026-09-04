<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = trim((string)($_GET['subject'] ?? ''));
if ($slug === '') {
    json_response(['error' => 'subject is required'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, slug, name, institution, description FROM subjects WHERE slug = ?');
$stmt->execute([$slug]);
$subject = $stmt->fetch();
if (!$subject) {
    json_response(['error' => 'Subject not found'], 404);
}

$stmt = $pdo->prepare('
    SELECT t.id, t.slug, t.title, t.description,
           (SELECT COUNT(*) FROM questions q WHERE q.tutorial_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM videos v WHERE v.tutorial_id = t.id) AS video_count
    FROM tutorials t WHERE t.subject_id = ? ORDER BY t.sort_order ASC, t.id ASC
');
$stmt->execute([$subject['id']]);
$tutorials = $stmt->fetchAll();

json_response(['subject' => $subject, 'tutorials' => $tutorials]);
