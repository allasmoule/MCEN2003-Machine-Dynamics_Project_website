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
    SELECT t.*, 
           (SELECT COUNT(*) FROM questions q WHERE q.tutorial_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM videos v WHERE v.tutorial_id = t.id) AS video_count,
           (SELECT COUNT(*) FROM pdfs p WHERE p.tutorial_id = t.id) AS pdf_count,
           (SELECT COUNT(*) FROM notes n WHERE n.tutorial_id = t.id) AS note_count
    FROM tutorials t WHERE t.subject_id = ? ORDER BY t.sort_order ASC, t.id ASC
');
$stmt->execute([$subjectId]);
$tutorials = $stmt->fetchAll();

admin_page_start($subject['name'] . ' — Tutorials', 'subjects', 'subjects.php', 'Back to Subjects');
?>
<div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
  <div>
    <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;"><?= h($subject['name']) ?> Tutorials</h2>
    <span class="meta" style="color:#64748B; font-size:13px;"><?= count($tutorials) ?> tutorial<?= count($tutorials) === 1 ? '' : 's' ?> available</span>
  </div>
  <a href="tutorial_form.php?subject_id=<?= $subjectId ?>" class="btn btn-primary" style="background:#102A56; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px;">+ New Tutorial</a>
</div>

<?php if (!$tutorials): ?>
  <div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No tutorials yet in <?= h($subject['name']) ?> — click "+ New Tutorial" to add one.</div>
<?php else: ?>
<div style="display:flex; flex-direction:column; gap:16px;">
  <?php foreach ($tutorials as $t): ?>
    <div style="background:#fff; border:1.5px solid #E2E8F0; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div>
          <h3 style="margin:0 0 4px 0; font-size:17px; font-weight:700; color:#0F172A;"><?= h($t['title']) ?></h3>
          <?php if ($t['description']): ?>
            <p style="margin:0; font-size:13px; color:#64748B;"><?= h($t['description']) ?></p>
          <?php endif; ?>
        </div>
        <div style="display:flex; gap:8px;">
          <a href="tutorial_form.php?subject_id=<?= $subjectId ?>&id=<?= (int)$t['id'] ?>" class="btn btn-outline" style="padding:5px 12px; font-size:12.5px; border-radius:6px; border:1px solid #CBD5E1; text-decoration:none; color:#334155; font-weight:600;">Edit Title</a>
          <form method="post" action="tutorial_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete <?= h($t['title']) ?> and all its questions, videos, PDFs and notes?');">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
            <button type="submit" style="padding:5px 12px; font-size:12.5px; border-radius:6px; border:1px solid #FECACA; background:#FEF2F2; color:#DC2626; font-weight:600; cursor:pointer;">Delete</button>
          </form>
        </div>
      </div>

      <!-- Tutorial Resources Buttons Grid -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; background:#F8FAFC; padding:14px; border-radius:10px; border:1px solid #F1F5F9;">
        
        <!-- 1. Questions -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">Questions</span>
            <span style="background:#EEF2FF; color:#4F46E5; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;"><?= (int)$t['question_count'] ?></span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="question_form.php?tutorial_id=<?= (int)$t['id'] ?>" style="flex:1; text-align:center; background:#4F46E5; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add Question</a>
            <a href="questions.php?tutorial_id=<?= (int)$t['id'] ?>" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

        <!-- 2. Videos -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">Videos</span>
            <span style="background:#FEF3C7; color:#D97706; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;"><?= (int)$t['video_count'] ?></span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="video_form.php?tutorial_id=<?= (int)$t['id'] ?>" style="flex:1; text-align:center; background:#D97706; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add Video</a>
            <a href="videos.php?tutorial_id=<?= (int)$t['id'] ?>" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

        <!-- 3. PDF Documents -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">PDF Documents</span>
            <span style="background:#ECFDF5; color:#059669; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;"><?= (int)$t['pdf_count'] ?></span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="pdf_form.php?tutorial_id=<?= (int)$t['id'] ?>" style="flex:1; text-align:center; background:#059669; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add PDF</a>
            <a href="pdfs.php?tutorial_id=<?= (int)$t['id'] ?>" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

        <!-- 4. Text Notes -->
        <div style="background:#fff; border:1px solid #E2E8F0; padding:12px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; font-size:13px; color:#1E293B;">Text / Notes</span>
            <span style="background:#F3E8FF; color:#7C3AED; font-weight:700; font-size:11.5px; padding:2px 8px; border-radius:12px;"><?= (int)$t['note_count'] ?></span>
          </div>
          <div style="display:flex; gap:6px;">
            <a href="note_form.php?tutorial_id=<?= (int)$t['id'] ?>" style="flex:1; text-align:center; background:#7C3AED; color:#fff; padding:6px 8px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">+ Add Text Note</a>
            <a href="notes.php?tutorial_id=<?= (int)$t['id'] ?>" style="text-align:center; background:#F1F5F9; color:#334155; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:600; text-decoration:none;">Manage</a>
          </div>
        </div>

      </div>

    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_page_end(); ?>
