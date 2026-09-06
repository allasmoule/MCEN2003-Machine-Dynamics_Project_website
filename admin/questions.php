<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$pdo = db();
$tutorialId = (int)($_GET['tutorial_id'] ?? 0);
$stmt = $pdo->prepare('SELECT t.*, s.name AS subject_name FROM tutorials t JOIN subjects s ON s.id = t.subject_id WHERE t.id = ?');
$stmt->execute([$tutorialId]);
$tutorial = $stmt->fetch();
if (!$tutorial) { header('Location: subjects.php'); exit; }

$stmt = $pdo->prepare('SELECT id, code, topic, title, sort_order FROM questions WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$questions = $stmt->fetchAll();

admin_page_start($tutorial['title'] . ' — Questions', 'subjects', 'tutorials.php?subject_id=' . $tutorial['subject_id'], 'Back to ' . $tutorial['subject_name']);
?>
<div class="toolbar">
  <span class="meta"><?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?> in <?= h($tutorial['title']) ?></span>
  <a href="question_form.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-primary">+ New Question</a>
</div>

<?php if (!$questions): ?>
  <div class="empty">No questions yet.</div>
<?php else: ?>
<table>
  <thead><tr><th>Code</th><th>Topic</th><th>Title</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($questions as $q): ?>
    <tr>
      <td><span class="code-chip"><?= h($q['code']) ?></span></td>
      <td><?= h($q['topic']) ?></td>
      <td><?= h($q['title']) ?></td>
      <td class="row-actions">
        <a href="question_form.php?tutorial_id=<?= $tutorialId ?>&id=<?= (int)$q['id'] ?>">Edit</a>
        <form method="post" action="question_delete.php" onsubmit="return confirm('Delete <?= h($q['code']) ?>? This cannot be undone.');">
          <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <input type="hidden" name="tutorial_id" value="<?= $tutorialId ?>">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php admin_page_end(); ?>
