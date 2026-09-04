<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_ui.php';

require_admin();

$subjects = db()->query('
    SELECT s.*, (SELECT COUNT(*) FROM tutorials t WHERE t.subject_id = s.id) AS tutorial_count
    FROM subjects s ORDER BY s.sort_order ASC, s.id ASC
')->fetchAll();

admin_page_start('Subjects', 'subjects');
?>
<div class="toolbar">
  <span class="meta"><?= count($subjects) ?> subject<?= count($subjects) === 1 ? '' : 's' ?> on the homepage</span>
  <a href="subject_form.php" class="btn btn-primary">+ New Subject</a>
</div>

<?php if (!$subjects): ?>
  <div class="empty">No subjects yet — create one to show it on the homepage.</div>
<?php else: ?>
<table>
  <thead><tr><th>Name</th><th>Institution</th><th>Tutorials</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($subjects as $s): ?>
    <tr>
      <td><?= h($s['name']) ?></td>
      <td><?= h($s['institution'] ?? '') ?></td>
      <td><?= (int)$s['tutorial_count'] ?></td>
      <td class="row-actions">
        <a href="tutorials.php?subject_id=<?= (int)$s['id'] ?>">Tutorials</a>
        <a href="subject_form.php?id=<?= (int)$s['id'] ?>">Edit</a>
        <form method="post" action="subject_delete.php" onsubmit="return confirm('Delete <?= h($s['name']) ?> and all its tutorials, questions and videos? This cannot be undone.');">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php admin_page_end(); ?>
