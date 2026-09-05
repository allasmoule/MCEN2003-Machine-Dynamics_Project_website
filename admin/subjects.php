<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$subjects = db()->query('
    SELECT s.*, 
           (SELECT COUNT(*) FROM tutorials t WHERE t.subject_id = s.id) AS tutorial_count,
           (SELECT COUNT(*) FROM questions q JOIN tutorials t ON q.tutorial_id = t.id WHERE t.subject_id = s.id) AS question_count,
           (SELECT COUNT(*) FROM videos v JOIN tutorials t ON v.tutorial_id = t.id WHERE t.subject_id = s.id) AS video_count,
           (SELECT COUNT(*) FROM pdfs p JOIN tutorials t ON p.tutorial_id = t.id WHERE t.subject_id = s.id) AS pdf_count,
           (SELECT COUNT(*) FROM notes n JOIN tutorials t ON n.tutorial_id = t.id WHERE t.subject_id = s.id) AS note_count
    FROM subjects s ORDER BY s.sort_order ASC, s.id ASC
')->fetchAll();

admin_page_start('Subjects', 'subjects');
?>
<div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
  <div>
    <h2 style="margin:0; font-size:18px; color:#1E293B; font-weight:700;">Subjects Overview</h2>
    <span class="meta" style="color:#64748B; font-size:13px;"><?= count($subjects) ?> subject<?= count($subjects) === 1 ? '' : 's' ?> active on website</span>
  </div>
  <a href="subject_form.php" class="btn btn-primary" style="background:#102A56; color:#fff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13.5px; box-shadow: 0 2px 8px rgba(16,42,86,0.2);">+ New Subject</a>
</div>

<?php if (!$subjects): ?>
  <div class="empty" style="background:#fff; padding:40px; text-align:center; border-radius:12px; border:1px solid #E2E8F0; color:#64748B;">No subjects yet — click "+ New Subject" to add your first subject.</div>
<?php else: ?>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
  <?php foreach ($subjects as $s): ?>
    <div onclick="window.location.href='tutorials.php?subject_id=<?= (int)$s['id'] ?>'" 
         style="background:#fff; border:1.5px solid #E2E8F0; border-radius:12px; padding:22px; cursor:pointer; transition:all 0.2s ease; position:relative; display:flex; flex-direction:column; justify-space-between; box-shadow:0 2px 10px rgba(0,0,0,0.03);"
         onmouseover="this.style.borderColor='#3B82F6'; this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(59,130,246,0.12)';"
         onmouseout="this.style.borderColor='#E2E8F0'; this.style.transform='none'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.03)';">
      
      <div>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
          <span style="font-size:11px; font-weight:700; background:#EFF6FF; color:#1D4ED8; padding:3px 9px; border-radius:12px; text-transform:uppercase; letter-spacing:0.04em;">
            <?= h($s['institution'] ?: 'Subject') ?>
          </span>
          <span style="font-size:12px; font-weight:700; background:#F1F5F9; color:#475569; padding:3px 9px; border-radius:12px;">
            <?= (int)$s['tutorial_count'] ?> Tutorial<?= (int)$s['tutorial_count'] === 1 ? '' : 's' ?>
          </span>
        </div>

        <h3 style="margin:8px 0 6px 0; font-size:18px; font-weight:700; color:#0F172A; line-height:1.3;"><?= h($s['name']) ?></h3>
        
        <?php if ($s['description']): ?>
          <p style="margin:0 0 16px 0; font-size:13px; color:#64748B; line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
            <?= h($s['description']) ?>
          </p>
        <?php else: ?>
          <p style="margin:0 0 16px 0; font-size:13px; color:#94A3B8; italic;">No description added.</p>
        <?php endif; ?>

        <!-- Stats Pill Badges -->
        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:18px;">
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">
            Questions: <strong><?= (int)$s['question_count'] ?></strong>
          </span>
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">
            Videos: <strong><?= (int)$s['video_count'] ?></strong>
          </span>
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">
            PDFs: <strong><?= (int)$s['pdf_count'] ?></strong>
          </span>
          <span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; color:#334155; padding:3px 8px; border-radius:6px;">
            Notes: <strong><?= (int)$s['note_count'] ?></strong>
          </span>
        </div>
      </div>

      <div style="padding-top:14px; border-top:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center;" onclick="event.stopPropagation();">
        <a href="tutorial_form.php?subject_id=<?= (int)$s['id'] ?>" class="btn btn-primary" style="padding:6px 12px; font-size:12px; border-radius:6px; background:#102A56; color:#fff; text-decoration:none; font-weight:600;">+ Add Tutorial</a>
        
        <div style="display:flex; align-items:center; gap:10px;">
          <a href="tutorials.php?subject_id=<?= (int)$s['id'] ?>" style="color:#2563EB; font-weight:600; font-size:12.5px; text-decoration:none;">Open Tutorials &rarr;</a>
          <a href="subject_form.php?id=<?= (int)$s['id'] ?>" style="color:#64748B; font-size:12.5px; text-decoration:none; font-weight:500;">Edit</a>
          <form method="post" action="subject_delete.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete <?= h($s['name']) ?> and all its tutorials, questions, videos, PDFs and notes? This cannot be undone.');">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button type="submit" style="color:#EF4444; background:transparent; border:none; cursor:pointer; font-size:12.5px; font-weight:500; padding:0;">Delete</button>
          </form>
        </div>
      </div>

    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_page_end(); ?>
