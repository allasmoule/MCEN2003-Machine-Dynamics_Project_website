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

$stmt = $pdo->prepare('SELECT * FROM videos WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$videos = $stmt->fetchAll();

admin_page_start($tutorial['title'] . ' — Videos', 'subjects', 'tutorials.php?subject_id=' . $tutorial['subject_id'], 'Back to ' . $tutorial['subject_name']);
?>
<div class="toolbar">
  <span class="meta"><?= count($videos) ?> video<?= count($videos) === 1 ? '' : 's' ?> in <?= h($tutorial['title']) ?></span>
  <a href="video_form.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-primary">+ New Video</a>
</div>

<?php if (!$videos): ?>
  <div class="empty">No videos yet — paste a YouTube/Vimeo link to add one.</div>
<?php else: ?>
<table>
  <thead><tr><th>Title</th><th>URL</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($videos as $v): ?>
    <tr>
      <td><?= h($v['title']) ?></td>
      <td><a href="<?= h($v['url']) ?>" target="_blank" rel="noopener" style="color:#5B7FA6"><?= h($v['url']) ?></a></td>
      <td class="row-actions">
        <a href="video_form.php?tutorial_id=<?= $tutorialId ?>&id=<?= (int)$v['id'] ?>">Edit</a>
        <form method="post" action="video_delete.php" onsubmit="return confirm('Delete this video?');">
          <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
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
