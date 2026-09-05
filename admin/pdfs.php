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

$stmt = $pdo->prepare('SELECT * FROM pdfs WHERE tutorial_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$tutorialId]);
$pdfs = $stmt->fetchAll();

admin_page_start($tutorial['title'] . ' — PDF Documents', 'subjects', 'tutorials.php?subject_id=' . $tutorial['subject_id'], 'Back to ' . $tutorial['subject_name']);
?>
<div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
  <div>
    <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">PDF Documents for <?= h($tutorial['title']) ?></h2>
    <span class="meta" style="color:#64748B; font-size:13px;"><?= count($pdfs) ?> PDF document<?= count($pdfs) === 1 ? '' : 's' ?> attached</span>
  </div>
  <a href="pdf_form.php?tutorial_id=<?= $tutorialId ?>" class="btn btn-primary" style="background:#059669; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New PDF</a>
</div>

<?php if (!$pdfs): ?>
  <div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No PDF documents added yet — click "+ New PDF" to upload or link a document.</div>
<?php else: ?>
<table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #E2E8F0; border-radius:10px; overflow:hidden;">
  <thead>
    <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; text-align:left; font-size:12.5px; color:#475569;">
      <th style="padding:12px 16px;">Title</th>
      <th style="padding:12px 16px;">Document Link / File</th>
      <th style="padding:12px 16px;">Description</th>
      <th style="padding:12px 16px; text-align:right;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pdfs as $p): ?>
    <tr style="border-bottom:1px solid #F1F5F9;">
      <td style="padding:14px 16px; font-weight:600; color:#0F172A;"><?= h($p['title']) ?></td>
      <td style="padding:14px 16px;">
        <a href="<?= h($p['url']) ?>" target="_blank" rel="noopener" style="color:#2563EB; font-weight:500; text-decoration:none;">Open PDF &rarr;</a>
      </td>
      <td style="padding:14px 16px; color:#64748B; font-size:13px;"><?= h($p['description'] ?: '—') ?></td>
      <td class="row-actions" style="padding:14px 16px; text-align:right;">
        <a href="pdf_form.php?tutorial_id=<?= $tutorialId ?>&id=<?= (int)$p['id'] ?>" style="color:#475569; font-weight:600; text-decoration:none; margin-right:12px;">Edit</a>
        <form method="post" action="pdf_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this PDF document?');">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="tutorial_id" value="<?= $tutorialId ?>">
          <button type="submit" style="color:#DC2626; background:transparent; border:none; cursor:pointer; font-weight:600; font-size:13px; padding:0;">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php admin_page_end(); ?>
