<?php
/**
 * One-time / idempotent seeder — loads the original T1.1–T1.5 questions and the
 * formula sheet into the database. Safe to re-run: existing rows are updated in place.
 * Run via CLI (php seed/run_seed.php) or by an admin visiting this file once.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$questions = json_decode(file_get_contents(__DIR__ . '/questions_seed.json'), true);
$stmt = $pdo->prepare('
    INSERT INTO questions (q_key, code, topic, title, statement, sketch, fig, fig_caption, given_json, hint_json, seed, parts_json, steps_json, original, sort_order)
    VALUES (:q_key, :code, :topic, :title, :statement, :sketch, :fig, :fig_caption, :given_json, :hint_json, :seed, :parts_json, :steps_json, :original, :sort_order)
    ON DUPLICATE KEY UPDATE
      code=VALUES(code), topic=VALUES(topic), title=VALUES(title), statement=VALUES(statement),
      sketch=VALUES(sketch), fig=VALUES(fig), fig_caption=VALUES(fig_caption),
      given_json=VALUES(given_json), hint_json=VALUES(hint_json), seed=VALUES(seed),
      parts_json=VALUES(parts_json), steps_json=VALUES(steps_json), original=VALUES(original),
      sort_order=VALUES(sort_order)
');

$i = 0;
foreach ($questions as $q) {
    $i += 10;
    $stmt->execute([
        ':q_key' => $q['id'],
        ':code' => $q['code'],
        ':topic' => $q['topic'],
        ':title' => $q['title'],
        ':statement' => $q['statement'],
        ':sketch' => $q['sketch'] ?? null,
        ':fig' => $q['fig'] ?? null,
        ':fig_caption' => $q['figCap'] ?? null,
        ':given_json' => json_encode($q['given']),
        ':hint_json' => json_encode($q['hint']),
        ':seed' => $q['seed'] ?? null,
        ':parts_json' => json_encode($q['parts']),
        ':steps_json' => json_encode($q['steps']),
        ':original' => $q['original'],
        ':sort_order' => $i,
    ]);
    echo "Seeded {$q['code']}\n";
}

$formulaSheet = json_decode(file_get_contents(__DIR__ . '/formula_sheet_seed.json'), true);
$stmt2 = $pdo->prepare('
    INSERT INTO content_blocks (block_key, value_json) VALUES (\'formula_sheet\', :value_json)
    ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)
');
$stmt2->execute([':value_json' => json_encode($formulaSheet)]);
echo "Seeded formula_sheet\n";

echo "Done.\n";
