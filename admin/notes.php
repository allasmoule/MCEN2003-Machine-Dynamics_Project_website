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

$stmt = $pdo->prepare('SELECT * FROM notes WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$notes = $stmt->fetchAll();

admin_page_start($tutorial['title'] . ' — Text Notes', 'subjects', 'tutorials.php?subject_id=' . $tutorial['subject_id'], 'Back to ' . $tutorial['subject_name']);
?>
<div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
  <div>
    <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">Text Notes & Reading Material for <?= h($tutorial['title']) ?></h2>
    <span class="meta" style="color:#64748B; font-size:13px;"><?= count($notes) ?> text note<?= count($notes) === 1 ? '' : 's' ?> attached</span>
  </div>
  <a href="note_form.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-primary" style="background:#7C3AED; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New Text Note</a>
</div>

<?php if (!$notes): ?>
  <div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No text notes added yet — click "+ New Text Note" to add reading material or explanations.</div>
<?php else: ?>
<div style="display:flex; flex-direction:column; gap:16px;">
  <?php foreach ($notes as $n): ?>
  <div style="background:#fff; border:1.5px solid #E2E8F0; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
      <div>
        <h3 style="margin:0 0 4px 0; font-size:16.5px; font-weight:700; color:#0F172A;"><?= h($n['title']) ?></h3>
        <?php if ($n['description']): ?>
          <span style="font-size:12px; color:#64748B; background:#F1F5F9; padding:2px 8px; border-radius:4px;"><?= h($n['description']) ?></span>
        <?php endif; ?>
      </div>
      <div style="display:flex; gap:10px; align-items:center;">
        <a href="note_form.php?tutorial_id=<?= $tutorialId ?>&id=<?= (int)$n['id'] ?>" style="color:#475569; font-weight:600; text-decoration:none; font-size:13px;">Edit</a>
        <form method="post" action="note_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this text note?');">
          <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
          <input type="hidden" name="tutorial_id" value="<?= $tutorialId ?>">
          <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-weight:600; font-size:13px; padding:0;">Delete</button>
        </form>
      </div>
    </div>
    
    <div style="background:#F8FAFC; border:1px solid #F1F5F9; border-radius:8px; padding:14px 16px; font-size:13.5px; line-height:1.6; color:#334155; white-space:pre-wrap; max-height:220px; overflow-y:auto;">
      <?= nl2br(h($n['content'])) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_page_end(); ?>
