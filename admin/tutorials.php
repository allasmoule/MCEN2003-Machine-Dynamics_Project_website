<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$subjectId = (int)($_GET['subject_id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ?');
$stmt->execute([$subjectId]);
$subject = $stmt->fetch();
if (!$subject) { header('Location: subjects.php'); exit; }

$stmt = $pdo->prepare('
    SELECT t.*, (SELECT COUNT(*) FROM questions q WHERE q.tutorial_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM videos v WHERE v.tutorial_id = t.id) AS video_count
    FROM tutorials t WHERE t.subject_id = ? ORDER BY t.sort_order ASC, t.id ASC
');
$stmt->execute([$subjectId]);
$tutorials = $stmt->fetchAll();

admin_page_start($subject['name'], 'subjects', 'subjects.php', 'Back to subjects');
?>
<div class="toolbar">
  <span class="meta"><?= count($tutorials) ?> tutorial<?= count($tutorials) === 1 ? '' : 's' ?> in this subject</span>
  <a href="tutorial_form.php?subject_id=<?= $subjectId ?>" class="btn btn-primary">+ New Tutorial</a>
</div>

<?php if (!$tutorials): ?>
  <div class="empty">No tutorials yet — create one to start adding questions and videos.</div>
<?php else: ?>
<table>
  <thead><tr><th>Title</th><th>Questions</th><th>Videos</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($tutorials as $t): ?>
    <tr>
      <td><?= h($t['title']) ?></td>
      <td><?= (int)$t['question_count'] ?></td>
      <td><?= (int)$t['video_count'] ?></td>
      <td class="row-actions">
        <a href="question_form.php?tutorial_id=<?= (int)$t['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:12px; border-radius:4px; margin-right:6px; color:#fff; text-decoration:none;">+ Add Question</a>
        <a href="questions.php?tutorial_id=<?= (int)$t['id'] ?>">Questions (<?= (int)$t['question_count'] ?>)</a>
        <a href="videos.php?tutorial_id=<?= (int)$t['id'] ?>">Videos</a>
        <a href="tutorial_form.php?subject_id=<?= $subjectId ?>&id=<?= (int)$t['id'] ?>">Edit</a>
        <form method="post" action="tutorial_delete.php" onsubmit="return confirm('Delete <?= h($t['title']) ?> and all its questions and videos? This cannot be undone.');">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php admin_page_end(); ?>
